<?php

namespace Tests\Feature\Admin;

use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnlimitedAssetTaxonomyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_can_append_levels_and_manage_deep_nodes(): void
    {
        $pusat = User::factory()->pusat()->create();

        $this->actingAs($pusat)->post('/admin/asset-category-levels', [
            'name' => 'Jenis Perangkat',
        ])->assertSessionDoesntHaveErrors();

        $level = AssetCategoryLevel::query()->where('position', 4)->firstOrFail();
        $parent = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 3)->value('id'),
        ]);

        $this->actingAs($pusat)->post('/admin/asset-category-nodes', [
            'asset_category_level_id' => $level->id,
            'parent_id' => $parent->id,
            'name' => 'Relay Room',
            'sort_order' => 2,
            'dashboard_color' => '#123ABC',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('asset_category_nodes', [
            'asset_category_level_id' => $level->id,
            'parent_id' => $parent->id,
            'normalized_name' => 'relay room',
            'dashboard_color' => '#123ABC',
        ]);
    }

    public function test_adding_a_level_reuses_the_next_soft_deleted_position(): void
    {
        $pusat = User::factory()->pusat()->create();
        $deleted = AssetCategoryLevel::factory()->create(['name' => 'Lama', 'position' => 4]);
        $deleted->delete();

        $this->actingAs($pusat)
            ->post('/admin/asset-category-levels', ['name' => 'Pengganti'])
            ->assertSessionDoesntHaveErrors();

        $restored = AssetCategoryLevel::query()->where('position', 4)->firstOrFail();
        $this->assertSame($deleted->id, $restored->id);
        $this->assertSame('Pengganti', $restored->name);
        $this->assertNull($restored->deleted_at);
    }

    public function test_adding_a_level_can_restore_a_soft_deleted_level_with_the_same_name(): void
    {
        $pusat = User::factory()->pusat()->create();
        $deleted = AssetCategoryLevel::factory()->create(['name' => 'Testing', 'position' => 4]);
        $deleted->delete();

        $this->actingAs($pusat)
            ->post('/admin/asset-category-levels', ['name' => 'testing'])
            ->assertSessionDoesntHaveErrors();

        $restored = AssetCategoryLevel::query()->where('position', 4)->firstOrFail();
        $this->assertSame($deleted->id, $restored->id);
        $this->assertSame('testing', $restored->name);
    }

    public function test_adding_a_duplicate_active_level_shows_a_readable_message(): void
    {
        $pusat = User::factory()->pusat()->create();
        AssetCategoryLevel::factory()->create(['name' => 'Testing', 'position' => 4]);

        $this->actingAs($pusat)
            ->post('/admin/asset-category-levels', ['name' => 'testing'])
            ->assertSessionHasErrors([
                'normalized_name' => 'Nama level sudah digunakan oleh level aktif.',
            ]);
    }

    public function test_regional_account_can_view_but_cannot_change_global_taxonomy(): void
    {
        $unit = User::factory()->unit()->create();

        $this->actingAs($unit)->get('/admin/asset-categories')->assertOk();
        $this->actingAs($unit)->post('/admin/asset-category-levels', ['name' => 'Rahasia'])->assertForbidden();
        $this->actingAs($unit)->post('/admin/asset-category-nodes', [
            'asset_category_level_id' => 1,
            'name' => 'Rahasia',
        ])->assertForbidden();
    }

    public function test_node_parent_must_come_from_the_previous_level(): void
    {
        $pusat = User::factory()->pusat()->create();
        $levelFour = AssetCategoryLevel::factory()->create(['position' => 4]);
        $wrongParent = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => AssetCategoryLevel::query()->where('position', 1)->value('id'),
        ]);

        $this->actingAs($pusat)->post('/admin/asset-category-nodes', [
            'asset_category_level_id' => $levelFour->id,
            'parent_id' => $wrongParent->id,
            'name' => 'Tidak valid',
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_pusat_can_delete_only_the_deepest_empty_custom_level(): void
    {
        $pusat = User::factory()->pusat()->create();
        $levelFour = AssetCategoryLevel::factory()->create(['position' => 4]);
        $levelFive = AssetCategoryLevel::factory()->create(['position' => 5]);

        $this->actingAs($pusat)
            ->delete("/admin/asset-category-levels/{$levelFour->id}")
            ->assertSessionHasErrors('level');

        $this->actingAs($pusat)
            ->delete("/admin/asset-category-levels/{$levelFive->id}")
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.asset-categories.index'));

        $this->assertSoftDeleted('asset_category_levels', ['id' => $levelFive->id]);
        $this->assertDatabaseHas('asset_category_levels', ['id' => $levelFour->id, 'deleted_at' => null]);
    }

    public function test_deleted_child_history_does_not_block_deleting_its_custom_parent(): void
    {
        $pusat = User::factory()->pusat()->create();
        $levelFour = AssetCategoryLevel::factory()->create(['position' => 4]);
        $levelFive = AssetCategoryLevel::factory()->create(['position' => 5]);
        $parent = AssetCategoryNode::factory()->create(['asset_category_level_id' => $levelFour->id]);
        $child = AssetCategoryNode::factory()->create([
            'asset_category_level_id' => $levelFive->id,
            'parent_id' => $parent->id,
        ]);
        $child->delete();

        $this->actingAs($pusat)
            ->delete("/admin/asset-category-nodes/{$parent->id}")
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.asset-categories.index'));

        $this->assertSoftDeleted('asset_category_nodes', ['id' => $parent->id]);
        $this->assertSoftDeleted('asset_category_nodes', ['id' => $child->id]);
    }

    public function test_deleted_node_history_does_not_block_deleting_its_deepest_level(): void
    {
        $pusat = User::factory()->pusat()->create();
        $level = AssetCategoryLevel::factory()->create(['position' => 4]);
        $node = AssetCategoryNode::factory()->create(['asset_category_level_id' => $level->id]);
        $node->delete();

        $this->actingAs($pusat)
            ->delete("/admin/asset-category-levels/{$level->id}")
            ->assertSessionDoesntHaveErrors();

        $this->assertSoftDeleted('asset_category_levels', ['id' => $level->id]);
        $this->assertSoftDeleted('asset_category_nodes', ['id' => $node->id]);
    }
}
