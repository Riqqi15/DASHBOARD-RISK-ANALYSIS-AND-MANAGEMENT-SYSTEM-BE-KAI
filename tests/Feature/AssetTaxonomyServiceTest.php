<?php

namespace Tests\Feature;

use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Services\AssetTaxonomyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssetTaxonomyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_synchronizes_the_legacy_tree_idempotently(): void
    {
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();
        $service = app(AssetTaxonomyService::class);

        $first = $service->syncLegacyTree();
        $second = $service->syncLegacyTree();

        $this->assertSame(3, $first['nodes']);
        $this->assertSame(3, $second['nodes']);
        $this->assertSame(3, AssetCategoryNode::query()->count());
        $this->assertSame(
            $subsystem->id,
            AssetCategoryNode::query()->where('legacy_type', 'subsystem')->sole()->legacy_id,
        );
    }

    public function test_it_supports_unlimited_depth_and_returns_the_subtree(): void
    {
        $service = app(AssetTaxonomyService::class);
        $root = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 1)->value('id'),
        ]);
        $parent = $root;

        foreach (range(2, 7) as $position) {
            $level = AssetCategoryLevel::query()->firstOrCreate(
                ['position' => $position],
                ['name' => "Level {$position}", 'is_active' => true],
            );
            $parent = $service->createNode($level, $parent, "Node {$position}", 0);
        }

        $this->assertSame(7, AssetCategoryLevel::query()->count());
        $this->assertCount(7, $service->subtreeIds($root));
        $this->assertSame(7, $service->path($parent)->count());
    }

    public function test_it_rejects_a_parent_from_a_non_adjacent_level(): void
    {
        $service = app(AssetTaxonomyService::class);
        $root = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 1)->value('id'),
        ]);
        $levelThree = AssetCategoryLevel::query()->where('position', 3)->firstOrFail();

        $this->expectException(ValidationException::class);
        $service->createNode($levelThree, $root, 'Lompat level', 0);
    }
}
