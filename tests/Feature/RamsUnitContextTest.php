<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RamsUnitContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_area_selection_persists_across_rams_modules(): void
    {
        $pusat = User::factory()->pusat()->create();
        UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2']);

        $this->actingAs($pusat)
            ->get('/dashboard?area=DAOP-2')
            ->assertOk()
            ->assertSessionHas('rams.active_unit_id', $daopTwo->id)
            ->assertInertia(fn (Assert $page) => $page->where('selected_area', 'DAOP-2'));

        $this->get('/risk-matrix')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected_area', 'DAOP-2'));

        $this->get('/master-asset')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page->where('filters.unit_kerja_id', (string) $daopTwo->id),
            );

        $this->get('/inventory')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page->where('filters.unit_kerja_id', (string) $daopTwo->id),
            );
    }

    public function test_pusat_numeric_selection_updates_the_shared_rams_context(): void
    {
        $pusat = User::factory()->pusat()->create();
        UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $divre = UnitKerja::factory()->create(['code' => 'DIVRE-III']);

        $this->actingAs($pusat)
            ->get('/master-asset?unit_kerja_id='.$divre->id)
            ->assertOk()
            ->assertSessionHas('rams.active_unit_id', $divre->id);

        $this->get('/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected_area', 'DIVRE-III'));
    }

    public function test_regional_account_cannot_override_its_assigned_unit(): void
    {
        $ownUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $regional = User::factory()->unit($ownUnit)->create();

        $this->actingAs($regional)
            ->get('/dashboard?area=DAOP-2')
            ->assertRedirect()
            ->assertSessionHasErrors('area');

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected_area', 'DAOP-1'));
    }
}
