<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\PredictiveAssetSnapshot;
use App\Models\UnitKerja;
use App\Models\UnitSubsystemOpening;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class MasterAssetWorkbookImporter
{
    private const SHEET = 'Predictive Data Asset';

    /** @var array<string, string> */
    private const REQUIRED_HEADERS = [
        'aset prasarana sintel' => 'group',
        'system' => 'system',
        'subsystem' => 'subsystem',
        'total' => 'total',
        'sparepart in' => 'sparepart_in',
        'sparepart out' => 'sparepart_out',
        'tanggal pemasangan' => 'installed_at',
    ];

    /** @var array<string, string> */
    private const OPTIONAL_PREDICTIVE_HEADERS = [
        'criteria function' => 'function_criterion',
        'criteria production impact' => 'production_impact',
        'lead time (month)' => 'lead_time_months',
        'price' => 'price_category',
        'average usage in 2021' => 'average_yearly_usage',
        'sla' => 'sla',
        'safety stock based on failure' => 'failure_safety_stock',
        'comsumable/ sparepart' => 'item_classification',
        'repairable (y/n)' => 'repairable',
        'lifetime (years)' => 'lifetime_years',
        'jumlah vandalisme' => 'vandalism_count',
        'likelihood' => 'likelihood',
        'consequences' => 'consequence',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly PredictiveInventoryCalculator $predictiveInventoryCalculator,
        private readonly RiskAssessmentCalculator $riskAssessmentCalculator,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, openings_created: int, openings_updated: int, predictive_snapshots: int}
     */
    public function import(string $workbookPath, UnitKerja $unit): array
    {
        if (! is_file($workbookPath)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$workbookPath}");
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
                throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan.');
            }

            $columns = $this->headerColumns($sheet);

            $workbookHash = hash_file('sha256', $workbookPath);
            if ($workbookHash === false) {
                throw new RuntimeException("Fingerprint workbook gagal dibuat: {$workbookPath}");
            }

            return DB::transaction(fn (): array => $this->importRows(
                $sheet,
                $columns,
                $unit,
                basename($workbookPath),
                $workbookHash,
            ));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return array<string, int>
     */
    private function headerColumns(Worksheet $sheet): array
    {
        $columns = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn(2));

        for ($column = 1; $column <= $highestColumn; $column++) {
            $header = $this->text($sheet->getCell([$column, 2])->getValue());
            $key = mb_strtolower($header);

            $headers = [...self::REQUIRED_HEADERS, ...self::OPTIONAL_PREDICTIVE_HEADERS];
            if (isset($headers[$key])) {
                $columns[$headers[$key]] = $column;
            }
        }

        $missing = array_diff(array_values(self::REQUIRED_HEADERS), array_keys($columns));

        if ($missing !== []) {
            throw new RuntimeException('Header workbook tidak valid. Kolom wajib yang hilang: '.implode(', ', $missing).'.');
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     * @return array{created: int, updated: int, skipped: int, openings_created: int, openings_updated: int, predictive_snapshots: int}
     */
    private function importRows(
        Worksheet $sheet,
        array $columns,
        UnitKerja $unit,
        string $workbookName,
        string $workbookHash,
    ): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'openings_created' => 0,
            'openings_updated' => 0,
            'predictive_snapshots' => 0,
        ];
        $currentGroup = '';
        $currentSystem = '';
        $legacyCurrentSystem = '';
        $resolvedRowsBySubsystem = [];

        for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
            $group = $this->cellText($sheet, $columns['group'], $row);
            $system = $this->cellText($sheet, $columns['system'], $row);
            $subsystem = $this->cellText($sheet, $columns['subsystem'], $row);
            $legacySystem = $this->legacyCellText($sheet, $columns['system'], $row);
            $legacySubsystem = $this->legacyCellText($sheet, $columns['subsystem'], $row);

            $currentGroup = $group !== '' ? $group : $currentGroup;
            $currentSystem = $system !== '' ? $system : $currentSystem;
            $legacyCurrentSystem = $legacySystem !== '' ? $legacySystem : $legacyCurrentSystem;

            if ($currentGroup === '' || $currentSystem === '' || $subsystem === '') {
                $result['skipped']++;

                continue;
            }

            $categories = $this->categoryResolver->resolve(
                $currentGroup,
                $currentSystem,
                $subsystem,
                $workbookName,
                self::SHEET,
                $row,
            );
            $this->lockAndRevalidateCategories(
                $categories['group'],
                $categories['system'],
                $categories['subsystem'],
                $workbookName,
                $row,
            );

            $subsystemId = $categories['subsystem']->id;
            if (isset($resolvedRowsBySubsystem[$subsystemId])) {
                $firstRow = $resolvedRowsBySubsystem[$subsystemId];
                $path = implode('|', [
                    $categories['group']->name,
                    $categories['system']->name,
                    $categories['subsystem']->name,
                ]);

                throw new RuntimeException(
                    "Duplikat subsistem kanonis pada workbook {$workbookName}, sheet ".self::SHEET
                        .", row {$firstRow} dan row {$row}, path {$path}.",
                );
            }
            $resolvedRowsBySubsystem[$subsystemId] = $row;

            $sourceKey = $this->assetSourceKey($unit, $categories['subsystem']);
            $previousStableSourceKey = hash('sha256', implode('|', [
                $unit->code,
                self::SHEET,
                $categories['subsystem']->id,
            ]));
            $legacySourceKey = hash('sha256', implode('|', [
                $unit->code,
                self::SHEET,
                $legacyCurrentSystem,
                $legacySubsystem,
            ]));
            $sourceValues = [
                'unit_kerja_id' => $unit->getKey(),
                'asset_subsystem_id' => $categories['subsystem']->id,
                'aset_prasarana_sintel' => $currentGroup,
                'system' => $currentSystem,
                'subsystem' => $subsystem,
                'jumlah_unit' => $this->quantity(
                    $sheet->getCell([$columns['total'], $row])->getCalculatedValue(),
                    $workbookName,
                    $row,
                    'TOTAL',
                ),
                'tanggal_pemasangan' => $this->date($sheet->getCell([$columns['installed_at'], $row])->getCalculatedValue()),
                'source_key' => $sourceKey,
            ];

            $matchingAssets = Asset::withTrashed()
                ->where(function ($candidates) use (
                    $sourceKey,
                    $previousStableSourceKey,
                    $legacySourceKey,
                    $unit,
                    $categories,
                ): void {
                    $candidates
                        ->whereIn('source_key', [$sourceKey, $previousStableSourceKey, $legacySourceKey])
                        ->orWhere(function ($fallback) use ($unit, $categories): void {
                            $fallback
                                ->where('unit_kerja_id', $unit->id)
                                ->where('asset_subsystem_id', $categories['subsystem']->id)
                                ->whereNotNull('source_key');
                        });
                })
                ->lockForUpdate()
                ->get();

            if ($matchingAssets->count() > 1) {
                throw new RuntimeException(
                    "Konflik kandidat aset impor pada workbook {$workbookName}, sheet ".self::SHEET
                        .", row {$row}, path {$currentGroup}|{$currentSystem}|{$subsystem}: "
                        .$matchingAssets->count().' kandidat ditemukan.',
                );
            }

            if ($matchingAssets->contains(fn (Asset $candidate): bool => $candidate->trashed())) {
                $result['skipped']++;

                continue;
            }

            /** @var Asset|null $asset */
            $asset = $matchingAssets->first();

            if ($asset) {
                $before = $this->auditValues($asset);
                $asset->fill($sourceValues);
                if ($asset->isDirty()) {
                    $asset->save();
                    $this->auditLogger->record(
                        'asset.import_updated',
                        $asset,
                        $before,
                        $this->auditValues($asset->refresh()),
                    );
                    $result['updated']++;
                }
            } else {
                $asset = Asset::query()->create([
                    ...$sourceValues,
                    'nama_aset' => $subsystem,
                    'lokasi' => null,
                    'status' => AssetStatus::Aktif,
                ]);
                $this->auditLogger->record('asset.import_created', $asset, [], $this->auditValues($asset));
                $result['created']++;
            }

            $this->importOpening(
                $sheet,
                $columns,
                $row,
                $unit,
                $categories['subsystem'],
                $workbookName,
                $result,
            );
            $result['predictive_snapshots'] += $this->importPredictiveSnapshot(
                $sheet,
                $columns,
                $row,
                $asset,
                $sourceValues,
                $workbookName,
                $workbookHash,
            );
        }

        return $result;
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array<string, mixed>  $assetValues
     */
    private function importPredictiveSnapshot(
        Worksheet $sheet,
        array $columns,
        int $row,
        Asset $asset,
        array $assetValues,
        string $workbookName,
        string $workbookHash,
    ): int {
        $required = array_values(self::OPTIONAL_PREDICTIVE_HEADERS);
        if (array_diff($required, array_keys($columns)) !== []) {
            return 0;
        }

        $functionCriterion = $this->integerValue($sheet, $columns['function_criterion'], $row, 'Criteria Function');
        $productionImpact = $this->integerValue($sheet, $columns['production_impact'], $row, 'Criteria Production Impact');
        $leadTimeMonths = $this->decimalValue($sheet, $columns['lead_time_months'], $row, 'Lead Time (Month)');
        $priceCategory = $this->cellText($sheet, $columns['price_category'], $row);
        $averageYearlyUsage = $this->decimalValue($sheet, $columns['average_yearly_usage'], $row, 'Average Usage');
        $rawSla = $this->decimalValue($sheet, $columns['sla'], $row, 'SLA');
        $slaPercentage = $rawSla <= 1 ? $rawSla * 100 : $rawSla;
        $failureSafetyStock = $this->decimalValue($sheet, $columns['failure_safety_stock'], $row, 'Safety Stock Based on Failure');
        $sparepartIn = $this->quantity($sheet->getCell([$columns['sparepart_in'], $row])->getCalculatedValue(), $workbookName, $row, 'Sparepart IN');
        $sparepartOut = $this->quantity($sheet->getCell([$columns['sparepart_out'], $row])->getCalculatedValue(), $workbookName, $row, 'Sparepart OUT');
        $currentStock = max(0, $sparepartIn - $sparepartOut - (int) ceil($failureSafetyStock));
        $installedAt = $assetValues['tanggal_pemasangan'];
        $lifetimeYears = $this->nullableDecimalValue($sheet, $columns['lifetime_years'], $row, 'Lifetime (Years)');
        $vandalismCount = $this->integerValue($sheet, $columns['vandalism_count'], $row, 'Jumlah Vandalisme');
        $likelihood = $this->nullableIntegerValue($sheet, $columns['likelihood'], $row, 'Likelihood');
        $consequence = $this->nullableIntegerValue($sheet, $columns['consequence'], $row, 'Consequences');
        $calculation = $this->predictiveInventoryCalculator->calculate([
            'function_criterion' => $functionCriterion,
            'production_impact' => $productionImpact,
            'lead_time_months' => $leadTimeMonths,
            'price_category' => $priceCategory,
            'current_stock' => $currentStock,
            'total_assets' => (int) $assetValues['jumlah_unit'],
            'average_yearly_usage' => $averageYearlyUsage,
            'sla_percentage' => $slaPercentage,
            'failure_safety_stock' => $failureSafetyStock,
            'installed_at' => $installedAt ? CarbonImmutable::parse($installedAt) : null,
            'lifetime_years' => $lifetimeYears,
        ], now());
        $risk = $likelihood !== null && $consequence !== null
            ? $this->riskAssessmentCalculator->calculate($likelihood, $consequence)
            : ['rating' => null, 'level' => null];
        $sourceKey = hash('sha256', implode('|', [$workbookHash, self::SHEET, $row]));

        PredictiveAssetSnapshot::query()->updateOrCreate(
            ['source_key' => $sourceKey],
            [
                'asset_id' => $asset->id,
                'workbook_hash' => $workbookHash,
                'workbook_name' => $workbookName,
                'sheet_name' => self::SHEET,
                'source_row' => $row,
                'function_criterion' => $functionCriterion,
                'production_impact' => $productionImpact,
                'lead_time_months' => $leadTimeMonths,
                'price_category' => $priceCategory,
                'current_stock' => $currentStock,
                'total_assets' => (int) $assetValues['jumlah_unit'],
                'average_yearly_usage' => $averageYearlyUsage,
                'sla_percentage' => $slaPercentage,
                'failure_safety_stock' => $failureSafetyStock,
                'item_classification' => $this->nullableText($sheet, $columns['item_classification'], $row),
                'repairable' => $this->yesNoValue($sheet, $columns['repairable'], $row),
                'installed_at' => $installedAt,
                'lifetime_years' => $lifetimeYears,
                'vandalism_count' => $vandalismCount,
                'likelihood' => $likelihood,
                'consequence' => $consequence,
                ...$calculation,
                'risk_rating' => $risk['rating'],
                'risk_level' => $risk['level'],
                'calculated_at' => now(),
            ],
        );

        return 1;
    }

    private function lockAndRevalidateCategories(
        AssetGroup $group,
        AssetSystem $system,
        AssetSubsystem $subsystem,
        string $workbookName,
        int $row,
    ): void {
        $lockedGroup = AssetGroup::withTrashed()->lockForUpdate()->find($group->id);
        $lockedSystem = AssetSystem::withTrashed()->lockForUpdate()->find($system->id);
        $lockedSubsystem = AssetSubsystem::withTrashed()->lockForUpdate()->find($subsystem->id);

        if (
            ! $lockedGroup || $lockedGroup->trashed()
            || ! $lockedSystem || $lockedSystem->trashed() || $lockedSystem->asset_group_id !== $lockedGroup->id
            || ! $lockedSubsystem || $lockedSubsystem->trashed() || $lockedSubsystem->asset_system_id !== $lockedSystem->id
        ) {
            throw new RuntimeException(
                "Asset category resolution conflict in workbook {$workbookName}, sheet ".self::SHEET.", row {$row}.",
            );
        }
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array{created: int, updated: int, skipped: int, openings_created: int, openings_updated: int, predictive_snapshots: int}  $result
     */
    private function importOpening(
        Worksheet $sheet,
        array $columns,
        int $row,
        UnitKerja $unit,
        AssetSubsystem $subsystem,
        string $workbookName,
        array &$result,
    ): void {
        $sourceKey = $this->openingSourceKey($unit, $subsystem);
        $values = [
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $subsystem->id,
            'source_key' => $sourceKey,
            'sparepart_in' => $this->quantity(
                $sheet->getCell([$columns['sparepart_in'], $row])->getCalculatedValue(),
                $workbookName,
                $row,
                'Sparepart IN',
            ),
            'sparepart_out' => $this->quantity(
                $sheet->getCell([$columns['sparepart_out'], $row])->getCalculatedValue(),
                $workbookName,
                $row,
                'Sparepart OUT',
            ),
        ];

        $opening = UnitSubsystemOpening::query()
            ->where('unit_kerja_id', $unit->id)
            ->where('asset_subsystem_id', $subsystem->id)
            ->lockForUpdate()
            ->first();

        if (! $opening) {
            $opening = UnitSubsystemOpening::query()->create($values);
            $this->auditLogger->record(
                'unit_subsystem_opening.imported',
                $opening,
                [],
                $this->openingAuditValues($opening),
            );
            $result['openings_created']++;

            return;
        }

        $before = $this->openingAuditValues($opening);
        $opening->fill($values);
        if (! $opening->isDirty()) {
            return;
        }

        $opening->save();
        $this->auditLogger->record(
            'unit_subsystem_opening.imported',
            $opening,
            $before,
            $this->openingAuditValues($opening->refresh()),
        );
        $result['openings_updated']++;
    }

    /** @return array<string, int|string> */
    private function openingAuditValues(UnitSubsystemOpening $opening): array
    {
        return $opening->only([
            'unit_kerja_id',
            'asset_subsystem_id',
            'sparepart_in',
            'sparepart_out',
            'source_key',
        ]);
    }

    private function assetSourceKey(UnitKerja $unit, AssetSubsystem $subsystem): string
    {
        return hash(
            'sha256',
            'rams:master-asset:v2'
                .'|unit_id='.$unit->id
                .'|sheet='.self::SHEET
                .'|asset_subsystem_id='.$subsystem->id,
        );
    }

    private function openingSourceKey(UnitKerja $unit, AssetSubsystem $subsystem): string
    {
        return hash(
            'sha256',
            'rams:unit-subsystem-opening:v2'
                .'|unit_id='.$unit->id
                .'|sheet='.self::SHEET
                .'|asset_subsystem_id='.$subsystem->id,
        );
    }

    private function cellText(Worksheet $sheet, int $column, int $row): string
    {
        return $this->text($sheet->getCell([$column, $row])->getCalculatedValue());
    }

    private function decimalValue(Worksheet $sheet, int $column, int $row, string $header): float
    {
        $value = $this->cachedCellValue($sheet, $column, $row);
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new RuntimeException("Nilai {$header} tidak valid pada sheet ".self::SHEET.", row {$row}.");
        }

        return (float) $value;
    }

    private function nullableDecimalValue(Worksheet $sheet, int $column, int $row, string $header): ?float
    {
        $value = $this->cachedCellValue($sheet, $column, $row);
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->decimalValue($sheet, $column, $row, $header);
    }

    private function integerValue(Worksheet $sheet, int $column, int $row, string $header): int
    {
        $value = $this->decimalValue($sheet, $column, $row, $header);
        if (floor($value) !== $value || $value > 4294967295) {
            throw new RuntimeException("Nilai {$header} harus bilangan bulat pada sheet ".self::SHEET.", row {$row}.");
        }

        return (int) $value;
    }

    private function nullableIntegerValue(Worksheet $sheet, int $column, int $row, string $header): ?int
    {
        $value = $this->cachedCellValue($sheet, $column, $row);
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->integerValue($sheet, $column, $row, $header);
    }

    private function nullableText(Worksheet $sheet, int $column, int $row): ?string
    {
        $value = $this->text($this->cachedCellValue($sheet, $column, $row));

        return $value === '' ? null : $value;
    }

    private function yesNoValue(Worksheet $sheet, int $column, int $row): ?bool
    {
        $value = mb_strtoupper($this->text($this->cachedCellValue($sheet, $column, $row)));

        return match ($value) {
            '' => null,
            'Y', 'YES', 'YA' => true,
            'N', 'NO', 'TIDAK' => false,
            default => throw new RuntimeException(
                'Repairable harus Y/N pada sheet '.self::SHEET.", row {$row}.",
            ),
        };
    }

    private function cachedCellValue(Worksheet $sheet, int $column, int $row): mixed
    {
        $cell = $sheet->getCell([$column, $row]);

        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function legacyCellText(Worksheet $sheet, int $column, int $row): string
    {
        return trim((string) ($sheet->getCell([$column, $row])->getCalculatedValue() ?? ''));
    }

    private function text(mixed $value): string
    {
        $trimmed = preg_replace('/^\s+|\s+$/u', '', (string) ($value ?? '')) ?? trim((string) ($value ?? ''));

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    private function quantity(mixed $value, string $workbookName, int $row, string $header): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_string($value)) {
            $value = preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);
            if (in_array($value, ['', '-', '–'], true)) {
                return 0;
            }
        }

        if (! is_numeric($value)) {
            throw $this->invalidQuantity($workbookName, $row, $header, 'harus berupa bilangan bulat');
        }

        $quantity = (float) $value;
        if (! is_finite($quantity)) {
            throw $this->invalidQuantity($workbookName, $row, $header, 'harus berupa bilangan terbatas');
        }

        if ($quantity < 0) {
            throw $this->invalidQuantity($workbookName, $row, $header, 'tidak boleh negatif');
        }

        if (floor($quantity) !== $quantity) {
            throw $this->invalidQuantity($workbookName, $row, $header, 'tidak boleh memiliki pecahan');
        }

        if ($quantity > 4294967295) {
            throw $this->invalidQuantity($workbookName, $row, $header, 'melebihi batas unsigned integer');
        }

        return (int) $quantity;
    }

    private function invalidQuantity(
        string $workbookName,
        int $row,
        string $header,
        string $reason,
    ): RuntimeException {
        return new RuntimeException(
            "Nilai kuantitas tidak valid pada workbook {$workbookName}, sheet ".self::SHEET
                .", row {$row}, kolom {$header}: {$reason}.",
        );
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable $exception) {
                throw new RuntimeException('Nilai tanggal pemasangan tidak valid.', previous: $exception);
            }
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, trim((string) $value));

                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                // Try the next supported format.
            }
        }

        throw new RuntimeException('Tanggal pemasangan "'.trim((string) $value).'" tidak menggunakan format yang didukung.');
    }

    /** @return array<string, mixed> */
    private function auditValues(Asset $asset): array
    {
        return [
            ...$asset->only([
                'id',
                'unit_kerja_id',
                'nama_aset',
                'aset_prasarana_sintel',
                'system',
                'subsystem',
                'lokasi',
                'jumlah_unit',
                'source_key',
            ]),
            'tanggal_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
            'status' => $asset->status->value,
        ];
    }
}
