<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetTaxonomyService
{
    /** @return array{levels: int, nodes: int} */
    public function syncLegacyTree(): array
    {
        return DB::transaction(function (): array {
            $levels = $this->ensureDefaultLevels();
            $groupNodes = [];
            foreach (AssetGroup::withTrashed()->orderBy('id')->get() as $group) {
                $groupNodes[$group->id] = $this->syncLegacyNode('group', $group, $levels[1], null);
            }

            $systemNodes = [];
            foreach (AssetSystem::withTrashed()->orderBy('id')->get() as $system) {
                $systemNodes[$system->id] = $this->syncLegacyNode(
                    'system',
                    $system,
                    $levels[2],
                    $groupNodes[$system->asset_group_id]?->id ?? null,
                );
            }

            foreach (AssetSubsystem::withTrashed()->orderBy('id')->get() as $subsystem) {
                $this->syncLegacyNode(
                    'subsystem',
                    $subsystem,
                    $levels[3],
                    $systemNodes[$subsystem->asset_system_id]?->id ?? null,
                );
            }

            return [
                'levels' => AssetCategoryLevel::query()->count(),
                'nodes' => AssetCategoryNode::query()->count(),
            ];
        });
    }

    public function syncLegacyPath(
        AssetGroup $group,
        AssetSystem $system,
        AssetSubsystem $subsystem,
    ): AssetCategoryNode {
        $levels = $this->ensureDefaultLevels();
        $groupNode = $this->syncLegacyNode('group', $group, $levels[1], null);
        $systemNode = $this->syncLegacyNode('system', $system, $levels[2], $groupNode->id);

        return $this->syncLegacyNode('subsystem', $subsystem, $levels[3], $systemNode->id);
    }

    public function createNode(
        AssetCategoryLevel $level,
        ?AssetCategoryNode $parent,
        string $name,
        int $sortOrder = 0,
        ?string $dashboardColor = null,
    ): AssetCategoryNode {
        $this->assertValidParent($level, $parent);

        return AssetCategoryNode::query()->create([
            'asset_category_level_id' => $level->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'sort_order' => $sortOrder,
            'dashboard_color' => $dashboardColor,
            'dashboard_color_source' => $dashboardColor ? 'manual' : null,
            'is_active' => true,
        ]);
    }

    /** @return Collection<int, int> */
    public function subtreeIds(AssetCategoryNode $root): Collection
    {
        $ids = collect([$root->id]);
        $frontier = collect([$root->id]);

        while ($frontier->isNotEmpty()) {
            $frontier = AssetCategoryNode::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id');
            $ids->push(...$frontier);
        }

        return $ids->unique()->values();
    }

    /** @return Collection<int, AssetCategoryNode> */
    public function path(AssetCategoryNode $node): Collection
    {
        $path = collect();
        $current = $node;

        while ($current) {
            $path->prepend($current);
            $current = $current->parent()->first();
        }

        return $path->values();
    }

    public function nearestLegacySubsystem(AssetCategoryNode $node): ?AssetSubsystem
    {
        $subsystemNode = $this->path($node)
            ->reverse()
            ->first(fn (AssetCategoryNode $candidate): bool => $candidate->legacy_type === 'subsystem');

        return $subsystemNode?->legacy_id
            ? AssetSubsystem::query()->find($subsystemNode->legacy_id)
            : null;
    }

    public function nodeForLegacy(string $type, int $id): ?AssetCategoryNode
    {
        return AssetCategoryNode::query()
            ->where('legacy_type', $type)
            ->where('legacy_id', $id)
            ->first();
    }

    /** @return array<int, AssetCategoryLevel> */
    private function ensureDefaultLevels(): array
    {
        $definitions = [
            1 => 'Aset Prasarana Sintel',
            2 => 'System',
            3 => 'Subsystem',
        ];
        $levels = [];

        foreach ($definitions as $position => $name) {
            $levels[$position] = AssetCategoryLevel::withTrashed()->firstOrCreate(
                ['position' => $position],
                ['name' => $name, 'is_active' => true],
            );
            if ($levels[$position]->trashed()) {
                $levels[$position]->restore();
            }
        }

        return $levels;
    }

    private function syncLegacyNode(
        string $type,
        AssetGroup|AssetSystem|AssetSubsystem $legacy,
        AssetCategoryLevel $level,
        ?int $parentId,
    ): AssetCategoryNode {
        $node = AssetCategoryNode::withTrashed()->firstOrNew([
            'legacy_type' => $type,
            'legacy_id' => $legacy->id,
        ]);
        $node->fill([
            'asset_category_level_id' => $level->id,
            'parent_id' => $parentId,
            'name' => $legacy->name,
            'sort_order' => $legacy->sort_order,
            'dashboard_color' => $legacy->dashboard_color,
            'dashboard_color_source' => $legacy->dashboard_color_source,
            'is_active' => $legacy->is_active,
        ]);
        $node->save();

        if ($legacy->trashed() && ! $node->trashed()) {
            $node->delete();
        } elseif (! $legacy->trashed() && $node->trashed()) {
            $node->restore();
        }

        return $node;
    }

    private function assertValidParent(AssetCategoryLevel $level, ?AssetCategoryNode $parent): void
    {
        if ($level->position === 1 && $parent === null) {
            return;
        }

        $parent?->loadMissing('level');
        if (! $parent || $parent->level->position !== $level->position - 1) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent harus berasal dari level tepat sebelumnya.',
            ]);
        }
    }
}
