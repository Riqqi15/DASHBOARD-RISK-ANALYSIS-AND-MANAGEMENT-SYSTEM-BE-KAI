<?php

namespace App\Services;

use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\UnitSparePartPolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class SparePartWorkbookImporter
{
    private const SHEET = 'Reorder Stock';

    /** @var list<string> */
    private const HEADERS = [
        'System',
        'Sub-System',
        'Equipment',
        'Detail Equipment',
        'Max yearly Failure',
        'Average Yearly Failure',
        'Max Lead Time (Month)',
        'Average Lead Time (Month)',
        'Safety Stock',
        'Lead Time Demand',
        'Reorder Point',
        'Severity Equipment',
    ];

    public function __construct(
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly ReorderStockCalculator $reorderStockCalculator,
    ) {}

    public function supports(string $workbookPath): bool
    {
        if (! is_file($workbookPath)) {
            return false;
        }

        $reader = IOFactory::createReaderForFile($workbookPath);

        return in_array(self::SHEET, $reader->listWorksheetNames($workbookPath), true);
    }

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     duplicates_skipped: int,
     *     duplicate_locations: list<string>,
     *     skipped: int,
     *     issues: list<array<string, mixed>>
     * }
     */
    public function import(
        string $workbookPath,
        bool $bootstrapCategories = false,
        ?UnitKerja $unit = null,
        bool $skipUnmatchedCategories = false,
    ): array {
        if (! is_file($workbookPath)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$workbookPath}");
        }

        $workbookName = basename($workbookPath);
        $workbookHash = hash_file('sha256', $workbookPath);
        if ($workbookHash === false) {
            throw new RuntimeException("Fingerprint workbook gagal dibuat: {$workbookPath}");
        }
        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET]);
        $reader->setReadEmptyCells(false);
        $reader->setIgnoreRowsWithNoCells(true);
        $spreadsheet = $reader->load($workbookPath);

        try {
            $sheet = $spreadsheet->getSheetByName(self::SHEET);
            if (! $sheet) {
                throw new RuntimeException(
                    "Workbook {$workbookName}, sheet ".self::SHEET.', row 1, header: sheet tidak ditemukan.',
                );
            }

            $this->assertHeaders($sheet, $workbookName);

            return DB::transaction(
                fn (): array => $this->importRows(
                    $sheet,
                    $workbookName,
                    $bootstrapCategories,
                    $unit,
                    $workbookHash,
                    $skipUnmatchedCategories,
                ),
                3,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function assertHeaders(Worksheet $sheet, string $workbookName): void
    {
        foreach (self::HEADERS as $offset => $expected) {
            $actual = $this->text($sheet->getCell([$offset + 1, 1])->getValue());
            if ($this->normalize($actual) !== $this->normalize($expected)) {
                $actualLabel = $actual === '' ? '(kosong)' : $actual;

                throw new RuntimeException(
                    "Workbook {$workbookName}, sheet ".
                        self::SHEET.
                        ", row 1, header {$expected}: ditemukan {$actualLabel}.",
                );
            }
        }
    }

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     duplicates_skipped: int,
     *     duplicate_locations: list<string>,
     *     skipped: int,
     *     issues: list<array<string, mixed>>
     * }
     */
    private function importRows(
        Worksheet $sheet,
        string $workbookName,
        bool $bootstrapCategories,
        ?UnitKerja $unit,
        string $workbookHash,
        bool $skipUnmatchedCategories,
    ): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'duplicates_skipped' => 0,
            'duplicate_locations' => [],
            'skipped' => 0,
            'issues' => [],
        ];
        $currentGroup = '';
        $currentSystem = '';
        $currentEquipment = '';
        /** @var AssetSubsystem|null $resolvedGroupAnchor */
        $resolvedGroupAnchor = null;
        /** @var array<string, int> $sourceRows */
        $sourceRows = [];

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $group = $this->cellText($sheet, 1, $row, $workbookName, self::HEADERS[0]);
            $system = $this->cellText($sheet, 2, $row, $workbookName, self::HEADERS[1]);
            $equipment = $this->cellText($sheet, 3, $row, $workbookName, self::HEADERS[2]);
            $detailEquipment = $this->cellText($sheet, 4, $row, $workbookName, self::HEADERS[3]);

            $groupWasExplicit = $group !== '';
            if ($groupWasExplicit) {
                $resolvedGroupAnchor = null;
            }

            $currentGroup = $groupWasExplicit ? $group : $currentGroup;
            $currentSystem = $system !== '' ? $system : $currentSystem;
            $currentEquipment = $equipment !== '' ? $equipment : $currentEquipment;

            if ($detailEquipment === '') {
                continue;
            }

            $currentGroup = $this->boundedText($currentGroup, 255, $workbookName, $row, 'System');
            $currentSystem = $this->boundedText($currentSystem, 255, $workbookName, $row, 'Sub-System');
            $currentEquipment = $this->boundedText($currentEquipment, 255, $workbookName, $row, 'Equipment');
            $detailEquipment = $this->boundedText($detailEquipment, 255, $workbookName, $row, 'Detail Equipment');

            foreach (
                [
                    'System' => $currentGroup,
                    'Sub-System' => $currentSystem,
                    'Equipment' => $currentEquipment,
                ] as $header => $value
            ) {
                if ($value === '') {
                    throw $this->rowError($workbookName, $row, $header, 'nilai hierarchy kosong.');
                }
            }

            $sourceKey = $this->sourceKey($currentGroup, $currentSystem, $currentEquipment, $detailEquipment);
            if (isset($sourceRows[$sourceKey])) {
                throw $this->rowError(
                    $workbookName,
                    $row,
                    'Detail Equipment',
                    "duplikat source key dengan row {$sourceRows[$sourceKey]} dan row {$row}.",
                );
            }
            $sourceRows[$sourceKey] = $row;

            $subsystem = $this->resolveSubsystem(
                $currentGroup,
                $currentSystem,
                $currentEquipment,
                $workbookName,
                $row,
                $bootstrapCategories,
                $unit?->id,
                $skipUnmatchedCategories,
                $groupWasExplicit ? null : $resolvedGroupAnchor,
            );
            if (! $subsystem) {
                $result['skipped']++;
                $result['issues'][] = [
                    'sheet_name' => self::SHEET,
                    'source_row' => $row,
                    'source_column' => 'Equipment',
                    'severity' => 'warning',
                    'message' => "Hierarchy {$currentGroup}|{$currentSystem}|{$currentEquipment} ".
                        'tidak ditemukan pada master Predictive Data Asset; baris Reorder Stock dilewati.',
                ];

                continue;
            }
            $resolvedGroupAnchor = $subsystem;
            $reorderInputs = [
                'max_yearly_failure' => $this->nullableDecimal($sheet, 5, $row, $workbookName, self::HEADERS[4]),
                'average_yearly_failure' => $this->nullableDecimal($sheet, 6, $row, $workbookName, self::HEADERS[5]),
                'max_lead_time_months' => $this->nullableDecimal($sheet, 7, $row, $workbookName, self::HEADERS[6]),
                'average_lead_time_months' => $this->nullableDecimal($sheet, 8, $row, $workbookName, self::HEADERS[7]),
            ];
            $reorderValues = $this->calculateReorderValues($reorderInputs);
            $sourceValues = [
                'asset_subsystem_id' => $subsystem->id,
                'equipment' => $currentEquipment,
                'detail_equipment' => $detailEquipment,
                ...$reorderInputs,
                ...$reorderValues,
                'severity' => $this->nullableBoundedText(
                    $this->cellValue($sheet, 12, $row, $workbookName, self::HEADERS[11], false),
                    255,
                    $workbookName,
                    $row,
                    self::HEADERS[11],
                ),
            ];

            $part = SparePart::withTrashed()
                ->where('source_key', $sourceKey)
                ->lockForUpdate()
                ->first();

            if (! $part) {
                $legacyMatches = SparePart::withTrashed()
                    ->where(function ($query): void {
                        $query->whereNull('source_key')->orWhere('source_key', '');
                    })
                    ->where('asset_subsystem_id', $subsystem->id)
                    ->lockForUpdate()
                    ->get()
                    ->filter(
                        fn (SparePart $candidate): bool => $this->normalize((string) $candidate->equipment) === $this->normalize($currentEquipment) &&
                            $this->normalize((string) $candidate->detail_equipment) === $this->normalize($detailEquipment),
                    )
                    ->values();

                if ($legacyMatches->count() > 1) {
                    throw $this->rowError(
                        $workbookName,
                        $row,
                        'Detail Equipment',
                        'lebih dari satu master sparepart legacy cocok; rekonsiliasi manual diperlukan.',
                    );
                }

                $part = $legacyMatches->first();
            }

            if ($part?->trashed()) {
                $result['skipped']++;

                continue;
            }

            if ($part) {
                $part->source_key = $sourceKey;
                $part->fill($sourceValues);
                if ($part->isDirty()) {
                    $part->reorder_calculated_at = $part->reorder_calculation_status === 'calculated' ? now() : null;
                    $part->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                    $result['duplicates_skipped']++;
                    $result['duplicate_locations'][] = self::SHEET.'!'.$row;
                }

                $this->syncUnitPolicy($unit, $part, $sourceValues, $workbookName, $workbookHash, $row);

                continue;
            }

            try {
                $part = SparePart::query()->create([
                    ...$sourceValues,
                    'reorder_calculated_at' => $sourceValues['reorder_calculation_status'] === 'calculated'
                        ? now()
                        : null,
                    'source_key' => $sourceKey,
                    'code' => 'SP-'.strtoupper(substr($sourceKey, 0, 10)),
                    'unit_of_measure' => 'unit',
                    'is_active' => true,
                ]);
                $this->syncUnitPolicy($unit, $part, $sourceValues, $workbookName, $workbookHash, $row);
                $result['created']++;
            } catch (UniqueConstraintViolationException $exception) {
                $concurrent = SparePart::withTrashed()->where('source_key', $sourceKey)->lockForUpdate()->first();
                if (! $concurrent) {
                    throw $exception;
                }
                if ($concurrent->trashed()) {
                    $result['skipped']++;

                    continue;
                }

                $concurrent->fill($sourceValues);
                if ($concurrent->isDirty()) {
                    $concurrent->reorder_calculated_at =
                        $concurrent->reorder_calculation_status === 'calculated' ? now() : null;
                    $concurrent->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                    $result['duplicates_skipped']++;
                    $result['duplicate_locations'][] = self::SHEET.'!'.$row;
                }
                $this->syncUnitPolicy($unit, $concurrent, $sourceValues, $workbookName, $workbookHash, $row);
            }
        }

        $result['duplicate_locations'] = array_values(array_unique($result['duplicate_locations']));

        return $result;
    }

    /** @param array<string, mixed> $sourceValues */
    private function syncUnitPolicy(
        ?UnitKerja $unit,
        SparePart $part,
        array $sourceValues,
        string $workbookName,
        string $workbookHash,
        int $row,
    ): void {
        if (! $unit) {
            return;
        }

        $policy = UnitSparePartPolicy::query()->firstOrNew([
            'unit_kerja_id' => $unit->id,
            'spare_part_id' => $part->id,
        ]);
        $policy->fill([
            'source_key' => hash('sha256', "unit={$unit->id}|spare_part={$part->id}"),
            'workbook_hash' => $workbookHash,
            'workbook_name' => $workbookName,
            'source_row' => $row,
            'max_yearly_failure' => $sourceValues['max_yearly_failure'],
            'average_yearly_failure' => $sourceValues['average_yearly_failure'],
            'max_lead_time_months' => $sourceValues['max_lead_time_months'],
            'average_lead_time_months' => $sourceValues['average_lead_time_months'],
            'safety_stock' => $sourceValues['safety_stock'],
            'lead_time_demand' => $sourceValues['lead_time_demand'],
            'reorder_point' => $sourceValues['reorder_point'],
            'severity' => $sourceValues['severity'],
            'calculation_status' => $sourceValues['reorder_calculation_status'],
            'formula_version' => $sourceValues['reorder_formula_version'],
        ]);
        if ($policy->isDirty()) {
            $policy->calculated_at = $policy->calculation_status === 'calculated' ? now() : null;
            $policy->save();
        }
    }

    /**
     * @param  array{
     *     max_yearly_failure: ?string,
     *     average_yearly_failure: ?string,
     *     max_lead_time_months: ?string,
     *     average_lead_time_months: ?string
     * }  $inputs
     * @return array<string, int|string|null|Carbon>
     */
    private function calculateReorderValues(array $inputs): array
    {
        if (in_array(null, $inputs, true)) {
            return [
                'safety_stock' => null,
                'lead_time_demand' => null,
                'reorder_point' => null,
                'reorder_calculation_status' => 'insufficient_data',
                'reorder_formula_version' => ReorderStockCalculator::FORMULA_VERSION,
                'reorder_calculated_at' => null,
            ];
        }

        $calculation = $this->reorderStockCalculator->calculate(
            (float) $inputs['max_yearly_failure'],
            (float) $inputs['average_yearly_failure'],
            (float) $inputs['max_lead_time_months'],
            (float) $inputs['average_lead_time_months'],
        );

        return [
            'safety_stock' => $calculation['safety_stock'],
            'lead_time_demand' => $calculation['lead_time_demand'],
            'reorder_point' => $calculation['reorder_point'],
            'reorder_calculation_status' => $calculation['calculation_status'],
            'reorder_formula_version' => $calculation['formula_version'],
        ];
    }

    private function resolveSubsystem(
        string $groupName,
        string $systemName,
        string $subsystemName,
        string $workbookName,
        int $row,
        bool $bootstrapCategories,
        ?int $unitKerjaId,
        bool $skipUnmatchedCategories,
        ?AssetSubsystem $fallbackSubsystem = null,
    ): ?AssetSubsystem {
        $paths = $this->categoryPaths($groupName, $systemName, $subsystemName);
        $alias = AssetCategorySourceAlias::query()
            ->where('category_type', 'subsystem')
            ->where('unit_kerja_id', $unitKerjaId)
            ->where('normalized_source_path', $paths['subsystem']['normalized'])
            ->lockForUpdate()
            ->first();

        if ($alias) {
            $subsystem = AssetSubsystem::query()
                ->with('assetSystem.assetGroup')
                ->whereKey($alias->category_id)
                ->first();
            if (! $subsystem || ! $subsystem->assetSystem || ! $subsystem->assetSystem->assetGroup) {
                throw $this->rowError($workbookName, $row, 'Equipment', 'alias kategori rusak.');
            }
            if (
                ! $subsystem->is_active ||
                ! $subsystem->assetSystem->is_active ||
                ! $subsystem->assetSystem->assetGroup->is_active
            ) {
                throw $this->rowError($workbookName, $row, 'Equipment', 'alias kategori tidak aktif.');
            }

            $this->persistCategoryAliases($subsystem, $paths, $workbookName, $row, $unitKerjaId);

            return $subsystem;
        }

        $subsystem = $this->resolveActiveNamePath($groupName, $systemName, $subsystemName, $unitKerjaId);
        if ($subsystem) {
            $this->persistCategoryAliases($subsystem, $paths, $workbookName, $row, $unitKerjaId);

            return $subsystem;
        }

        $subsystem = $this->resolveSubsystemByWorkbookSystem($groupName, $systemName, $unitKerjaId);

        if (! $subsystem && $fallbackSubsystem) {
            $fallbackSubsystem->loadMissing('assetSystem.assetGroup');
            $sameGroup = $fallbackSubsystem->assetSystem?->assetGroup
                && $this->categoryNameMatches(
                    $fallbackSubsystem->assetSystem->assetGroup->name,
                    $groupName,
                );
            $subsystem = $sameGroup ? $fallbackSubsystem : null;
        }

        if ($subsystem) {
            $this->persistCategoryAliases($subsystem, $paths, $workbookName, $row, $unitKerjaId);

            return $subsystem;
        }

        if ($skipUnmatchedCategories) {
            return null;
        }

        if (! $bootstrapCategories) {
            throw $this->unmatchedCategoryError($workbookName, $row, $groupName, $systemName, $subsystemName);
        }

        $this->bootstrapParentAliases($groupName, $systemName, $paths, $workbookName, $row, $unitKerjaId);
        $resolved = $this->categoryResolver->resolve(
            $groupName,
            $systemName,
            $subsystemName,
            $workbookName,
            self::SHEET,
            $row,
            $unitKerjaId,
        );
        $subsystem = $resolved['subsystem']->load('assetSystem.assetGroup');
        if (
            ! $subsystem->is_active ||
            ! $subsystem->assetSystem->is_active ||
            ! $subsystem->assetSystem->assetGroup->is_active
        ) {
            throw $this->rowError($workbookName, $row, 'Equipment', 'kategori hasil bootstrap tidak aktif.');
        }

        return $subsystem;
    }

    private function resolveSubsystemByWorkbookSystem(
        string $groupName,
        string $systemName,
        ?int $unitKerjaId,
    ): ?AssetSubsystem {
        $groups = AssetGroup::query()
            ->where('is_active', true)
            ->where('unit_kerja_id', $unitKerjaId)
            ->with([
                'systems' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['subsystems' => fn ($subsystems) => $subsystems->where('is_active', true)]),
            ])
            ->get()
            ->filter(fn (AssetGroup $group): bool => $this->categoryNameMatches($group->name, $groupName));

        if ($groups->count() !== 1) {
            return null;
        }

        /** @var AssetGroup $group */
        $group = $groups->first();
        $subsystems = $group->systems
            ->flatMap(fn (AssetSystem $system) => $system->subsystems)
            ->filter(
                fn (AssetSubsystem $subsystem): bool => $this->categoryNameMatches($subsystem->name, $systemName),
            )
            ->values();

        return $subsystems->count() === 1 ? $subsystems->first() : null;
    }

    private function resolveActiveNamePath(
        string $groupName,
        string $systemName,
        string $subsystemName,
        ?int $unitKerjaId,
    ): ?AssetSubsystem {
        $groups = AssetGroup::query()
            ->where('is_active', true)
            ->where('unit_kerja_id', $unitKerjaId)
            ->with([
                'systems' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['subsystems' => fn ($subsystems) => $subsystems->where('is_active', true)]),
            ])
            ->get()
            ->filter(fn (AssetGroup $group): bool => $this->categoryNameMatches($group->name, $groupName));

        if ($groups->count() !== 1) {
            return null;
        }

        /** @var AssetGroup $group */
        $group = $groups->first();
        $systems = $group->systems->filter(
            fn (AssetSystem $system): bool => $this->categoryNameMatches($system->name, $systemName),
        );
        if ($systems->count() !== 1) {
            return null;
        }

        /** @var AssetSystem $system */
        $system = $systems->first();
        $subsystems = $system->subsystems->filter(
            fn (AssetSubsystem $subsystem): bool => $this->categoryNameMatches($subsystem->name, $subsystemName),
        );

        return $subsystems->count() === 1 ? $subsystems->first() : null;
    }

    /**
     * @param  array{
     *     group: array{source: string, normalized: string},
     *     system: array{source: string, normalized: string},
     *     subsystem: array{source: string, normalized: string}
     * }  $paths
     */
    private function bootstrapParentAliases(
        string $groupName,
        string $systemName,
        array $paths,
        string $workbookName,
        int $row,
        ?int $unitKerjaId,
    ): void {
        $groups = AssetGroup::query()
            ->where('is_active', true)
            ->where('unit_kerja_id', $unitKerjaId)
            ->get()
            ->filter(fn (AssetGroup $group): bool => $this->categoryNameMatches($group->name, $groupName));
        if ($groups->count() > 1) {
            throw $this->rowError($workbookName, $row, 'System', 'kategori global ambigu.');
        }
        if ($groups->count() !== 1) {
            return;
        }

        /** @var AssetGroup $group */
        $group = $groups->first();
        $this->persistAlias('group', $group->id, $paths['group'], $workbookName, $row, $unitKerjaId);

        $systems = $group
            ->systems()
            ->where('is_active', true)
            ->get()
            ->filter(fn (AssetSystem $system): bool => $this->categoryNameMatches($system->name, $systemName));
        if ($systems->count() > 1) {
            throw $this->rowError($workbookName, $row, 'Sub-System', 'kategori global ambigu.');
        }
        if ($systems->count() === 1) {
            $this->persistAlias('system', $systems->first()->id, $paths['system'], $workbookName, $row, $unitKerjaId);
        }
    }

    /**
     * @return array{
     *     group: array{source: string, normalized: string},
     *     system: array{source: string, normalized: string},
     *     subsystem: array{source: string, normalized: string}
     * }
     */
    private function categoryPaths(string $groupName, string $systemName, string $subsystemName): array
    {
        $groupPath = $groupName;
        $systemPath = "{$groupPath}|{$systemName}";
        $subsystemPath = "{$systemPath}|{$subsystemName}";

        return [
            'group' => ['source' => $groupPath, 'normalized' => $this->normalizedPath($groupName)],
            'system' => ['source' => $systemPath, 'normalized' => $this->normalizedPath($groupName, $systemName)],
            'subsystem' => [
                'source' => $subsystemPath,
                'normalized' => $this->normalizedPath($groupName, $systemName, $subsystemName),
            ],
        ];
    }

    /**
     * @param  array{
     *     group: array{source: string, normalized: string},
     *     system: array{source: string, normalized: string},
     *     subsystem: array{source: string, normalized: string}
     * }  $paths
     */
    private function persistCategoryAliases(
        AssetSubsystem $subsystem,
        array $paths,
        string $workbookName,
        int $row,
        ?int $unitKerjaId,
    ): void {
        $this->persistAlias(
            'group',
            $subsystem->assetSystem->assetGroup->id,
            $paths['group'],
            $workbookName,
            $row,
            $unitKerjaId,
        );
        $this->persistAlias('system', $subsystem->assetSystem->id, $paths['system'], $workbookName, $row, $unitKerjaId);
        $this->persistAlias('subsystem', $subsystem->id, $paths['subsystem'], $workbookName, $row, $unitKerjaId);
    }

    /** @param array{source: string, normalized: string} $path */
    private function persistAlias(
        string $type,
        int $categoryId,
        array $path,
        string $workbookName,
        int $row,
        ?int $unitKerjaId,
    ): void {
        $alias = AssetCategorySourceAlias::query()
            ->where('category_type', $type)
            ->where('unit_kerja_id', $unitKerjaId)
            ->where('normalized_source_path', $path['normalized'])
            ->lockForUpdate()
            ->first();

        if ($alias && $alias->category_id !== $categoryId) {
            throw $this->rowError($workbookName, $row, $this->aliasHeader($type), 'alias kategori konflik.');
        }

        if ($alias) {
            $alias->update([
                'source_path' => $path['source'],
                'workbook_name' => $workbookName,
                'sheet_name' => self::SHEET,
                'last_imported_at' => now(),
            ]);

            return;
        }

        try {
            AssetCategorySourceAlias::query()->create([
                'category_type' => $type,
                'category_id' => $categoryId,
                'unit_kerja_id' => $unitKerjaId,
                'source_path' => $path['source'],
                'normalized_source_path' => $path['normalized'],
                'workbook_name' => $workbookName,
                'sheet_name' => self::SHEET,
                'first_imported_at' => now(),
                'last_imported_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $concurrent = AssetCategorySourceAlias::query()
                ->where('category_type', $type)
                ->where('unit_kerja_id', $unitKerjaId)
                ->where('normalized_source_path', $path['normalized'])
                ->first();
            if (! $concurrent || $concurrent->category_id !== $categoryId) {
                throw $exception;
            }
        }
    }

    private function normalizedPath(string ...$parts): string
    {
        return implode('|', array_map($this->categoryResolver->normalize(...), $parts));
    }

    private function aliasHeader(string $type): string
    {
        return match ($type) {
            'group' => 'System',
            'system' => 'Sub-System',
            default => 'Equipment',
        };
    }

    private function unmatchedCategoryError(
        string $workbookName,
        int $row,
        string $groupName,
        string $systemName,
        string $subsystemName,
    ): RuntimeException {
        return $this->rowError(
            $workbookName,
            $row,
            'Equipment',
            "hierarchy {$groupName}|{$systemName}|{$subsystemName} tidak cocok dengan kategori global; ".
                'jalankan ulang dengan --bootstrap-categories hanya untuk bootstrap awal yang disetujui.',
        );
    }

    private function categoryNameMatches(string $categoryName, string $sourceName): bool
    {
        return $this->categoryComparable($categoryName) === $this->categoryComparable($sourceName);
    }

    private function categoryComparable(string $value): string
    {
        $value = preg_replace("/^\s*\d+\s*[.)-]?\s*/u", '', $value) ?? $value;
        $value = str_ireplace(['electric', 'ciruit'], ['elektrik', 'circuit'], $value);

        return $this->categoryResolver->normalize($value);
    }

    private function sourceKey(string ...$parts): string
    {
        return hash(
            'sha256',
            implode('|', [self::SHEET, ...array_map($this->categoryResolver->normalize(...), $parts)]),
        );
    }

    private function nullableDecimal(
        Worksheet $sheet,
        int $column,
        int $row,
        string $workbookName,
        string $header,
    ): ?string {
        $value = $this->cellValue($sheet, $column, $row, $workbookName, $header, true);
        if ($this->isBlank($value)) {
            return null;
        }
        if (! is_numeric($value)) {
            throw $this->rowError($workbookName, $row, $header, 'harus berupa angka desimal atau kosong.');
        }

        $number = (float) $value;
        if (! is_finite($number) || $number < 0 || $number > 99999999.99) {
            throw $this->rowError($workbookName, $row, $header, 'harus berada dalam rentang 0 sampai 99999999.99.');
        }
        if (abs($number * 100 - round($number * 100)) > 0.0000001) {
            throw $this->rowError($workbookName, $row, $header, 'maksimal memiliki 2 angka desimal.');
        }

        return number_format($number, 2, '.', '');
    }

    private function nullableQuantity(
        Worksheet $sheet,
        int $column,
        int $row,
        string $workbookName,
        string $header,
    ): ?int {
        $value = $this->cellValue($sheet, $column, $row, $workbookName, $header, true);
        if ($this->isBlank($value)) {
            return null;
        }
        if (! is_numeric($value)) {
            throw $this->rowError($workbookName, $row, $header, 'harus berupa bilangan bulat atau kosong.');
        }

        $number = (float) $value;
        if (! is_finite($number) || $number < 0 || $number > 4294967295 || floor($number) !== $number) {
            throw $this->rowError(
                $workbookName,
                $row,
                $header,
                'harus berupa bilangan bulat dalam rentang 0 sampai 4294967295.',
            );
        }

        return (int) $number;
    }

    private function nullableBoundedText(
        mixed $value,
        int $maximum,
        string $workbookName,
        int $row,
        string $header,
    ): ?string {
        $value = $this->text($value);

        return $value === '' ? null : $this->boundedText($value, $maximum, $workbookName, $row, $header);
    }

    private function boundedText(string $value, int $maximum, string $workbookName, int $row, string $header): string
    {
        if (mb_strlen($value) > $maximum) {
            throw $this->rowError($workbookName, $row, $header, "maksimal {$maximum} karakter.");
        }

        return $value;
    }

    private function cellText(Worksheet $sheet, int $column, int $row, string $workbookName, string $header): string
    {
        return $this->text($this->cellValue($sheet, $column, $row, $workbookName, $header, false));
    }

    private function cellValue(
        Worksheet $sheet,
        int $column,
        int $row,
        string $workbookName,
        string $header,
        bool $allowFormula,
    ): mixed {
        $cell = $sheet->getCell([$column, $row]);
        $rawValue = $cell->getValue();
        $isFormula = is_string($rawValue) && str_starts_with($rawValue, '=');
        if ($isFormula && ! $allowFormula) {
            throw $this->rowError($workbookName, $row, $header, 'formula tidak diizinkan pada kolom teks.');
        }

        try {
            $value = $cell->getCalculatedValue();
        } catch (Throwable $exception) {
            throw $this->rowError(
                $workbookName,
                $row,
                $header,
                'formula gagal dievaluasi: '.$exception->getMessage(),
            );
        }

        if ($isFormula && is_string($value) && str_starts_with($value, '#')) {
            throw $this->rowError($workbookName, $row, $header, "formula menghasilkan error {$value}.");
        }

        return $value;
    }

    private function text(mixed $value): string
    {
        $value = preg_replace('/^\s+|\s+$/u', '', (string) ($value ?? '')) ?? trim((string) ($value ?? ''));

        return preg_replace("/\s+/u", ' ', $value) ?? $value;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower($this->text($value));
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $this->text($value) === '' || in_array($this->text($value), ['-', '–'], true);
    }

    private function rowError(string $workbookName, int $row, string $header, string $message): RuntimeException
    {
        return new RuntimeException(
            "Workbook {$workbookName}, sheet ".self::SHEET.", row {$row}, header {$header}: {$message}",
        );
    }
}
