<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InventoryController extends Controller
{
    private const STOCK_STATUSES = ['all', 'available', 'below_reorder', 'critical', 'empty'];

    private const TABS = ['stock', 'history', 'master'];

    private const MAX_PAGE = 1_000_000;

    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $filters = $this->filters($request);
        $stockPage = $this->page($request->input('page'));
        $movementPage = $this->page($request->input('movement_page'));
        $stockQuery = $this->stockQuery($request, $filters);
        $movementQuery = $this->movementQuery($request, $filters);

        $stocks = (clone $stockQuery)
            ->with([
                'sparePart' => fn ($query) => $this->withHistoricalCategory($query),
                'unitKerja' => fn ($query) => $query->withTrashed(),
            ])
            ->orderBy('spare_parts.code')
            ->orderBy('unit_kerjas.code')
            ->paginate(20, ['inventory_stocks.*'], 'page', $stockPage)
            ->through(fn (InventoryStock $stock): array => $this->stockPayload($stock))
            ->withQueryString();

        $movements = (clone $movementQuery)
            ->with([
                'sparePart' => fn ($query) => $this->withHistoricalCategory($query),
                'unitKerja' => fn ($query) => $query->withTrashed(),
                'actor:id,name',
            ])
            ->orderByDesc('stock_movements.movement_date')
            ->orderByDesc('stock_movements.id')
            ->paginate(20, ['stock_movements.*'], 'movement_page', $movementPage)
            ->through(fn (StockMovement $movement): array => $this->movementPayload($movement))
            ->withQueryString();

        $statsQuery = clone $stockQuery;
        $movementStatsQuery = $this->movementQuery($request, [
            ...$filters,
            'movement_type' => '',
            'date_from' => '',
            'date_to' => '',
        ]);

        return Inertia::render('master-data/inventory/Inventory', [
            'stats' => [
                'total_parts' => (clone $statsQuery)->distinct()->count('inventory_stocks.spare_part_id'),
                'total_quantity' => (int) (clone $statsQuery)->sum('inventory_stocks.quantity'),
                'below_reorder' => (clone $statsQuery)
                    ->whereNotNull('spare_parts.reorder_point')
                    ->whereColumn('inventory_stocks.quantity', '<=', 'spare_parts.reorder_point')
                    ->count(),
                'movements_this_month' => (clone $movementStatsQuery)
                    ->whereBetween('stock_movements.movement_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count(),
            ],
            'stocks' => $stocks,
            'movements' => $movements,
            'spareParts' => $this->spareParts($request, $filters),
            'categories' => $this->categories(),
            'units' => $request->user()->isPusat() ? $this->activeUnits() : [],
            'filters' => $filters,
            'can' => [
                'choose_unit' => $request->user()->isPusat(),
                'manage_master' => Gate::allows('create', SparePart::class),
                'record_movement' => Gate::allows('viewAny', InventoryStock::class),
            ],
        ]);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $search = Str::squish($this->scalarString($request->input('search')));
        $groupId = $this->activeGroupId($request->input('asset_group_id'));
        $subsystemId = $this->activeSubsystemId($request->input('asset_subsystem_id'));
        $stockStatus = $this->scalarString($request->input('stock_status'));
        $tab = $this->scalarString($request->input('tab'));
        $movementType = $this->scalarString($request->input('movement_type'));
        $dateFrom = $this->date($request->input('date_from'));
        $dateTo = $this->date($request->input('date_to'));

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            $dateFrom = '';
            $dateTo = '';
        }

        return [
            'search' => $search,
            'asset_group_id' => $groupId ? (string) $groupId : '',
            'asset_subsystem_id' => $subsystemId ? (string) $subsystemId : '',
            'stock_status' => in_array($stockStatus, self::STOCK_STATUSES, true) ? $stockStatus : 'all',
            'unit_kerja_id' => ($unitId = $this->activeUnitId($request)) ? (string) $unitId : '',
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'stock',
            'movement_type' => StockMovementType::tryFrom($movementType)?->value ?? '',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'master_page' => (string) $this->page($request->input('master_page')),
        ];
    }

    /** @param array<string, string> $filters */
    private function stockQuery(Request $request, array $filters): Builder
    {
        $query = InventoryStock::query()
            ->visibleTo($request->user())
            ->select('inventory_stocks.*')
            ->selectRaw($this->stockStatusSql().' AS stock_status')
            ->join('spare_parts', 'spare_parts.id', '=', 'inventory_stocks.spare_part_id')
            ->join('asset_subsystems', 'asset_subsystems.id', '=', 'spare_parts.asset_subsystem_id')
            ->join('asset_systems', 'asset_systems.id', '=', 'asset_subsystems.asset_system_id')
            ->join('asset_groups', 'asset_groups.id', '=', 'asset_systems.asset_group_id')
            ->join('unit_kerjas', 'unit_kerjas.id', '=', 'inventory_stocks.unit_kerja_id');

        $this->applyCommonFilters($query, $filters, 'inventory_stocks');

        if ($filters['stock_status'] !== 'all') {
            $query->whereRaw($this->stockStatusSql().' = ?', [$filters['stock_status']]);
        }

        return $query;
    }

    /** @param array<string, string> $filters */
    private function movementQuery(Request $request, array $filters): Builder
    {
        $query = StockMovement::query()
            ->visibleTo($request->user())
            ->select('stock_movements.*')
            ->selectSub(
                InventoryStock::query()
                    ->select('quantity')
                    ->whereColumn('inventory_stocks.unit_kerja_id', 'stock_movements.unit_kerja_id')
                    ->whereColumn('inventory_stocks.spare_part_id', 'stock_movements.spare_part_id')
                    ->limit(1),
                'current_stock',
            )
            ->withExists('corrections')
            ->join('spare_parts', 'spare_parts.id', '=', 'stock_movements.spare_part_id')
            ->join('asset_subsystems', 'asset_subsystems.id', '=', 'spare_parts.asset_subsystem_id')
            ->join('asset_systems', 'asset_systems.id', '=', 'asset_subsystems.asset_system_id')
            ->join('asset_groups', 'asset_groups.id', '=', 'asset_systems.asset_group_id')
            ->join('unit_kerjas', 'unit_kerjas.id', '=', 'stock_movements.unit_kerja_id');

        $this->applyCommonFilters($query, $filters, 'stock_movements', true);

        return $query
            ->when(
                $filters['movement_type'] !== '',
                fn (Builder $filtered): Builder => $filtered->where('stock_movements.type', $filters['movement_type']),
            )
            ->when(
                $filters['date_from'] !== '',
                fn (Builder $filtered): Builder => $filtered->where('stock_movements.movement_date', '>=', $filters['date_from']),
            )
            ->when(
                $filters['date_to'] !== '',
                fn (Builder $filtered): Builder => $filtered->where('stock_movements.movement_date', '<=', $filters['date_to']),
            );
    }

    /** @param array<string, string> $filters */
    private function applyCommonFilters(Builder $query, array $filters, string $table, bool $includeMovementText = false): void
    {
        if ($filters['search'] !== '') {
            $like = '%'.$filters['search'].'%';
            $query->where(function (Builder $search) use ($like, $includeMovementText): void {
                $search->where('spare_parts.code', 'like', $like)
                    ->orWhere('spare_parts.equipment', 'like', $like)
                    ->orWhere('spare_parts.detail_equipment', 'like', $like)
                    ->orWhere('asset_subsystems.name', 'like', $like)
                    ->orWhere('asset_systems.name', 'like', $like)
                    ->orWhere('asset_groups.name', 'like', $like)
                    ->orWhere('unit_kerjas.code', 'like', $like)
                    ->orWhere('unit_kerjas.name', 'like', $like);

                if ($includeMovementText) {
                    $search->orWhere('stock_movements.reference_number', 'like', $like)
                        ->orWhere('stock_movements.notes', 'like', $like);
                }
            });
        }

        $query
            ->when(
                $filters['asset_group_id'] !== '',
                fn (Builder $filtered): Builder => $filtered->where('asset_groups.id', (int) $filters['asset_group_id']),
            )
            ->when(
                $filters['asset_subsystem_id'] !== '',
                fn (Builder $filtered): Builder => $filtered->where('asset_subsystems.id', (int) $filters['asset_subsystem_id']),
            )
            ->when(
                $filters['unit_kerja_id'] !== '',
                fn (Builder $filtered): Builder => $filtered->where($table.'.unit_kerja_id', (int) $filters['unit_kerja_id']),
            );
    }

    /** @param array<string, string> $filters */
    private function spareParts(Request $request, array $filters): array
    {
        $parts = SparePart::query()
            ->when(! $request->user()->isPusat(), fn (Builder $query): Builder => $query->active())
            ->with('assetSubsystem.assetSystem.assetGroup')
            ->when($filters['asset_group_id'] !== '', fn (Builder $query): Builder => $query->whereHas(
                'assetSubsystem.assetSystem',
                fn (Builder $system): Builder => $system->where('asset_group_id', (int) $filters['asset_group_id']),
            ))
            ->when($filters['asset_subsystem_id'] !== '', fn (Builder $query): Builder => $query->where(
                'asset_subsystem_id',
                (int) $filters['asset_subsystem_id'],
            ))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $search) use ($like): void {
                    $search->where('code', 'like', $like)
                        ->orWhere('equipment', 'like', $like)
                        ->orWhere('detail_equipment', 'like', $like)
                        ->orWhereHas('assetSubsystem', fn (Builder $subsystem): Builder => $subsystem
                            ->where('name', 'like', $like)
                            ->orWhereHas('assetSystem', fn (Builder $system): Builder => $system
                                ->where('name', 'like', $like)
                                ->orWhereHas('assetGroup', fn (Builder $group): Builder => $group->where('name', 'like', $like))));
                });
            })
            ->orderBy('code')
            ->get();

        return $parts->map(fn (SparePart $part): array => $this->partPayload($part))->all();
    }

    private function stockPayload(InventoryStock $stock): array
    {
        return [
            ...$stock->only(['id', 'unit_kerja_id', 'spare_part_id', 'quantity']),
            'status' => $stock->getAttribute('stock_status'),
            'spare_part' => $this->partPayload($stock->sparePart),
            'unit' => $this->unitPayload($stock->unitKerja),
        ];
    }

    private function movementPayload(StockMovement $movement): array
    {
        return [
            ...$movement->only([
                'id',
                'unit_kerja_id',
                'spare_part_id',
                'actor_id',
                'quantity',
                'stock_before',
                'stock_after',
                'reference_number',
                'notes',
                'reverses_movement_id',
            ]),
            'type' => $movement->type->value,
            'direction' => $movement->direction->value,
            'movement_date' => $movement->movement_date->toDateString(),
            'posted_at' => $movement->created_at?->toIso8601String(),
            'current_stock' => (int) ($movement->getAttribute('current_stock') ?? 0),
            'is_correctable' => $movement->type !== StockMovementType::Correction
                && ! (bool) $movement->getAttribute('corrections_exists'),
            'spare_part' => $this->partPayload($movement->sparePart),
            'unit' => $this->unitPayload($movement->unitKerja),
            'actor' => $movement->actor?->only(['id', 'name']),
        ];
    }

    private function partPayload(SparePart $part): array
    {
        $subsystem = $part->assetSubsystem;
        $system = $subsystem?->assetSystem;
        $group = $system?->assetGroup;

        return [
            ...$part->only([
                'id',
                'asset_subsystem_id',
                'code',
                'equipment',
                'detail_equipment',
                'max_yearly_failure',
                'average_yearly_failure',
                'max_lead_time_months',
                'average_lead_time_months',
                'safety_stock',
                'lead_time_demand',
                'reorder_point',
                'severity',
                'unit_of_measure',
                'is_active',
            ]),
            'category' => $subsystem && $system && $group ? [
                'group' => $group->only(['id', 'name', 'is_active']),
                'system' => $system->only(['id', 'name', 'is_active']),
                'subsystem' => $subsystem->only(['id', 'name', 'is_active']),
            ] : null,
        ];
    }

    private function withHistoricalCategory($query)
    {
        return $query->withTrashed()->with([
            'assetSubsystem' => fn ($subsystem) => $subsystem->withTrashed()->with([
                'assetSystem' => fn ($system) => $system->withTrashed()->with([
                    'assetGroup' => fn ($group) => $group->withTrashed(),
                ]),
            ]),
        ]);
    }

    private function unitPayload(UnitKerja $unit): array
    {
        return $unit->only(['id', 'code', 'name']);
    }

    private function categories(): array
    {
        return AssetGroup::query()
            ->where('is_active', true)
            ->with(['systems' => fn ($systems) => $systems
                ->where('is_active', true)
                ->with(['subsystems' => fn ($subsystems) => $subsystems->where('is_active', true)])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AssetGroup $group): array => [
                ...$group->only(['id', 'name']),
                'systems' => $group->systems->map(fn (AssetSystem $system): array => [
                    ...$system->only(['id', 'name']),
                    'subsystems' => $system->subsystems->map(
                        fn (AssetSubsystem $subsystem): array => $subsystem->only(['id', 'name']),
                    )->all(),
                ])->all(),
            ])->all();
    }

    private function activeUnits(): array
    {
        return UnitKerja::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->toArray();
    }

    private function activeUnitId(Request $request): ?int
    {
        $value = $this->scalarString($request->input('unit_kerja_id'));
        if (! $request->user()->isPusat() || ! ctype_digit($value)) {
            return null;
        }

        return UnitKerja::query()
            ->where('is_active', true)
            ->whereKey((int) $value)
            ->value('id');
    }

    private function activeGroupId(mixed $value): ?int
    {
        $value = $this->scalarString($value);
        if (! ctype_digit($value)) {
            return null;
        }

        return AssetGroup::query()->where('is_active', true)->whereKey((int) $value)->value('id');
    }

    private function activeSubsystemId(mixed $value): ?int
    {
        $value = $this->scalarString($value);
        if (! ctype_digit($value)) {
            return null;
        }

        return AssetSubsystem::query()
            ->where('is_active', true)
            ->whereHas('assetSystem', fn (Builder $system): Builder => $system
                ->where('is_active', true)
                ->whereHas('assetGroup', fn (Builder $group): Builder => $group->where('is_active', true)))
            ->whereKey((int) $value)
            ->value('id');
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            return $date && $date->format('Y-m-d') === $value ? $value : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function scalarString(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? trim((string) $value)
            : '';
    }

    private function page(mixed $value): int
    {
        $value = $this->scalarString($value);

        if (! ctype_digit($value)) {
            return 1;
        }

        $value = ltrim($value, '0');
        if ($value === '') {
            return 1;
        }

        $maximum = (string) self::MAX_PAGE;
        if (strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
            return self::MAX_PAGE;
        }

        return (int) $value;
    }

    private function stockStatusSql(): string
    {
        return <<<'SQL'
CASE
    WHEN inventory_stocks.quantity = 0 THEN 'empty'
    WHEN spare_parts.safety_stock IS NOT NULL
        AND inventory_stocks.quantity <= spare_parts.safety_stock THEN 'critical'
    WHEN spare_parts.reorder_point IS NOT NULL
        AND inventory_stocks.quantity > COALESCE(spare_parts.safety_stock, 0)
        AND inventory_stocks.quantity <= spare_parts.reorder_point THEN 'below_reorder'
    ELSE 'available'
END
SQL;
    }
}
