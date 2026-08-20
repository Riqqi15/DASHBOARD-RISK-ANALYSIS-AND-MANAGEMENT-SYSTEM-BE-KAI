<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetCategoryNodeRequest;
use App\Http\Requests\Admin\UpdateAssetCategoryNodeRequest;
use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Services\AssetTaxonomyService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetCategoryNodeController extends Controller
{
    public function __construct(
        private readonly AssetTaxonomyService $taxonomy,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function store(StoreAssetCategoryNodeRequest $request): RedirectResponse
    {
        $node = DB::transaction(function () use ($request): AssetCategoryNode {
            $level = AssetCategoryLevel::query()->lockForUpdate()->findOrFail($request->integer('asset_category_level_id'));
            $parent = $request->filled('parent_id')
                ? AssetCategoryNode::query()->with('level')->lockForUpdate()->findOrFail($request->integer('parent_id'))
                : null;
            $this->assertUniqueSibling($level->id, $parent?->id, $request->validated('name'));

            if ($level->position <= 3) {
                $node = $this->createLegacyNode($level->position, $parent, $request->validated());
            } else {
                $node = $this->taxonomy->createNode(
                    $level,
                    $parent,
                    $request->validated('name'),
                    (int) $request->validated('sort_order'),
                    $request->validated('dashboard_color'),
                );
            }

            $this->auditLogger->record('asset_category_node.created', $node, [], $this->auditValues($node));

            return $node;
        });

        return redirect()->route('admin.asset-categories.index', ['node' => $node->id])
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(UpdateAssetCategoryNodeRequest $request, AssetCategoryNode $assetCategoryNode): RedirectResponse
    {
        DB::transaction(function () use ($request, $assetCategoryNode): void {
            $node = AssetCategoryNode::query()->with('level')->lockForUpdate()->findOrFail($assetCategoryNode->id);
            $this->assertUniqueSibling($node->asset_category_level_id, $node->parent_id, $request->validated('name'), $node->id);
            $before = $this->auditValues($node);
            $values = [
                'name' => $request->validated('name'),
                'sort_order' => $request->validated('sort_order'),
                'dashboard_color' => $request->validated('dashboard_color'),
                'dashboard_color_source' => $request->validated('dashboard_color') ? 'manual' : null,
                'is_active' => $request->validated('is_active'),
            ];

            if ($node->legacy_type && $node->legacy_id) {
                $legacy = $this->legacyModel($node);
                $legacy?->update($values);
                $this->taxonomy->syncLegacyTree();
                $node->refresh();
            } else {
                $node->update($values);
            }

            $this->auditLogger->record('asset_category_node.updated', $node, $before, $this->auditValues($node));
        });

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(AssetCategoryNode $assetCategoryNode): RedirectResponse
    {
        Gate::authorize('delete', $assetCategoryNode);

        DB::transaction(function () use ($assetCategoryNode): void {
            $node = AssetCategoryNode::query()->lockForUpdate()->findOrFail($assetCategoryNode->id);
            $blockers = [
                'kategori turunan' => $node->children()->count(),
                'aset' => $node->assets()->withTrashed()->count(),
                'alias sumber' => $node->legacy_type
                    ? AssetCategorySourceAlias::query()->where('category_type', $node->legacy_type)->where('category_id', $node->legacy_id)->count()
                    : 0,
            ];
            $blockers = array_filter($blockers);
            if ($blockers !== []) {
                $details = collect($blockers)->map(fn (int $count, string $label): string => "{$count} {$label}")->implode(', ');
                throw ValidationException::withMessages(['category' => "Kategori masih digunakan oleh {$details}. Nonaktifkan bila ingin mempertahankan riwayat."]);
            }

            $before = $this->auditValues($node);
            if ($node->legacy_type && $node->legacy_id) {
                $this->legacyModel($node)?->delete();
                $this->taxonomy->syncLegacyTree();
            } else {
                $node->delete();
            }
            $this->auditLogger->record('asset_category_node.deleted', $node, $before, []);
        });

        return to_route('admin.asset-categories.index')->with('success', 'Kategori berhasil dihapus.');
    }

    private function createLegacyNode(int $position, ?AssetCategoryNode $parent, array $values): AssetCategoryNode
    {
        $attributes = [
            'name' => $values['name'],
            'sort_order' => $values['sort_order'],
            'dashboard_color' => $values['dashboard_color'],
            'dashboard_color_source' => $values['dashboard_color'] ? 'manual' : null,
            'is_active' => true,
        ];

        $legacy = match ($position) {
            1 => AssetGroup::query()->create($attributes),
            2 => AssetSystem::query()->create([
                ...$attributes,
                'asset_group_id' => $this->requiredLegacyParent($parent, 'group'),
            ]),
            3 => AssetSubsystem::query()->create([
                ...$attributes,
                'asset_system_id' => $this->requiredLegacyParent($parent, 'system'),
            ]),
        };
        $this->taxonomy->syncLegacyTree();

        return $this->taxonomy->nodeForLegacy(match ($position) {
            1 => 'group', 2 => 'system', 3 => 'subsystem'
        }, $legacy->id);
    }

    private function requiredLegacyParent(?AssetCategoryNode $parent, string $type): int
    {
        if (! $parent || $parent->legacy_type !== $type || ! $parent->legacy_id) {
            throw ValidationException::withMessages(['parent_id' => 'Parent kategori tidak kompatibel dengan struktur Excel.']);
        }

        return $parent->legacy_id;
    }

    private function legacyModel(AssetCategoryNode $node): AssetGroup|AssetSystem|AssetSubsystem|null
    {
        return match ($node->legacy_type) {
            'group' => AssetGroup::query()->find($node->legacy_id),
            'system' => AssetSystem::query()->find($node->legacy_id),
            'subsystem' => AssetSubsystem::query()->find($node->legacy_id),
            default => null,
        };
    }

    private function assertUniqueSibling(int $levelId, ?int $parentId, string $name, ?int $ignoreId = null): void
    {
        $duplicate = AssetCategoryNode::query()
            ->where('asset_category_level_id', $levelId)
            ->where('parent_id', $parentId)
            ->where('normalized_name', mb_strtolower($name))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan pada parent ini.']);
        }
    }

    private function auditValues(AssetCategoryNode $node): array
    {
        return $node->only([
            'id', 'asset_category_level_id', 'parent_id', 'name', 'sort_order',
            'dashboard_color', 'is_active', 'legacy_type', 'legacy_id',
        ]);
    }
}
