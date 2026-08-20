<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\UnitKerja;
use App\Services\AssetTaxonomyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    public function __construct(private readonly AssetTaxonomyService $taxonomy) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', AssetCategoryNode::class);
        $this->taxonomy->syncLegacyTree();

        $aliases = AssetCategorySourceAlias::query()
            ->select(['category_type', 'category_id'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('category_type', 'category_id')
            ->get()
            ->groupBy('category_type')
            ->map(fn (Collection $rows) => $rows->pluck('aggregate', 'category_id'));

        $groups = AssetGroup::query()
            ->withCount('systems')
            ->with(['systems' => fn ($systems) => $systems
                ->withCount('subsystems')
                ->with(['subsystems' => fn ($subsystems) => $subsystems->withCount('assets')])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AssetGroup $group): array => [
                ...$group->only(['id', 'name', 'sort_order', 'dashboard_color', 'dashboard_color_source', 'is_active']),
                'systems_count' => $group->systems_count,
                'aliases_count' => (int) ($aliases->get('group')?->get($group->id) ?? 0),
                'systems' => $group->systems->map(fn ($system): array => [
                    ...$system->only(['id', 'asset_group_id', 'name', 'sort_order', 'dashboard_color', 'dashboard_color_source', 'is_active']),
                    'subsystems_count' => $system->subsystems_count,
                    'aliases_count' => (int) ($aliases->get('system')?->get($system->id) ?? 0),
                    'subsystems' => $system->subsystems->map(fn ($subsystem): array => [
                        ...$subsystem->only(['id', 'asset_system_id', 'name', 'sort_order', 'dashboard_color', 'dashboard_color_source', 'is_active']),
                        'assets_count' => $subsystem->assets_count,
                        'aliases_count' => (int) ($aliases->get('subsystem')?->get($subsystem->id) ?? 0),
                    ])->values()->all(),
                ])->values()->all(),
            ])->values();

        $requestedGroupId = $request->integer('group', $request->integer('selected_group_id'));
        $selectedGroup = $groups->firstWhere('id', $requestedGroupId) ?? $groups->first();
        $systems = collect($selectedGroup['systems'] ?? []);
        $requestedSystemId = $request->integer('system', $request->integer('selected_system_id'));
        $selectedSystem = $systems->firstWhere('id', $requestedSystemId) ?? $systems->first();

        $levels = AssetCategoryLevel::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get(['id', 'name', 'position', 'is_active']);
        $nodes = AssetCategoryNode::query()
            ->with('level:id,position')
            ->orderBy('asset_category_level_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $unitId = $this->selectedUnitId($request);
        $activeAssets = Asset::query()
            ->when($unitId, fn (Builder $query): Builder => $query->where('unit_kerja_id', $unitId))
            ->get(['id', 'asset_category_node_id', 'jumlah_unit']);
        $directCounts = $activeAssets->groupBy('asset_category_node_id');
        $nodePayload = $nodes->map(function (AssetCategoryNode $node) use ($directCounts): array {
            $subtree = $this->taxonomy->subtreeIds($node);
            $groups = collect($directCounts->all())->only($subtree->all());

            return [
                ...$node->only([
                    'id', 'asset_category_level_id', 'parent_id', 'name', 'sort_order',
                    'dashboard_color', 'dashboard_color_source', 'is_active', 'legacy_type', 'legacy_id',
                ]),
                'level_position' => $node->level->position,
                'direct_assets_count' => $groups->get($node->id, collect())->count(),
                'subtree_assets_count' => $groups->flatten(1)->count(),
                'subtree_units_count' => (int) $groups->flatten(1)->sum('jumlah_unit'),
            ];
        });
        $requestedNode = $nodes->firstWhere('id', $request->integer('node'));
        $selectedNode = $requestedNode
            ?? $nodes->firstWhere('id', $activeAssets->first()?->asset_category_node_id)
            ?? ($request->user()->isPusat() ? $nodes->first() : null);
        $assetQuery = Asset::query()
            ->with(['unitKerja:id,code,name', 'assetCategoryNode.level:id,name,position'])
            ->when($unitId, fn (Builder $query): Builder => $query->where('unit_kerja_id', $unitId));
        if ($selectedNode) {
            $assetQuery->whereIn('asset_category_node_id', $this->taxonomy->subtreeIds($selectedNode));
        }
        $assets = $assetQuery
            ->orderBy('nama_aset')
            ->paginate(20)
            ->through(fn (Asset $asset): array => [
                ...$asset->only([
                    'id', 'unit_kerja_id', 'asset_category_node_id', 'nama_aset', 'lokasi',
                    'jumlah_unit', 'tanggal_pemasangan', 'status', 'aset_prasarana_sintel', 'system', 'subsystem',
                ]),
                'unit_kerja' => $asset->unitKerja?->only(['id', 'code', 'name']),
                'category_node' => $asset->assetCategoryNode?->only(['id', 'name', 'asset_category_level_id']),
            ])
            ->withQueryString();

        return Inertia::render('Admin/AssetCategories/Index', [
            'groups' => $groups,
            'selectedGroupId' => $selectedGroup['id'] ?? null,
            'selectedSystemId' => $selectedSystem['id'] ?? null,
            'levels' => $levels,
            'nodes' => $nodePayload,
            'assets' => $assets,
            'units' => $request->user()->isPusat()
                ? UnitKerja::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : [],
            'selectedUnitId' => $unitId,
            'selectedNodeId' => $selectedNode?->id,
            'capabilities' => [
                'manage' => $request->user()->isPusat(),
                'manage_taxonomy' => $request->user()->isPusat(),
                'manage_assets' => true,
                'choose_unit' => $request->user()->isPusat(),
            ],
        ]);
    }

    private function selectedUnitId(Request $request): ?int
    {
        if ($request->user()->isUnit()) {
            return $request->user()->unit_kerja_id;
        }

        $requested = $request->integer('unit_kerja_id');
        if ($requested && UnitKerja::query()->whereKey($requested)->where('is_active', true)->exists()) {
            return $requested;
        }

        return UnitKerja::query()->where('is_active', true)->orderBy('code')->value('id');
    }
}
