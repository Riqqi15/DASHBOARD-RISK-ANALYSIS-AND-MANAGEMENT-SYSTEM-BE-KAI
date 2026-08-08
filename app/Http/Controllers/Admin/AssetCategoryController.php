<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', AssetGroup::class);

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

        return Inertia::render('Admin/AssetCategories/Index', [
            'groups' => $groups,
            'selectedGroupId' => $selectedGroup['id'] ?? null,
            'selectedSystemId' => $selectedSystem['id'] ?? null,
            'capabilities' => ['manage' => true],
        ]);
    }
}
