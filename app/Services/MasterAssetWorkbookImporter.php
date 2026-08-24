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

    /** @var array<string, array<string, string>> */
    private const KAI_SYSTEM_BY_SUBSYSTEM = [
        'peralatan dalam sinyal elektrik' => [
            'interlocking elektrik' => 'INTERLOCKING ELEKTRIK',
        ],
        'peralatan luar sinyal elektrik' => [
            'peraga sinyal elektrik utama' => 'PERAGA SINYAL ELEKTRIK',
            'peraga sinyal elektrik pembantu' => 'PERAGA SINYAL ELEKTRIK',
            'peraga sinyal elektrik pelengkap' => 'PERAGA SINYAL ELEKTRIK',
            'penggerak wesel elektrik' => 'PENGGERAK WESEL ELEKTRIK',
            'pengaman wesel setempat elektrik' => 'PENGAMAN WESEL SETEMPAT ELEKTRIK',
            'track ciruit' => 'DETEKSI SARANA PERKERETAAPIAN',
            'track circuit' => 'DETEKSI SARANA PERKERETAAPIAN',
            'axle counter' => 'DETEKSI SARANA PERKERETAAPIAN',
        ],
        'peralatan dalam sinyal mekanik' => [
            'interlocking mekanik' => 'INTERLOCKING MEKANIK',
        ],
        'peralatan luar sinyal mekanik' => [
            'peraga sinyal mekanik utama' => 'PERAGA SINYAL MEKANIK',
            'peraga sinyal mekanik pembantu' => 'PERAGA SINYAL MEKANIK',
            'peraga sinyal mekanik pelengkap' => 'PERAGA SINYAL MEKANIK',
            'penggerak wesel mekanik' => 'PENGGERAK WESEL MEKANIK',
            'pengontrol dan petunjuk kedudukan wesel mekanik' => 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK',
            'pengaman wesel setempat mekanik' => 'PENGAMAN WESEL SETEMPAT MEKANIK',
            'kontak deteksi' => 'PENDETEKSI SARANA PERKERETAAPIAN',
        ],
        'catu daya sintel' => [
            'catu daya sinyal' => 'CATU DAYA SINYAL',
        ],
    ];

    /** @var array<string, string> */
    private const EXCEL_OUTPUT_HEADERS = [
        'criticality' => 'criticality',
        'lead time period' => 'lead_time_category',
        'level inventory' => 'inventory_policy',
        'stock saat ini' => 'current_stock',
        'kebutuhan' => 'needed_stock',
        'proposal qty' => 'proposal_quantity',
        'status kategori qty' => 'proposal_reasonableness',
        'safety stok based usage' => 'safety_stock_usage',
        'safety stock based on mca' => 'safety_stock_mca',
        'safety stock' => 'final_safety_stock',
        'umur peralatan (tahun)' => 'age_years',
        'lifetime' => 'age_condition',
        'rating' => 'risk_rating',
        'desc' => 'risk_level',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AssetCategoryResolver $categoryResolver,
        private readonly AssetTaxonomyService $assetTaxonomy,
        private readonly PredictiveInventoryCalculator $predictiveInventoryCalculator,
        private readonly RiskAssessmentCalculator $riskAssessmentCalculator,
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
     *     openings_created: int,
     *     openings_updated: int,
     *     predictive_snapshots: int
     * }
     */
    public function import(string $workbookPath, UnitKerja $unit): array
    {
        if (! is_file($workbookPath)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$workbookPath}");
        }

        $reader = IOFactory::createReaderForFile($workbookPath);
        $reader->setReadDataOnly(false);
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

            return DB::transaction(
                fn (): array => $this->importRows($sheet, $columns, $unit, basename($workbookPath), $workbookHash),
            );
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

            $headers = [...self::REQUIRED_HEADERS, ...self::OPTIONAL_PREDICTIVE_HEADERS, ...self::EXCEL_OUTPUT_HEADERS];
            if (isset($headers[$key])) {
                $columns[$headers[$key]] = $column;
            }
        }

        $missing = array_diff(array_values(self::REQUIRED_HEADERS), array_keys($columns));

        if ($missing !== []) {
            throw new RuntimeException(
                'Header workbook tidak valid. Kolom wajib yang hilang: '.implode(', ', $missing).'.',
            );
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     * @return array{
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     duplicates_skipped: int,
     *     duplicate_locations: list<string>,
     *     skipped: int,
     *     openings_created: int,
     *     openings_updated: int,
     *     predictive_snapshots: int
     * }
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
            'unchanged' => 0,
            'duplicates_skipped' => 0,
            'duplicate_locations' => [],
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

            $resolvedSystem = $this->kaiSystemForSubsystem($currentGroup, $currentSystem, $subsystem);
            $this->rehomeExistingKaiSubsystem(
                $unit,
                $currentGroup,
                $currentSystem,
                $resolvedSystem,
                $subsystem,
            );

            $categories = $this->categoryResolver->resolve(
                $currentGroup,
                $resolvedSystem,
                $subsystem,
                $workbookName,
                self::SHEET,
                $row,
                $unit->id,
            );
            $this->retireEmptyLegacyKaiPath($categories, $currentSystem, $resolvedSystem, $subsystem);
            $this->inheritKaiDashboardColor($categories, $currentSystem, $resolvedSystem);
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
                    "Duplikat subsistem kanonis pada workbook {$workbookName}, sheet ".
                        self::SHEET.
                        ", row {$firstRow} dan row {$row}, path {$path}.",
                );
            }
            $resolvedRowsBySubsystem[$subsystemId] = $row;

            $sourceKey = $this->assetSourceKey($unit, $categories['subsystem']);
            $previousStableSourceKey = hash(
                'sha256',
                implode('|', [$unit->code, self::SHEET, $categories['subsystem']->id]),
            );
            $legacySourceKey = hash(
                'sha256',
                implode('|', [$unit->code, self::SHEET, $legacyCurrentSystem, $legacySubsystem]),
            );
            $sourceValues = [
                'unit_kerja_id' => $unit->getKey(),
                'asset_subsystem_id' => $categories['subsystem']->id,
                'aset_prasarana_sintel' => $currentGroup,
                'system' => $resolvedSystem,
                'subsystem' => $subsystem,
                'jumlah_unit' => $this->quantity(
                    $sheet->getCell([$columns['total'], $row])->getCalculatedValue(),
                    $workbookName,
                    $row,
                    'TOTAL',
                ),
                'tanggal_pemasangan' => $this->date(
                    $sheet->getCell([$columns['installed_at'], $row])->getCalculatedValue(),
                ),
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

            if ($matchingAssets->isEmpty()) {
                $normalizedSubsystem = $this->categoryResolver->normalize($subsystem);
                $matchingAssets = Asset::withTrashed()
                    ->where('unit_kerja_id', $unit->id)
                    ->whereNotNull('source_key')
                    ->with('assetSubsystem')
                    ->lockForUpdate()
                    ->get()
                    ->filter(function (Asset $candidate) use ($normalizedSubsystem): bool {
                        return $this->categoryResolver->normalize($candidate->subsystem) === $normalizedSubsystem ||
                            $this->categoryResolver->normalize($candidate->assetSubsystem->name) ===
                                $normalizedSubsystem;
                    })
                    ->values();
            }

            if ($matchingAssets->count() > 1) {
                throw new RuntimeException(
                    "Konflik kandidat aset impor pada workbook {$workbookName}, sheet ".
                        self::SHEET.
                        ", row {$row}, path {$currentGroup}|{$resolvedSystem}|{$subsystem}: ".
                        $matchingAssets->count().
                        ' kandidat ditemukan.',
                );
            }

            /** @var Asset|null $asset */
            $asset = $matchingAssets->first();
            $previousSubsystemId = $asset?->asset_subsystem_id;

            if ($asset?->trashed()) {
                $result['skipped']++;
                $result['duplicates_skipped']++;
                $result['duplicate_locations'][] = self::SHEET.'!'.$row;

                continue;
            }

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
                } else {
                    $result['unchanged']++;
                    $result['duplicates_skipped']++;
                    $result['duplicate_locations'][] = self::SHEET.'!'.$row;
                }
            } else {
                $asset = Asset::query()->create([
                    ...$sourceValues,
                    'nama_aset' => $subsystem,
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
                $previousSubsystemId,
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

    private function kaiSystemForSubsystem(string $group, string $system, string $subsystem): string
    {
        $normalizedGroup = $this->categoryResolver->normalize($group);
        $normalizedSubsystem = $this->categoryResolver->normalize($subsystem);

        foreach (self::KAI_SYSTEM_BY_SUBSYSTEM as $groupName => $systems) {
            if (str_contains($normalizedGroup, $groupName) && isset($systems[$normalizedSubsystem])) {
                return $systems[$normalizedSubsystem];
            }
        }

        return $system;
    }

    private function rehomeExistingKaiSubsystem(
        UnitKerja $unit,
        string $groupName,
        string $sourceSystemName,
        string $resolvedSystemName,
        string $subsystemName,
    ): void {
        if ($this->categoryResolver->normalize($sourceSystemName) === $this->categoryResolver->normalize($resolvedSystemName)) {
            return;
        }

        $group = AssetGroup::query()
            ->where('unit_kerja_id', $unit->id)
            ->where('normalized_name', $this->categoryResolver->normalize($groupName))
            ->lockForUpdate()
            ->first();
        if (! $group) {
            return;
        }

        $sourceSystem = AssetSystem::query()
            ->where('asset_group_id', $group->id)
            ->where('normalized_name', $this->categoryResolver->normalize($sourceSystemName))
            ->lockForUpdate()
            ->first();
        $sourceSubsystem = $sourceSystem?->subsystems()
            ->where('normalized_name', $this->categoryResolver->normalize($subsystemName))
            ->lockForUpdate()
            ->first();
        if (! $sourceSystem || ! $sourceSubsystem) {
            return;
        }

        $targetSystem = AssetSystem::query()->firstOrCreate(
            [
                'asset_group_id' => $group->id,
                'normalized_name' => $this->categoryResolver->normalize($resolvedSystemName),
            ],
            [
                'name' => $resolvedSystemName,
                'sort_order' => $sourceSystem->sort_order,
                'dashboard_color' => $sourceSystem->dashboard_color ?? $group->dashboard_color,
                'dashboard_color_source' => $sourceSystem->dashboard_color_source ?? $group->dashboard_color_source,
                'is_active' => true,
            ],
        );

        $duplicate = $targetSystem->subsystems()
            ->where('normalized_name', $this->categoryResolver->normalize($subsystemName))
            ->lockForUpdate()
            ->first();
        if ($duplicate && $duplicate->id !== $sourceSubsystem->id) {
            return;
        }

        $sourceSubsystem->asset_system_id = $targetSystem->id;
        $sourceSubsystem->save();
        $this->assetTaxonomy->syncLegacyPath($group, $targetSystem, $sourceSubsystem);
    }

    /** @param array{group: AssetGroup, system: AssetSystem, subsystem: AssetSubsystem} $categories */
    private function retireEmptyLegacyKaiPath(
        array $categories,
        string $sourceSystemName,
        string $resolvedSystemName,
        string $subsystemName,
    ): void {
        if ($this->categoryResolver->normalize($sourceSystemName) === $this->categoryResolver->normalize($resolvedSystemName)) {
            return;
        }

        $sourceSystem = AssetSystem::query()
            ->where('asset_group_id', $categories['group']->id)
            ->where('normalized_name', $this->categoryResolver->normalize($sourceSystemName))
            ->lockForUpdate()
            ->first();
        $sourceSubsystem = $sourceSystem?->subsystems()
            ->where('normalized_name', $this->categoryResolver->normalize($subsystemName))
            ->lockForUpdate()
            ->first();

        if ($sourceSubsystem && $sourceSubsystem->id !== $categories['subsystem']->id) {
            $node = $this->assetTaxonomy->nodeForLegacy('subsystem', $sourceSubsystem->id);
            $unused = ! $sourceSubsystem->assets()->withTrashed()->exists()
                && ! $sourceSubsystem->openings()->exists()
                && ! $sourceSubsystem->spareParts()->exists()
                && ! $node?->children()->exists();

            if ($unused) {
                $sourceSubsystem->delete();
                $node?->delete();
            }
        }

        if ($sourceSystem && $sourceSystem->subsystems()->doesntExist()) {
            $node = $this->assetTaxonomy->nodeForLegacy('system', $sourceSystem->id);
            if (! $node?->children()->exists()) {
                $sourceSystem->delete();
                $node?->delete();
            }
        }
    }

    /** @param array{group: AssetGroup, system: AssetSystem, subsystem: AssetSubsystem} $categories */
    private function inheritKaiDashboardColor(array $categories, string $sourceSystemName, string $resolvedSystemName): void
    {
        if ($this->categoryResolver->normalize($sourceSystemName) === $this->categoryResolver->normalize($resolvedSystemName)) {
            return;
        }

        $color = $categories['group']->dashboard_color;
        if (! $color) {
            return;
        }

        foreach ([$categories['system'], $categories['subsystem']] as $category) {
            if (! $category->dashboard_color) {
                $category->forceFill([
                    'dashboard_color' => $color,
                    'dashboard_color_source' => $categories['group']->dashboard_color_source,
                ])->save();
            }
        }

        $this->assetTaxonomy->syncLegacyPath($categories['group'], $categories['system'], $categories['subsystem']);
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
        $productionImpact = $this->integerValue(
            $sheet,
            $columns['production_impact'],
            $row,
            'Criteria Production Impact',
        );
        $leadTimeMonths = $this->decimalValue($sheet, $columns['lead_time_months'], $row, 'Lead Time (Month)');
        $priceCategory = $this->cellText($sheet, $columns['price_category'], $row);
        $averageYearlyUsage = $this->decimalValue($sheet, $columns['average_yearly_usage'], $row, 'Average Usage');
        $rawSla = $this->decimalValue($sheet, $columns['sla'], $row, 'SLA');
        $slaPercentage = $rawSla <= 1 ? $rawSla * 100 : $rawSla;
        $failureSafetyStock = $this->decimalValue(
            $sheet,
            $columns['failure_safety_stock'],
            $row,
            'Safety Stock Based on Failure',
        );
        $sparepartIn = $this->quantity(
            $this->cachedCellValue($sheet, $columns['sparepart_in'], $row),
            $workbookName,
            $row,
            'Sparepart IN',
        );
        $sparepartOut = $this->quantity(
            $this->cachedCellValue($sheet, $columns['sparepart_out'], $row),
            $workbookName,
            $row,
            'Sparepart OUT',
        );
        $currentStock = $sparepartIn - $sparepartOut - (int) ceil($failureSafetyStock);
        $installedAt = $assetValues['tanggal_pemasangan'];
        $lifetimeYears = $this->nullableDecimalValue($sheet, $columns['lifetime_years'], $row, 'Lifetime (Years)');
        $vandalismCount = $this->integerValue($sheet, $columns['vandalism_count'], $row, 'Jumlah Vandalisme');
        $likelihood = $this->nullableIntegerValue($sheet, $columns['likelihood'], $row, 'Likelihood');
        $consequence = $this->nullableIntegerValue($sheet, $columns['consequence'], $row, 'Consequences');
        $calculation = $this->predictiveInventoryCalculator->calculate(
            [
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
            ],
            now(),
        );
        $risk =
            $likelihood !== null && $consequence !== null
                ? $this->riskAssessmentCalculator->calculate($likelihood, $consequence)
                : ['rating' => null, 'level' => null];
        $backendValues = [
            'criticality' => $calculation['criticality'],
            'lead_time_category' => $calculation['lead_time_category'],
            'inventory_policy' => $calculation['inventory_policy'],
            'current_stock' => $currentStock,
            'needed_stock' => $calculation['needed_stock'],
            'proposal_quantity' => $calculation['proposal_quantity'],
            'proposal_reasonableness' => $calculation['proposal_reasonableness'],
            'safety_stock_usage' => $calculation['safety_stock_usage'],
            'safety_stock_mca' => $calculation['safety_stock_mca'],
            'safety_stock_failure' => $calculation['safety_stock_failure'],
            'final_safety_stock' => $calculation['final_safety_stock'],
            'age_years' => $calculation['age_years'],
            'age_condition' => $calculation['age_condition'],
            'risk_rating' => $risk['rating'],
            'risk_level' => $risk['level'],
        ];
        [$excelValues, $excelFormulas] = $this->excelAuditValues($sheet, $columns, $row);
        $parityDifferences = $this->parityDifferences($excelValues, $backendValues);
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
                'excel_values' => $excelValues,
                'excel_formulas' => $excelFormulas,
                'parity_status' => $excelValues === []
                    ? 'not_compared'
                    : ($parityDifferences === [] ? 'matched' : 'corrected'),
                'parity_differences' => $parityDifferences === [] ? null : $parityDifferences,
                'calculated_at' => now(),
            ],
        );

        return 1;
    }

    /** @param array<string, int> $columns
     * @return array{array<string, mixed>, array<string, string>}
     */
    private function excelAuditValues(Worksheet $sheet, array $columns, int $row): array
    {
        $values = [];
        $formulas = [];
        foreach (array_values(self::EXCEL_OUTPUT_HEADERS) as $key) {
            if (! isset($columns[$key])) {
                continue;
            }
            $cell = $sheet->getCell([$columns[$key], $row]);
            $value = $this->cachedCellValue($sheet, $columns[$key], $row);
            if ($value !== null && $value !== '') {
                $values[$key] = $value;
            }
            if ($cell->isFormula()) {
                $formulas[$key] = (string) $cell->getValue();
            }
        }

        if (isset($columns['failure_safety_stock'])) {
            $cell = $sheet->getCell([$columns['failure_safety_stock'], $row]);
            $values['safety_stock_failure'] = $this->cachedCellValue($sheet, $columns['failure_safety_stock'], $row);
            if ($cell->isFormula()) {
                $formulas['safety_stock_failure'] = (string) $cell->getValue();
            }
        }

        return [$values, $formulas];
    }

    /** @param array<string, mixed> $excelValues
     * @param  array<string, mixed>  $backendValues
     * @return array<string, array{excel: mixed, backend: mixed}>
     */
    private function parityDifferences(array $excelValues, array $backendValues): array
    {
        $differences = [];
        foreach ($excelValues as $key => $excelValue) {
            if (! array_key_exists($key, $backendValues)) {
                continue;
            }
            $backendValue = $backendValues[$key];
            $matches =
                is_numeric($excelValue) && is_numeric($backendValue)
                    ? abs((float) $excelValue - (float) $backendValue) < 0.0001
                    : mb_strtolower(trim((string) $excelValue)) === mb_strtolower(trim((string) $backendValue));
            if (! $matches) {
                $differences[$key] = ['excel' => $excelValue, 'backend' => $backendValue];
            }
        }

        return $differences;
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
            ! $lockedGroup ||
            $lockedGroup->trashed() ||
            ! $lockedSystem ||
            $lockedSystem->trashed() ||
            $lockedSystem->asset_group_id !== $lockedGroup->id ||
            ! $lockedSubsystem ||
            $lockedSubsystem->trashed() ||
            $lockedSubsystem->asset_system_id !== $lockedSystem->id
        ) {
            throw new RuntimeException(
                "Asset category resolution conflict in workbook {$workbookName}, sheet ".
                    self::SHEET.
                    ", row {$row}.",
            );
        }
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     openings_created: int,
     *     openings_updated: int,
     *     predictive_snapshots: int
     * }  $result
     */
    private function importOpening(
        Worksheet $sheet,
        array $columns,
        int $row,
        UnitKerja $unit,
        AssetSubsystem $subsystem,
        string $workbookName,
        array &$result,
        ?int $previousSubsystemId = null,
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

        if (! $opening && $previousSubsystemId && $previousSubsystemId !== $subsystem->id) {
            $opening = UnitSubsystemOpening::query()
                ->where('unit_kerja_id', $unit->id)
                ->where('asset_subsystem_id', $previousSubsystemId)
                ->lockForUpdate()
                ->first();
        }

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
        return $opening->only(['unit_kerja_id', 'asset_subsystem_id', 'sparepart_in', 'sparepart_out', 'source_key']);
    }

    private function assetSourceKey(UnitKerja $unit, AssetSubsystem $subsystem): string
    {
        return hash(
            'sha256',
            'rams:master-asset:v2'.
                '|unit_id='.
                $unit->id.
                '|sheet='.
                self::SHEET.
                '|asset_subsystem_id='.
                $subsystem->id,
        );
    }

    private function openingSourceKey(UnitKerja $unit, AssetSubsystem $subsystem): string
    {
        return hash(
            'sha256',
            'rams:unit-subsystem-opening:v2'.
                '|unit_id='.
                $unit->id.
                '|sheet='.
                self::SHEET.
                '|asset_subsystem_id='.
                $subsystem->id,
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
            throw new RuntimeException(
                "Nilai {$header} harus bilangan bulat pada sheet ".self::SHEET.", row {$row}.",
            );
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
            default => throw new RuntimeException('Repairable harus Y/N pada sheet '.self::SHEET.", row {$row}."),
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

        return preg_replace("/\s+/u", ' ', $trimmed) ?? $trimmed;
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

    private function invalidQuantity(string $workbookName, int $row, string $header, string $reason): RuntimeException
    {
        return new RuntimeException(
            "Nilai kuantitas tidak valid pada workbook {$workbookName}, sheet ".
                self::SHEET.
                ", row {$row}, kolom {$header}: {$reason}.",
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

        throw new RuntimeException(
            'Tanggal pemasangan "'.trim((string) $value).'" tidak menggunakan format yang didukung.',
        );
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
                'jumlah_unit',
                'source_key',
            ]),
            'tanggal_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
            'status' => $asset->status->value,
        ];
    }
}
