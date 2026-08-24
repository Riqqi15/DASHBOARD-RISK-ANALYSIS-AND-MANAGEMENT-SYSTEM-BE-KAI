<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use App\Models\FailureLog;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetTaxonomyRegionalAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_selects_and_counts_data_from_the_requested_region(): void
    {
        $pusat = User::factory()->pusat()->create();
        $firstUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $secondUnit = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $level = AssetCategoryLevel::query()->where('position', 1)->firstOrFail();
        $firstNode = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => $level->id,
            'parent_id' => null,
        ]);
        $secondNode = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => $level->id,
            'parent_id' => null,
        ]);
        Asset::factory()->create([
            'unit_kerja_id' => $firstUnit->id,
            'asset_subsystem_id' => null,
            'asset_category_node_id' => $firstNode->id,
        ]);
        Asset::factory()->create([
            'unit_kerja_id' => $secondUnit->id,
            'asset_subsystem_id' => null,
            'asset_category_node_id' => $secondNode->id,
        ]);

        $this->actingAs($pusat)
            ->get("/admin/asset-categories?unit_kerja_id={$secondUnit->id}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('selectedUnitId', $secondUnit->id)
                    ->where('selectedNodeId', $secondNode->id)
                    ->where('assets.data.0.unit_kerja_id', $secondUnit->id)
                    ->where('nodes', function ($nodes) use ($firstNode, $secondNode): bool {
                        $byId = collect($nodes)->keyBy('id');

                        return ! $byId->has($firstNode->id) && $byId[$secondNode->id]['subtree_assets_count'] === 1;
                    }),
            );
    }

    public function test_category_menu_starts_at_level_one_but_explicit_node_links_stay_specific(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $root = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 1)->value('id'),
            'parent_id' => null,
            'name' => 'PERALATAN DALAM SINYAL ELEKTRIK',
        ]);
        $system = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 2)->value('id'),
            'parent_id' => $root->id,
            'name' => 'INTERLOCKING ELEKTRIK',
        ]);
        $subsystem = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 3)->value('id'),
            'parent_id' => $system->id,
            'name' => 'INTERLOCKING ELEKTRIK',
        ]);
        Asset::factory()->create([
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => null,
            'asset_category_node_id' => $subsystem->id,
        ]);

        $this->actingAs($pusat)
            ->get("/admin/asset-categories?unit_kerja_id={$unit->id}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('selectedNodeId', $root->id)
                    ->where('assets.data.0.asset_category_node_id', $subsystem->id),
            );

        $this->actingAs($pusat)
            ->get("/admin/asset-categories?unit_kerja_id={$unit->id}&node={$subsystem->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selectedNodeId', $subsystem->id));
    }

    public function test_regional_asset_creation_is_locked_to_its_own_unit_and_accepts_any_level(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $node = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 1)->value('id'),
            'parent_id' => null,
            'name' => 'Peralatan Sintel',
        ]);

        $this->actingAs($user)
            ->post('/admin/asset-category-assets', [
                'unit_kerja_id' => $otherUnit->id,
                'asset_category_node_id' => $node->id,
                'nama_aset' => 'Aset Wilayah',
                'jumlah_unit' => 3,
                'status' => AssetStatus::Aktif->value,
            ])
            ->assertSessionHasErrors('unit_kerja_id');

        $this->actingAs($user)
            ->post('/admin/asset-category-assets', [
                'asset_category_node_id' => $node->id,
                'nama_aset' => 'Aset Wilayah',
                'jumlah_unit' => 3,
                'status' => AssetStatus::Aktif->value,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('assets', [
            'unit_kerja_id' => $unit->id,
            'asset_category_node_id' => $node->id,
            'asset_subsystem_id' => null,
            'aset_prasarana_sintel' => 'Peralatan Sintel',
        ]);
    }

    public function test_new_root_asset_appears_in_its_category_and_dashboard_group(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $node = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 1)->value('id'),
            'parent_id' => null,
            'name' => '1. PERALATAN DALAM SINYAL ELEKTRIK',
            'sort_order' => 1,
            'dashboard_color' => '#FFFF00',
        ]);

        $this->actingAs($pusat)
            ->post('/admin/asset-category-assets', [
                'unit_kerja_id' => $unit->id,
                'asset_category_node_id' => $node->id,
                'nama_aset' => 'INTERLOCKING ELEKTRIK',
                'jumlah_unit' => 2,
                'status' => AssetStatus::Aktif->value,
            ])
            ->assertRedirect(route('admin.asset-categories.index', [
                'unit_kerja_id' => $unit->id,
                'node' => $node->id,
            ]));

        $this->actingAs($pusat)
            ->get(route('admin.asset-categories.index', [
                'unit_kerja_id' => $unit->id,
                'node' => $node->id,
            ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('selectedNodeId', $node->id)
                    ->where('nodes.0.subtree_assets_count', 1)
                    ->where('assets.data.0.nama_aset', 'INTERLOCKING ELEKTRIK'),
            );

        $this->actingAs($pusat)
            ->get('/dashboard?area=DAOP1')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('assets', 1)
                    ->where('assets.0.aset_prasarana_sintel', '1. PERALATAN DALAM SINYAL ELEKTRIK')
                    ->has('summary.reliabilityGroups', 1)
                    ->where('summary.reliabilityGroups.0.code', 'PDSE')
                    ->where('summary.reliabilityGroups.0.asset_count', 1),
            );
    }

    public function test_subtree_archive_only_affects_selected_region_and_preserves_history(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $levelOne = AssetCategoryLevel::query()->where('position', 1)->firstOrFail();
        $levelTwo = AssetCategoryLevel::query()->where('position', 2)->firstOrFail();
        $root = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => $levelOne->id,
            'parent_id' => null,
        ]);
        $child = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => $levelTwo->id,
            'parent_id' => $root->id,
        ]);
        $first = Asset::factory()->create([
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => null,
            'asset_category_node_id' => $root->id,
        ]);
        $second = Asset::factory()->create([
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => null,
            'asset_category_node_id' => $child->id,
        ]);
        $untouched = Asset::factory()->create([
            'unit_kerja_id' => $otherUnit->id,
            'asset_subsystem_id' => null,
            'asset_category_node_id' => $child->id,
        ]);
        $failure = FailureLog::factory()->for($second)->create();

        $this->actingAs($pusat)
            ->getJson("/admin/asset-category-nodes/{$root->id}/archive-preview?unit_kerja_id={$unit->id}")
            ->assertOk()
            ->assertJsonPath('assets_count', 2)
            ->assertJsonPath('historical_records_count', 1);

        $this->actingAs($pusat)
            ->delete("/admin/asset-category-nodes/{$root->id}/assets", [
                'unit_kerja_id' => $unit->id,
                'confirmation' => 'HAPUS ASET WILAYAH',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($untouched);
        $this->assertDatabaseHas('failure_logs', ['id' => $failure->id, 'asset_id' => $second->id]);
        $this->assertSame($second->id, $failure->fresh()->asset->id);
    }
}
