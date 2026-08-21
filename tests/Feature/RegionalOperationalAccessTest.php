<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\InventoryStock;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegionalOperationalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_daop_one_account_is_locked_to_its_operational_scope_across_modules(): void
    {
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2', 'is_active' => true]);
        $user = User::factory()->unit($daopOne)->create(['username' => 'daop1']);
        $ownAsset = Asset::factory()->for($daopOne)->create(['nama_aset' => 'Aset DAOP-1']);
        $otherAsset = Asset::factory()->for($daopTwo)->create(['nama_aset' => 'Aset DAOP-2']);
        RiskRegister::factory()->for($ownAsset)->create(['risk_event' => 'Risiko DAOP-1']);
        RiskRegister::factory()->for($otherAsset)->create(['risk_event' => 'Risiko DAOP-2']);
        $part = SparePart::factory()->create();
        InventoryStock::factory()->for($daopOne)->for($part)->create(['quantity' => 3]);
        InventoryStock::factory()->for($daopTwo)->for($part)->create(['quantity' => 99]);

        $this->actingAs($user)->get('/dashboard?area=DAOP-2')
            ->assertSessionHasErrors('area');

        $this->actingAs($user)->get('/master-asset?unit_kerja_id='.$daopTwo->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.unit_kerja_id', (string) $daopOne->id)
                ->has('assets.data', 1)
                ->where('assets.data.0.nama_aset', 'Aset DAOP-1'));

        $this->actingAs($user)->get('/risk-register?area=DAOP-2')
            ->assertSessionHasErrors('area');

        $this->actingAs($user)->get('/risk-register')
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_choose_unit', false)
                ->has('registers', 1)
                ->where('registers.0.risk_event', 'Risiko DAOP-1'));

        $this->actingAs($user)->get('/inventory?unit_kerja_id='.$daopTwo->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.choose_unit', false)
                ->where('can.manage_master', false)
                ->where('filters.unit_kerja_id', (string) $daopOne->id)
                ->where('stats.total_quantity', 3)
                ->has('stocks.data', 1));

        $this->actingAs($user)->get('/trouble-report/import')
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_choose_unit', false)
                ->where('selected_unit_id', $daopOne->id)
                ->where('units', []));

        $this->actingAs($user)->get('/admin/units')->assertForbidden();
        $this->actingAs($user)->post('/admin/spare-parts', [])->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function regionalCodes(): array
    {
        return collect(range(1, 9))->mapWithKeys(fn (int $number): array => [
            "DAOP-{$number}" => ["DAOP-{$number}"],
        ])->merge([
            'DIVRE-I' => ['DIVRE-I'],
            'DIVRE-II' => ['DIVRE-II'],
            'DIVRE-III' => ['DIVRE-III'],
            'DIVRE-IV' => ['DIVRE-IV'],
        ])->all();
    }

    #[DataProvider('regionalCodes')]
    public function test_each_regional_account_resolves_only_its_assigned_unit(string $code): void
    {
        $unit = UnitKerja::factory()->create(['code' => $code, 'is_active' => true]);
        $other = UnitKerja::factory()->create(['code' => 'TEST-'.$code, 'is_active' => true]);
        $user = User::factory()->unit($unit)->create();
        Asset::factory()->for($unit)->create();
        Asset::factory()->for($other)->create();

        $this->actingAs($user)->get('/master-asset?unit_kerja_id='.$other->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.unit_kerja_id', (string) $unit->id)
                ->has('assets.data', 1));
    }
}
