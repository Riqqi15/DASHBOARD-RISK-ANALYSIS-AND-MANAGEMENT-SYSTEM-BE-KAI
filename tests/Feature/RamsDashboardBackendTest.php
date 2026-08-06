<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\RamsOperationalDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RamsDashboardBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_asset_category_tree_without_assets(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        AssetGroup::query()->create([
            'name' => '1234',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($pusat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->has('asset_categories', 1)
                ->where('asset_categories.0.name', '1234')
                ->has('asset_categories.0.systems', 0)
                ->has('assets', 0));
    }

    public function test_pusat_dashboard_defaults_to_first_active_unit_instead_of_national_scope(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        UnitKerja::factory()->create(['code' => 'DIVRE-IV', 'is_active' => true]);

        $this->actingAs($pusat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->where('selected_area', 'DAOP-1'));
    }

    public function test_dashboard_pages_read_seeded_database_with_area_authorization(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $this->seed(RamsOperationalDataSeeder::class);

        $this->actingAs($pusat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->where('selected_area', 'DAOP-1')
                ->where('summary.totalAset', 17)
                ->has('assets', 17));

        $this->actingAs($pusat)
            ->get('/risk-matrix?area=DIVRE4')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/RiskMatrix')
                ->where('selected_area', 'DIVRE-IV')
                ->has('risks', 5));

        $this->actingAs($pusat)
            ->get('/inventory?area=DAOP1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/inventory/Inventory')
                ->where('selected_area', 'DAOP-1')
                ->has('items', 41));

        $this->actingAs($pusat)->get('/dashboard?area=UNKNOWN')->assertNotFound();
    }

    public function test_regional_user_is_limited_to_its_own_unit(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $this->seed(RamsOperationalDataSeeder::class);
        $unit = UnitKerja::query()->where('code', 'DAOP-1')->sole();
        $regional = User::factory()->unit($unit)->create(['is_active' => true]);

        $this->actingAs($regional)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->where('summary.totalAset', 17)
                ->has('assets', 17)
                ->where('selected_area', 'DAOP-1'));

        $this->actingAs($regional)
            ->get('/dashboard?area=DIVRE4')
            ->assertSessionHasErrors('area');

        $this->assertTrue($pusat->exists);
    }
}
