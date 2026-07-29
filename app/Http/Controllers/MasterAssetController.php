<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\UnitKerja;
use App\Queries\AssetHierarchyQuery;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MasterAssetController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AssetHierarchyQuery $assetHierarchyQuery,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $unitId = $this->selectedUnitId($request);
        $query = $this->filteredQuery($request, $search, $status, $unitId);
        $hierarchyProps = $this->hierarchyProps($request, $query, $unitId);

        $stats = [
            'total_assets' => (clone $query)->count(),
            'total_units' => (int) (clone $query)->sum('jumlah_unit'),
            'active_assets' => (clone $query)->where('status', AssetStatus::Aktif->value)->count(),
            'unique_subsystems' => (clone $query)
                ->whereNotNull('asset_subsystem_id')
                ->distinct()
                ->count('asset_subsystem_id')
                + (clone $query)
                    ->whereNull('asset_subsystem_id')
                    ->whereRaw("TRIM(`subsystem`) <> ''")
                    ->distinct()
                    ->count(DB::raw('TRIM(`subsystem`)')),
        ];

        $assets = $query
            ->with([
                'assetSubsystem.assetSystem.assetGroup',
                'unitKerja:id,code,name',
            ])
            ->orderBy('unit_kerja_id')
            ->orderBy('system')
            ->orderBy('subsystem')
            ->orderBy('nama_aset')
            ->paginate(15)
            ->through(fn (Asset $asset): array => [
                ...$this->assetPayload($asset),
                'unit_kerja' => $asset->unitKerja->only(['id', 'code', 'name']),
            ])
            ->withQueryString();

        return Inertia::render('master-data/assets/MasterAsset', [
            'assets' => $assets,
            ...$hierarchyProps,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'unit_kerja_id' => $unitId ? (string) $unitId : '',
            ],
            'units' => $request->user()->isPusat() ? $this->activeUnits() : [],
            'statusOptions' => $this->statusOptions(),
            'can' => ['choose_unit' => $request->user()->isPusat()],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Asset::class);

        return Inertia::render('master-data/assets/Create', $this->formProps($request));
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $subsystem = $this->lockedCategoryPath($request->integer('asset_subsystem_id'), false);
            $asset = Asset::query()->create($request->assetData($subsystem));
            $asset->setRelation('assetSubsystem', $subsystem);
            $this->auditLogger->record('asset.created', $asset, [], $this->auditValues($asset));
        });

        return redirect()->route('master-assets.index')->with('success', 'Aset berhasil ditambahkan.');
    }

    public function edit(Request $request, int $asset): Response
    {
        $asset = $this->visibleAsset($request, $asset, true);
        Gate::authorize('update', $asset);

        return Inertia::render('master-data/assets/Edit', [
            ...$this->formProps($request, $asset->assetSubsystem),
            'asset' => $this->assetPayload($asset),
        ]);
    }

    public function update(UpdateAssetRequest $request, int $asset): RedirectResponse
    {
        $this->visibleAsset($request, $asset);

        DB::transaction(function () use ($request, $asset): void {
            $lockedAsset = Asset::query()
                ->visibleTo($request->user())
                ->with('assetSubsystem.assetSystem.assetGroup')
                ->lockForUpdate()
                ->findOrFail($asset);
            $subsystem = $this->lockedCategoryPath(
                $request->integer('asset_subsystem_id'),
                $lockedAsset->asset_subsystem_id === $request->integer('asset_subsystem_id'),
            );
            $before = $this->auditValues($lockedAsset);
            $lockedAsset->update($request->assetData($subsystem));
            $lockedAsset->setRelation('assetSubsystem', $subsystem);
            $this->auditLogger->record('asset.updated', $lockedAsset, $before, $this->auditValues($lockedAsset));
        });

        return redirect()->route('master-assets.index')->with('success', 'Aset berhasil diperbarui.');
    }

    public function destroy(Request $request, int $asset): RedirectResponse
    {
        $asset = $this->visibleAsset($request, $asset);
        Gate::authorize('delete', $asset);

        DB::transaction(function () use ($asset): void {
            $before = $this->auditValues($asset);
            $asset->delete();
            $this->auditLogger->record('asset.deleted', $asset, $before, []);
        });

        return redirect()->route('master-assets.index')->with('success', 'Aset berhasil dihapus.');
    }

    private function filteredQuery(Request $request, string $search, string $status, ?int $unitId): Builder
    {
        return Asset::query()
            ->visibleTo($request->user())
            ->search($search)
            ->when(
                AssetStatus::tryFrom($status),
                fn (Builder $query, AssetStatus $validStatus): Builder => $query->where('status', $validStatus->value),
            )
            ->when(
                $request->user()->isPusat() && $unitId,
                fn (Builder $query): Builder => $query->where('unit_kerja_id', $unitId),
            );
    }

    private function hierarchyProps(Request $request, Builder $filteredQuery, ?int $unitId): array
    {
        $matched = (clone $filteredQuery)
            ->select('asset_subsystem_id')
            ->selectRaw('COUNT(*) AS asset_count')
            ->selectRaw('COALESCE(SUM(jumlah_unit), 0) AS total')
            ->groupBy('asset_subsystem_id')
            ->get();
        $matchedBySubsystem = $matched
            ->whereNotNull('asset_subsystem_id')
            ->keyBy(fn (Asset $aggregate): int => (int) $aggregate->asset_subsystem_id);
        $subsystemIds = $matchedBySubsystem->keys()->values();
        $hierarchy = $subsystemIds->isEmpty()
            ? collect()
            : $this->assetHierarchyQuery
                ->forUser($request->user(), $unitId, $subsystemIds->all())
                ->each(function (AssetSubsystem $subsystem) use ($matchedBySubsystem): void {
                    $subsystem->setAttribute(
                        'total',
                        (int) $matchedBySubsystem->get($subsystem->id)->getAttribute('total'),
                    );
                });
        $legacy = $matched->first(fn (Asset $aggregate): bool => $aggregate->asset_subsystem_id === null);

        return [
            'hierarchy' => $hierarchy,
            'legacySummary' => $legacy ? [
                'asset_count' => (int) $legacy->getAttribute('asset_count'),
                'total' => (int) $legacy->getAttribute('total'),
                'sparepart_in' => 0,
                'sparepart_out' => 0,
            ] : null,
        ];
    }

    private function visibleAsset(Request $request, int $id, bool $withCategory = false): Asset
    {
        return Asset::query()
            ->visibleTo($request->user())
            ->when($withCategory, fn (Builder $query): Builder => $query->with('assetSubsystem.assetSystem.assetGroup'))
            ->findOrFail($id);
    }

    private function formProps(Request $request, ?AssetSubsystem $currentSubsystem = null): array
    {
        return [
            'units' => $request->user()->isPusat() ? $this->activeUnits() : [],
            'categories' => $this->activeCategories($currentSubsystem),
            'statusOptions' => $this->statusOptions(),
            'can' => ['choose_unit' => $request->user()->isPusat()],
        ];
    }

    private function activeUnits()
    {
        return UnitKerja::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    private function selectedUnitId(Request $request): ?int
    {
        $value = $request->input('unit_kerja_id');
        if (! $request->user()->isPusat() || (! is_int($value) && ! is_string($value))) {
            return null;
        }

        $unitId = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($unitId === false) {
            return null;
        }

        return UnitKerja::query()
            ->where('is_active', true)
            ->whereKey($unitId)
            ->value('id');
    }

    private function statusOptions(): array
    {
        return array_map(
            fn (AssetStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            AssetStatus::cases(),
        );
    }

    private function assetPayload(Asset $asset): array
    {
        $subsystem = $asset->assetSubsystem;
        $system = $subsystem?->assetSystem;
        $group = $system?->assetGroup;

        return [
            ...$asset->only([
                'id',
                'unit_kerja_id',
                'asset_subsystem_id',
                'nama_aset',
                'aset_prasarana_sintel',
                'system',
                'subsystem',
                'lokasi',
                'jumlah_unit',
            ]),
            'status' => $asset->status->value,
            'tanggal_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
            'category' => $subsystem && $system && $group ? [
                'group' => $this->categoryPayload($group),
                'system' => $this->categoryPayload($system),
                'subsystem' => $this->categoryPayload($subsystem),
            ] : null,
        ];
    }

    private function auditValues(Asset $asset): array
    {
        return $this->assetPayload($asset);
    }

    private function activeCategories(?AssetSubsystem $currentSubsystem = null): array
    {
        $categories = AssetGroup::query()
            ->where('is_active', true)
            ->with(['systems' => fn ($systems) => $systems
                ->where('is_active', true)
                ->with(['subsystems' => fn ($subsystems) => $subsystems->where('is_active', true)])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AssetGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                '_sort_order' => $group->sort_order,
                'systems' => $group->systems->map(fn (AssetSystem $system): array => [
                    'id' => $system->id,
                    'name' => $system->name,
                    '_sort_order' => $system->sort_order,
                    'subsystems' => $system->subsystems->map(fn (AssetSubsystem $subsystem): array => [
                        'id' => $subsystem->id,
                        'name' => $subsystem->name,
                        '_sort_order' => $subsystem->sort_order,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all();

        if (! $currentSubsystem) {
            return $this->orderedCategoryOptions($categories);
        }

        $currentSubsystem->loadMissing('assetSystem.assetGroup');
        $currentSystem = $currentSubsystem->assetSystem;
        $currentGroup = $currentSystem?->assetGroup;

        if (! $currentSystem || ! $currentGroup) {
            return $this->orderedCategoryOptions($categories);
        }

        $currentPath = [
            'id' => $currentGroup->id,
            'name' => $currentGroup->name,
            '_sort_order' => $currentGroup->sort_order,
            'is_active' => $currentGroup->is_active,
            'systems' => [[
                'id' => $currentSystem->id,
                'name' => $currentSystem->name,
                '_sort_order' => $currentSystem->sort_order,
                'is_active' => $currentSystem->is_active,
                'subsystems' => [[
                    'id' => $currentSubsystem->id,
                    'name' => $currentSubsystem->name,
                    '_sort_order' => $currentSubsystem->sort_order,
                    'is_active' => $currentSubsystem->is_active,
                ]],
            ]],
        ];
        $groupIndex = array_search($currentGroup->id, array_column($categories, 'id'), true);

        if ($groupIndex === false) {
            $categories[] = $currentPath;

            return $this->orderedCategoryOptions($categories);
        }

        $systemIndex = array_search(
            $currentSystem->id,
            array_column($categories[$groupIndex]['systems'], 'id'),
            true,
        );

        if ($systemIndex === false) {
            $categories[$groupIndex]['systems'][] = $currentPath['systems'][0];

            return $this->orderedCategoryOptions($categories);
        }

        $subsystemIds = array_column($categories[$groupIndex]['systems'][$systemIndex]['subsystems'], 'id');
        if (! in_array($currentSubsystem->id, $subsystemIds, true)) {
            $categories[$groupIndex]['systems'][$systemIndex]['subsystems'][] = $currentPath['systems'][0]['subsystems'][0];
        }

        return $this->orderedCategoryOptions($categories);
    }

    private function orderedCategoryOptions(array $categories): array
    {
        $compare = fn (array $left, array $right): int => ($left['_sort_order'] <=> $right['_sort_order'])
            ?: strcasecmp($left['name'], $right['name'])
            ?: ($left['id'] <=> $right['id']);
        usort($categories, $compare);

        foreach ($categories as &$group) {
            usort($group['systems'], $compare);
            foreach ($group['systems'] as &$system) {
                usort($system['subsystems'], $compare);
                foreach ($system['subsystems'] as &$subsystem) {
                    unset($subsystem['_sort_order']);
                }
                unset($subsystem, $system['_sort_order']);
            }
            unset($system, $group['_sort_order']);
        }
        unset($group);

        return $categories;
    }

    private function categoryPayload(AssetGroup|AssetSystem|AssetSubsystem $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'is_active' => $category->is_active,
        ];
    }

    private function lockedCategoryPath(int $subsystemId, bool $allowInactive): AssetSubsystem
    {
        $identity = AssetSubsystem::query()
            ->with('assetSystem:id,asset_group_id')
            ->find($subsystemId);

        if (! $identity || ! $identity->assetSystem) {
            throw ValidationException::withMessages([
                'asset_subsystem_id' => 'Kategori aset yang dipilih tidak tersedia.',
            ]);
        }

        $group = AssetGroup::query()->lockForUpdate()->find($identity->assetSystem->asset_group_id);
        $system = AssetSystem::query()
            ->where('asset_group_id', $group?->id)
            ->lockForUpdate()
            ->find($identity->asset_system_id);
        $subsystem = AssetSubsystem::query()
            ->where('asset_system_id', $system?->id)
            ->lockForUpdate()
            ->find($subsystemId);

        if (! $group || ! $system || ! $subsystem || (! $allowInactive && (! $group->is_active || ! $system->is_active || ! $subsystem->is_active))) {
            throw ValidationException::withMessages([
                'asset_subsystem_id' => 'Kategori aset yang dipilih tidak aktif atau tidak tersedia.',
            ]);
        }

        $system->setRelation('assetGroup', $group);
        $subsystem->setRelation('assetSystem', $system);

        return $subsystem;
    }
}
