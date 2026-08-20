<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnlimitedAssetTaxonomySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_levels_and_generic_taxonomy_schema_exist(): void
    {
        $this->assertTrue(Schema::hasTable('asset_category_levels'));
        $this->assertTrue(Schema::hasTable('asset_category_nodes'));
        $this->assertTrue(Schema::hasColumn('assets', 'asset_category_node_id'));

        $this->assertSame(
            ['Aset Prasarana Sintel', 'System', 'Subsystem'],
            AssetCategoryLevel::query()->orderBy('position')->pluck('name')->all(),
        );
    }

    public function test_nodes_support_unlimited_parent_depth_and_asset_links(): void
    {
        $levelFour = AssetCategoryLevel::factory()->create(['name' => 'Perangkat', 'position' => 4]);
        $levelFive = AssetCategoryLevel::factory()->create(['name' => 'Komponen', 'position' => 5]);
        $parent = AssetCategoryNode::factory()->create(['asset_category_level_id' => $levelFour->id]);
        $child = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => $levelFive->id,
            'parent_id' => $parent->id,
        ]);
        $asset = Asset::factory()->create(['asset_category_node_id' => $child->id]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($asset->assetCategoryNode->is($child));
        $this->assertTrue($child->assets->contains($asset));
    }
}
