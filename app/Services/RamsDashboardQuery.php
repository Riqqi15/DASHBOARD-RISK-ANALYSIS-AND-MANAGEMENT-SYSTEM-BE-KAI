<?php

namespace App\Services;

use App\Enums\RiskRegisterStatus;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\PredictiveAssetSnapshot;
use App\Models\RamsImportBatch;
use App\Models\RamsImportChange;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\ReliabilitySummary;
use App\Models\RiskMatrix;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RamsDashboardQuery
{
    /** @return array<string, mixed> */
    public function dashboard(User $user, ?UnitKerja $unit): array
    {
        $dashboardAssets = $this->dashboardAssetModels($user, $unit);

        return [
            'selected_area' => $unit?->code,
            'units' => $user->isPusat() ? $this->units() : [],
            'summary' => $this->summary($user, $unit),
            'assets' => $dashboardAssets->map(fn (Asset $asset): array => $this->assetPayload($asset))->all(),
            'asset_categories' => $this->assetCategories($dashboardAssets, $unit),
        ];
    }

    /** @return array<string, mixed> */
    public function overview(User $user, ?UnitKerja $unit): array
    {
        return [
            'selected_area' => $unit?->code,
            'units' => $user->isPusat() ? $this->units() : [],
            'summary' => $this->summary($user, $unit),
            'assets' => $this->assets($user, $unit),
            'risk_registers' => $this->riskRegisters($user, $unit),
            'failure_trend' => $this->failureTrend($user, $unit),
        ];
    }

    /** @return array<string, mixed> */
    public function riskMatrix(User $user, ?UnitKerja $unit): array
    {
        return [
            'selected_area' => $unit?->code,
            'units' => $user->isPusat() ? $this->units() : [],
            'risks' => RiskMatrix::query()
                ->visibleTo($user)
                ->when(
                    $unit,
                    fn (Builder $query): Builder => $query->whereHas(
                        'asset',
                        fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                    ),
                    fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                )
                ->with('asset.assetSubsystem.assetSystem.assetGroup')
                ->get()
                ->map(
                    fn (RiskMatrix $risk): array => [
                        'id' => $risk->id,
                        'asset_id' => $risk->asset_id,
                        'system' => $risk->asset->assetSubsystem->assetSystem->name,
                        'subsystem' => $risk->asset->assetSubsystem->name,
                        'likelihood' => $risk->likelihood,
                        'consequence' => $risk->consequence,
                        'rating' => $risk->rating,
                        'level' => $risk->level,
                        'last_update' => $risk->assessed_at?->toDateString() ?? $risk->updated_at->toDateString(),
                    ],
                )
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function inventory(User $user, ?UnitKerja $unit): array
    {
        $stocks = InventoryStock::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->where('unit_kerja_id', $unit->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->whereHas('sparePart', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with([
                'unitKerja:id,code,name',
                'sparePart.assetSubsystem:id,asset_system_id,name',
                'sparePart.unitPolicies',
            ])
            ->orderBy('unit_kerja_id')
            ->orderBy('spare_part_id')
            ->get();

        return [
            'selected_area' => $unit?->code,
            'units' => $user->isPusat() ? $this->units() : [],
            'items' => $stocks->map(fn (InventoryStock $stock): array => $this->stockPayload($stock))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function troubleReport(User $user, ?UnitKerja $unit, string $subsystem): array
    {
        $assets = $this->assetQuery($user, $unit)
            ->whereHas(
                'assetSubsystem',
                fn (Builder $query): Builder => $query->whereRaw('LOWER(name) = ?', [mb_strtolower($subsystem)]),
            )
            ->with('assetSubsystem')
            ->get();
        $assetIds = $assets->pluck('id');

        $summaries = ReliabilitySummary::query()
            ->visibleTo($user)
            ->whereIn('asset_id', $assetIds)
            ->latest('period')
            ->get();

        $allLogs = FailureLog::query()
            ->visibleTo($user)
            ->whereIn('asset_id', $assetIds)
            ->orderByRaw('CASE WHEN source_row IS NULL THEN 1 ELSE 0 END')
            ->orderBy('source_row')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        $logsWithInterval = [];
        $groupedLogs = $allLogs->groupBy('asset_id');

        foreach ($groupedLogs as $assetId => $logs) {
            $summary = $summaries->firstWhere('asset_id', $assetId);
            $asset = $assets->firstWhere('id', $assetId);
            $baseline = null;
            $intervalBaseline = $summary?->calculation_profile['interval_baseline_date'] ?? null;
            if ($intervalBaseline) {
                $baseline = CarbonImmutable::parse((string) $intervalBaseline)->startOfDay();
            } elseif ($summary && $summary->baseline_date) {
                $baseline = CarbonImmutable::instance($summary->baseline_date);
            } elseif ($asset && $asset->tanggal_pemasangan) {
                $baseline = CarbonImmutable::parse($asset->tanggal_pemasangan);
            }

            $previousStart = $baseline;
            foreach ($logs as $log) {
                if ($previousStart !== null) {
                    $log->interval_jam = $previousStart->diffInMinutes($log->started_at, false) / 60;
                } else {
                    $log->interval_jam = null;
                }
                $previousStart = CarbonImmutable::instance($log->started_at);
                $logsWithInterval[] = $log;
            }
        }

        return [
            'selected_area' => $unit?->code,
            'subsystem' => $subsystem,
            'assets' => $assets->map(fn (Asset $asset): array => $this->assetPayload($asset))->all(),
            'reliability' => $summaries
                ->map(fn (ReliabilitySummary $summary): array => $this->reliabilityPayload($summary))
                ->all(),
            'failure_logs' => collect($logsWithInterval)
                ->map(fn (FailureLog $log): array => $this->failurePayload($log))
                ->all(),
            'spare_parts' => collect($this->inventory($user, $unit)['items'])
                ->filter(fn (array $item): bool => mb_strtolower($item['subsystem']) === mb_strtolower($subsystem))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(User $user, ?UnitKerja $unit): array
    {
        $assets = $this->assetQuery($user, $unit);
        $assetIds = (clone $assets)->pluck('id');
        $risks = RiskMatrix::query()->whereIn('asset_id', $assetIds)->get();
        $latestReliability = ReliabilitySummary::query()
            ->whereIn('asset_id', $assetIds)
            ->with(['asset.assetSubsystem.assetSystem.assetGroup', 'asset.assetCategoryNode'])
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id')
            ->filter(fn (ReliabilitySummary $summary): bool => $summary->calculation_status === 'calculated');
        $validReliability = $latestReliability->filter(
            fn (ReliabilitySummary $summary): bool => $summary->reliability !== null,
        );
        $validAvailability = $latestReliability->filter(
            fn (ReliabilitySummary $summary): bool => $summary->availability !== null,
        );
        $overallReliability = $validReliability->isEmpty()
            ? null
            : $validReliability->reduce(
                fn (float $product, ReliabilitySummary $summary): float => $product * (float) $summary->reliability,
                1.0,
            );
        $overallAvailability = $validAvailability->isEmpty()
            ? null
            : $validAvailability->reduce(
                fn (float $product, ReliabilitySummary $summary): float => $product * (float) $summary->availability,
                1.0,
            );
        $importedBaselineDate = ReliabilityExcelSnapshot::query()
            ->whereIn('asset_id', $assetIds)
            ->whereNotNull('baseline_date')
            ->latest('imported_at')
            ->latest('id')
            ->value('baseline_date');
        $operatingStartDate = $unit?->operating_start_date?->toDateString() ?? $importedBaselineDate;
        $latestPredictive = PredictiveAssetSnapshot::query()
            ->whereIn('asset_id', $assetIds)
            ->orderByDesc('calculated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id');

        return [
            'totalAset' => (clone $assets)->count(),
            'totalUnit' => (int) (clone $assets)->sum('jumlah_unit'),
            'risikoExtreme' => $risks->filter(fn (RiskMatrix $risk): bool => $risk->level === 'Extreme')->count(),
            'risikoHigh' => $risks->filter(fn (RiskMatrix $risk): bool => $risk->level === 'High')->count(),
            'avgAvailability' => $overallAvailability ?? 0.0,
            'overallReliability' => $overallReliability,
            'overallAvailability' => $overallAvailability,
            'operatingDays' => $operatingStartDate
                ? (int) CarbonImmutable::parse($operatingStartDate)
                    ->startOfDay()
                    ->diffInDays(now()->startOfDay())
                : null,
            'reliabilityGroups' => $this->reliabilityGroups($latestReliability, $assetIds),
            'latestImport' => $this->latestImport($unit),
            'totalFailure' => FailureLog::query()->whereIn('asset_id', $assetIds)->count(),
            'totalProposalReorder' => $latestPredictive
                ->filter(fn (PredictiveAssetSnapshot $snapshot): bool => $snapshot->proposal_quantity > 0)
                ->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function assets(User $user, ?UnitKerja $unit): array
    {
        return $this->dashboardAssetModels($user, $unit)
            ->map(fn (Asset $asset): array => $this->assetPayload($asset))
            ->all();
    }

    /** @return Collection<int, Asset> */
    private function dashboardAssetModels(User $user, ?UnitKerja $unit): Collection
    {
        return $this->assetQuery($user, $unit)
            ->with([
                'unitKerja:id,code,name',
                'assetSubsystem.assetSystem.assetGroup',
                'assetCategoryNode',
                'latestPredictiveAssetSnapshot',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * Build the category tree from the selected area's assets plus empty categories
     * that are still being prepared in the master taxonomy.
     *
     * Categories with assets only in another area stay hidden from the current
     * area, but newly-created empty categories appear immediately.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function assetCategories(Collection $assets, ?UnitKerja $unit): array
    {
        $subsystemIds = $assets->pluck('asset_subsystem_id')->filter()->unique()->values();

        return AssetGroup::query()
            ->when($unit, fn (Builder $query): Builder => $query->forUnit($unit))
            ->with([
                'systems.subsystems' => fn ($query) => $query->withCount([
                    'assets' => fn (Builder $assets): Builder => $assets->when(
                        $unit,
                        fn (Builder $scoped): Builder => $scoped->where('unit_kerja_id', $unit->id),
                        fn (Builder $scoped): Builder => $scoped->whereRaw('1 = 0'),
                    ),
                ]),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (AssetGroup $group) use ($subsystemIds): ?array {
                $systems = $group->systems
                    ->map(function (AssetSystem $system) use ($subsystemIds): ?array {
                        $subsystems = $system->subsystems
                            ->filter(
                                fn (AssetSubsystem $subsystem): bool => $subsystemIds->contains($subsystem->id) ||
                                    (int) ($subsystem->assets_count ?? 0) === 0,
                            )
                            ->map(
                                fn (AssetSubsystem $subsystem): array => [
                                    'id' => $subsystem->id,
                                    'name' => $subsystem->name,
                                    'dashboard_color' => $subsystem->dashboard_color,
                                    'dashboard_color_source' => $subsystem->dashboard_color_source,
                                    'is_active' => $subsystem->is_active,
                                ],
                            )
                            ->values();

                        if ($system->subsystems->isNotEmpty() && $subsystems->isEmpty()) {
                            return null;
                        }

                        return [
                            'id' => $system->id,
                            'name' => $system->name,
                            'dashboard_color' => $system->dashboard_color,
                            'dashboard_color_source' => $system->dashboard_color_source,
                            'is_active' => $system->is_active,
                            'subsystems' => $subsystems->all(),
                        ];
                    })
                    ->filter()
                    ->values();

                if ($group->systems->isNotEmpty() && $systems->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sort_order' => $group->sort_order,
                    'dashboard_color' => $group->dashboard_color,
                    'dashboard_color_source' => $group->dashboard_color_source,
                    'is_active' => $group->is_active,
                    'systems' => $systems->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function riskRegisters(User $user, ?UnitKerja $unit): array
    {
        return RiskRegister::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->whereHas(
                    'asset',
                    fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                ),
            )
            ->with('asset:id')
            ->latest('updated_at')
            ->get()
            ->map(
                fn (RiskRegister $register): array => [
                    'id' => $register->id,
                    'aset_id' => $register->asset_id,
                    'part_number' => $register->part_number,
                    'peristiwa_risiko' => $register->risk_event,
                    'penyebab' => $register->risk_cause,
                    'rekomendasi' => $register->recommendation,
                    'status' => match ($register->status) {
                        RiskRegisterStatus::Open => 'Open',
                        RiskRegisterStatus::InProgress => 'In Progress',
                        RiskRegisterStatus::Closed => 'Closed',
                    },
                ],
            )
            ->all();
    }

    /** @return array<int, array{period: string, count: int}> */
    private function failureTrend(User $user, ?UnitKerja $unit): array
    {
        return FailureLog::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->whereHas(
                    'asset',
                    fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unit->id),
                ),
            )
            ->selectRaw("DATE_FORMAT(started_at, '%Y-%m') as period, COUNT(*) as aggregate")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(
                fn (FailureLog $row): array => [
                    'period' => (string) $row->getAttribute('period'),
                    'count' => (int) $row->getAttribute('aggregate'),
                ],
            )
            ->all();
    }

    private function assetQuery(User $user, ?UnitKerja $unit): Builder
    {
        return Asset::query()
            ->visibleTo($user)
            ->when(
                $unit,
                fn (Builder $query): Builder => $query->where('unit_kerja_id', $unit->id),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    /** @return array<string, mixed> */
    private function assetPayload(Asset $asset): array
    {
        $asset->loadMissing([
            'unitKerja:id,code,name',
            'assetSubsystem.assetSystem.assetGroup',
            'assetCategoryNode',
            'latestPredictiveAssetSnapshot',
        ]);

        $subsystem = $asset->assetSubsystem;
        $system = $subsystem?->assetSystem;
        $group = $system?->assetGroup;

        return [
            'id' => $asset->id,
            'nama_aset' => $asset->nama_aset,
            'unit_kerja_id' => $this->frontendUnitCode($asset->unitKerja->code),
            'aset_prasarana_sintel' => $group?->name ?: $asset->aset_prasarana_sintel,
            'system' => $system?->name ?: $asset->system,
            'subsystem' => $subsystem?->name ?: $asset->subsystem,
            'jumlah_unit' => $asset->jumlah_unit,
            'tahun_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
            'status' => $asset->status->label(),
            'predictive' => $asset->latestPredictiveAssetSnapshot
                ? $this->predictivePayload($asset->latestPredictiveAssetSnapshot)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function predictivePayload(PredictiveAssetSnapshot $snapshot): array
    {
        return [
            'criticality' => $snapshot->criticality,
            'lead_time_category' => $snapshot->lead_time_category,
            'inventory_policy' => $snapshot->inventory_policy,
            'current_stock' => $snapshot->current_stock,
            'needed_stock' => $snapshot->needed_stock,
            'proposal_quantity' => $snapshot->proposal_quantity,
            'proposal_reasonableness' => $snapshot->proposal_reasonableness,
            'final_safety_stock' => $snapshot->final_safety_stock,
            'age_years' => $snapshot->age_years === null ? null : (float) $snapshot->age_years,
            'age_condition' => $snapshot->age_condition,
            'lifetime_status' => $snapshot->lifetime_status,
            'risk_rating' => $snapshot->risk_rating,
            'risk_level' => $snapshot->risk_level,
            'calculation_status' => $snapshot->calculation_status,
            'formula_version' => $snapshot->formula_version,
            'calculated_at' => $snapshot->calculated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function stockPayload(InventoryStock $stock): array
    {
        $policy = $stock->sparePart->unitPolicies->firstWhere('unit_kerja_id', $stock->unit_kerja_id);

        return [
            'id' => $stock->id,
            'unit_kerja_id' => $stock->unit_kerja_id,
            'unit_code' => $stock->unitKerja->code,
            'spare_part_id' => $stock->spare_part_id,
            'code' => $stock->sparePart->code,
            'name' => $stock->sparePart->detail_equipment,
            'category' => $stock->sparePart->equipment,
            'subsystem' => $stock->sparePart->assetSubsystem->name,
            'quantity' => $stock->quantity,
            'safety_stock' => $policy?->safety_stock ?? ($stock->sparePart->safety_stock ?? 0),
            'reorder_point' => $policy?->reorder_point ?? ($stock->sparePart->reorder_point ?? 0),
            'predicted_need' => $policy?->lead_time_demand ?? ($stock->sparePart->lead_time_demand ?? 0),
            'calculation_status' => $policy?->calculation_status ?? $stock->sparePart->reorder_calculation_status,
            'formula_version' => $policy?->formula_version ?? $stock->sparePart->reorder_formula_version,
        ];
    }

    /** @return array<string, mixed> */
    private function reliabilityPayload(ReliabilitySummary $summary): array
    {
        return [
            'id' => $summary->id,
            'aset_id' => $summary->asset_id,
            'periode' => $summary->period->format('Y-m'),
            'jumlah_unit' => $summary->unit_count,
            'total_operating_hour' => $summary->operating_hours === null
                    ? $summary->operating_minutes / 60
                    : (float) $summary->operating_hours,
            'total_downtime' => $summary->downtime_value === null
                    ? $summary->downtime_minutes / 60
                    : (float) $summary->downtime_value,
            'total_uptime' => $summary->uptime_hours === null
                    ? ($summary->operating_minutes - $summary->downtime_minutes) / 60
                    : (float) $summary->uptime_hours,
            'jumlah_failure' => $summary->failure_count,
            'mttf' => $summary->mttf_hours === null ? null : (float) $summary->mttf_hours,
            'mtbf' => $summary->mtbf_hours === null ? null : (float) $summary->mtbf_hours,
            'mttr' => $summary->mttr_hours === null ? null : (float) $summary->mttr_hours,
            'failure_rate' => $summary->failure_rate === null ? null : (float) $summary->failure_rate,
            'reliability' => $summary->reliability === null ? null : (float) $summary->reliability,
            'availability' => $summary->availability === null ? null : (float) $summary->availability,
            'spare_part_replacement_count' => $summary->spare_part_replacement_count,
            'vandalism_count' => $summary->vandalism_count,
            'calculation_status' => $summary->calculation_status,
            'formula_version' => $summary->formula_version,
            'parity_status' => $summary->parity_status,
            'parity_differences' => $summary->parity_differences,
            'baseline_date' => $summary->calculation_profile['interval_baseline_date']
                ?? $summary->baseline_date?->toDateString(),
            'calculated_at' => $summary->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, ReliabilitySummary>  $summaries
     * @param  Collection<int, int>  $assetIds
     * @return array<int, array<string, int|float|string>>
     */
    private function reliabilityGroups(Collection $summaries, Collection $assetIds): array
    {
        $standardLabels = [
            'PDSM' => 'Peralatan Dalam Sinyal Mekanik',
            'PLSM' => 'Peralatan Luar Sinyal Mekanik',
            'PDSE' => 'Peralatan Dalam Sinyal Elektrik',
            'PLSE' => 'Peralatan Luar Sinyal Elektrik',
            'CDS' => 'Catu Daya Sintel',
        ];
        $standardColors = [
            'PDSM' => '#92D050',
            'PLSM' => '#4BACC6',
            'PDSE' => '#FFFF00',
            'PLSE' => '#FFC000',
            'CDS' => '#FF0000',
        ];
        $grouped = $summaries->groupBy(
            fn (ReliabilitySummary $summary): string => mb_strtolower(
                $this->assetGroupLabel($this->assetGroupName($summary->asset)),
            ),
        );
        $groups = Asset::query()
            ->whereIn('id', $assetIds)
            ->with(['assetSubsystem.assetSystem.assetGroup', 'assetCategoryNode'])
            ->orderBy('id')
            ->get()
            ->groupBy(
                fn (Asset $asset): string => mb_strtolower(
                    $this->assetGroupLabel($this->assetGroupName($asset)),
                ),
            )
            ->sortBy(function (Collection $assets): string {
                $asset = $assets->first();
                $sortOrder = $asset->assetSubsystem?->assetSystem?->assetGroup?->sort_order
                    ?? $asset->assetCategoryNode?->sort_order
                    ?? 0;

                return sprintf('%010d|%s', $sortOrder, mb_strtolower($this->assetGroupName($asset)));
            });

        return $groups
            ->map(function (Collection $assets, string $groupKey) use ($grouped, $standardLabels, $standardColors): array {
                $asset = $assets->first();
                $group = $asset->assetSubsystem?->assetSystem?->assetGroup;
                $groupName = $this->assetGroupName($asset);
                $code = $this->assetGroupCode($groupName);
                $items = $grouped->get($groupKey, collect());
                $reliabilityItems = $items->whereNotNull('reliability');
                $availabilityItems = $items->whereNotNull('availability');

                return [
                    'id' => $group?->id ?? $asset->asset_category_node_id ?? $asset->id,
                    'code' => $code,
                    'name' => $standardLabels[$code] ?? $this->assetGroupLabel($groupName),
                    'color' => $group?->dashboard_color
                        ?? $asset->assetCategoryNode?->dashboard_color
                        ?? ($standardColors[$code] ?? '#64748B'),
                    'asset_count' => $assets->count(),
                    'reliability' => $reliabilityItems->isEmpty()
                        ? null
                        : $reliabilityItems->reduce(
                            fn (float $product, ReliabilitySummary $summary): float => $product *
                                (float) $summary->reliability,
                            1.0,
                        ),
                    'availability' => $availabilityItems->isEmpty()
                        ? null
                        : $availabilityItems->reduce(
                            fn (float $product, ReliabilitySummary $summary): float => $product *
                                (float) $summary->availability,
                            1.0,
                        ),
                ];
            })
            ->values()
            ->all();
    }

    private function assetGroupName(Asset $asset): string
    {
        return $asset->assetSubsystem?->assetSystem?->assetGroup?->name
            ?: $asset->aset_prasarana_sintel
            ?: $asset->assetCategoryNode?->name
            ?: 'Tanpa kategori';
    }

    /** @return array{date: string, groupCodes: list<string>}|null */
    private function latestImport(?UnitKerja $unit): ?array
    {
        if (! $unit) {
            return null;
        }

        $batch = RamsImportBatch::query()
            ->where('unit_kerja_id', $unit->id)
            ->where('status', 'succeeded')
            ->where('dry_run', false)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();

        if (! $batch) {
            return null;
        }

        return [
            'date' => ($batch->finished_at ?? $batch->created_at)->toDateString(),
            'groupCodes' => $this->latestImportGroupCodes($batch),
        ];
    }

    /** @return list<string> */
    private function latestImportGroupCodes(RamsImportBatch $batch): array
    {
        $assetIds = collect();
        $sparePartIds = collect();
        $subsystemIds = collect();
        $systemIds = collect();
        $groupIds = collect();
        $groupNames = collect();

        foreach ($batch->changes()->get() as $change) {
            $values = $this->meaningfulImportChangeValues($change);
            if ($values === null) {
                continue;
            }

            match ($change->table_name) {
                'asset_groups' => $groupNames->push($values['name'] ?? null),
                'asset_systems' => $groupIds->push($values['asset_group_id'] ?? null),
                'asset_subsystems' => $systemIds->push($values['asset_system_id'] ?? null),
                'assets', 'unit_subsystem_openings', 'spare_parts' => $subsystemIds->push(
                    $values['asset_subsystem_id'] ?? null,
                ),
                'unit_spare_part_policies' => $sparePartIds->push($values['spare_part_id'] ?? null),
                'predictive_asset_snapshots',
                'risk_matrices',
                'risk_registers',
                'failure_logs',
                'reliability_excel_snapshots',
                'reliability_summaries' => $assetIds->push($values['asset_id'] ?? null),
                default => null,
            };
        }

        if ($sparePartIds->filter()->isNotEmpty()) {
            $subsystemIds = $subsystemIds->merge(
                DB::table('spare_parts')
                    ->whereIn('id', $sparePartIds->filter()->unique())
                    ->pluck('asset_subsystem_id'),
            );
        }

        if ($assetIds->filter()->isNotEmpty()) {
            $subsystemIds = $subsystemIds->merge(
                Asset::query()
                    ->whereIn('id', $assetIds->filter()->unique())
                    ->pluck('asset_subsystem_id'),
            );
        }

        if ($subsystemIds->filter()->isNotEmpty()) {
            $systemIds = $systemIds->merge(
                AssetSubsystem::query()
                    ->whereIn('id', $subsystemIds->filter()->unique())
                    ->pluck('asset_system_id'),
            );
        }

        if ($systemIds->filter()->isNotEmpty()) {
            $groupIds = $groupIds->merge(
                AssetSystem::query()
                    ->whereIn('id', $systemIds->filter()->unique())
                    ->pluck('asset_group_id'),
            );
        }

        if ($groupIds->filter()->isNotEmpty()) {
            $groupNames = $groupNames->merge(
                AssetGroup::query()
                    ->whereIn('id', $groupIds->filter()->unique())
                    ->pluck('name'),
            );
        }

        $order = ['PDSM', 'PLSM', 'PDSE', 'PLSE', 'CDS'];

        return $groupNames
            ->filter()
            ->map(fn (string $name): string => $this->assetGroupCode($name))
            ->unique()
            ->sortBy(function (string $code) use ($order): int {
                $position = array_search($code, $order, true);

                return $position === false ? count($order) : $position;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function meaningfulImportChangeValues(RamsImportChange $change): ?array
    {
        $values = $change->after_values ?? $change->before_values;
        if (! is_array($values)) {
            return null;
        }

        if ($change->operation !== 'updated') {
            return $values;
        }

        $before = $change->before_values ?? [];
        $after = $change->after_values ?? [];
        $dateFields = [
            'created_at',
            'updated_at',
            'calculated_at',
            'imported_at',
            'first_imported_at',
            'last_imported_at',
        ];
        foreach ($dateFields as $field) {
            unset($before[$field], $after[$field]);
        }

        ksort($before);
        ksort($after);

        return $before === $after ? null : $values;
    }

    private function assetGroupCode(string $groupName): string
    {
        $name = mb_strtoupper($groupName);

        return match (true) {
            str_contains($name, 'DALAM SINYAL MEKANIK') => 'PDSM',
            str_contains($name, 'LUAR SINYAL MEKANIK') => 'PLSM',
            str_contains($name, 'DALAM SINYAL ELEKTRIK') => 'PDSE',
            str_contains($name, 'LUAR SINYAL ELEKTRIK') => 'PLSE',
            str_contains($name, 'CATU DAYA') => 'CDS',
            default => $this->assetGroupInitials($groupName),
        };
    }

    private function assetGroupLabel(string $groupName): string
    {
        return preg_replace("/^\s*\d+\s*[.\-]\s*/u", '', trim($groupName)) ?: trim($groupName);
    }

    private function assetGroupInitials(string $groupName): string
    {
        $words = preg_split("/[^\pL\pN]+/u", $this->assetGroupLabel($groupName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect($words)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return mb_substr($initials, 0, 8) ?: 'ASET';
    }

    /** @return array<string, mixed> */
    private function failurePayload(FailureLog $log): array
    {
        $isYes = fn (?string $val): bool => in_array(mb_strtoupper(trim((string) $val)), ['Y', 'YA', 'YES'], true);

        return [
            'id' => $log->id,
            'aset_id' => $log->asset_id,
            'lokasi' => $log->location,
            'resor' => $log->resort,
            'qc' => $log->qc,
            'failure_event' => $log->failure_event,
            'penyebab' => $log->cause,
            'tindakan' => $log->action_taken,
            'penggantian_sparepart' => $log->spare_part_replaced || $isYes($log->spare_part_marker) ? 'Y' : 'N',
            'tindak_vandalisme' => $log->vandalism || $isYes($log->vandalism_marker) ? 'Y' : 'N',
            'tanggal_jam_kejadian' => $log->started_at->toDateTimeString(),
            'tanggal_jam_penanganan' => $log->resolved_at->toDateTimeString(),
            'downtime_jam' => floor($log->downtime_minutes / 60).
                ':'.
                str_pad((string) ($log->downtime_minutes % 60), 2, '0', STR_PAD_LEFT),
            'downtime_menit' => $log->downtime_minutes,
            'interval_jam' => $log->interval_jam !== null ? round($log->interval_jam, 2) : null,
            'nama_sparepart' => $log->sparePart?->detail_equipment,
            'jumlah_sparepart' => $log->spare_part_quantity,
        ];
    }

    /** @return array<int, array{id: int, code: string, name: string}> */
    private function units(): array
    {
        return UnitKerja::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (UnitKerja $unit): array => $unit->only(['id', 'code', 'name']))
            ->all();
    }

    private function frontendUnitCode(string $code): string
    {
        $divre = ['I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4'];
        if (preg_match('/^DIVRE-(I|II|III|IV)$/', $code, $matches) === 1) {
            return 'DIVRE'.$divre[$matches[1]];
        }

        return str_replace('-', '', $code);
    }
}
