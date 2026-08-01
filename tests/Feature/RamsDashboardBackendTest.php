<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\RamsOperationalDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RamsDashboardBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_pages_read_seeded_database_with_area_authorization(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $this->seed(RamsOperationalDataSeeder::class);

        $this->actingAs($pusat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->has('assets', 34)
                ->where('summary.totalAset', 34)
                ->where('selected_area', null));

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
                ->has('assets', 17)
                ->where('summary.totalAset', 17)
                ->where('selected_area', 'DAOP-1'));

        $this->actingAs($regional)
            ->get('/dashboard?area=DIVRE4')
            ->assertSessionHasErrors('area');

        $this->assertTrue($pusat->exists);
    }
}
