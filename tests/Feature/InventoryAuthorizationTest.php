<?php

namespace Tests\Feature;

use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use App\Policies\InventoryStockPolicy;
use App\Policies\SparePartPolicy;
use App\Policies\StockMovementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class InventoryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_policies_are_discovered_for_their_models(): void
    {
        $this->assertInstanceOf(SparePartPolicy::class, Gate::getPolicyFor(SparePart::class));
        $this->assertInstanceOf(InventoryStockPolicy::class, Gate::getPolicyFor(InventoryStock::class));
        $this->assertInstanceOf(StockMovementPolicy::class, Gate::getPolicyFor(StockMovement::class));
    }

    public function test_pusat_can_manage_global_spareparts_and_access_every_units_inventory(): void
    {
        $pusat = User::factory()->pusat()->create();
        $part = SparePart::factory()->create();
        $stock = InventoryStock::factory()->for($part)->create();
        $movement = StockMovement::factory()
            ->for($stock->unitKerja)
            ->for($part)
            ->for($pusat, 'actor')
            ->create();

        $this->assertTrue($pusat->can('viewAny', SparePart::class));
        $this->assertTrue($pusat->can('view', $part));
        $this->assertTrue($pusat->can('create', SparePart::class));
        $this->assertTrue($pusat->can('update', $part));
        $this->assertTrue($pusat->can('delete', $part));

        $this->assertTrue($pusat->can('viewAny', InventoryStock::class));
        $this->assertTrue($pusat->can('view', $stock));
        $this->assertTrue($pusat->can('createMovement', $stock));
        $this->assertTrue($pusat->can('viewAny', StockMovement::class));
        $this->assertTrue($pusat->can('view', $movement));
        $this->assertTrue($pusat->can('correct', $movement));
    }

    public function test_unit_user_only_sees_and_moves_own_unit_stock(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $ownUser = User::factory()->unit($ownUnit)->create();
        $part = SparePart::factory()->create();
        $ownStock = InventoryStock::factory()->for($ownUnit)->for($part)->create();
        $otherStock = InventoryStock::factory()->for($otherUnit)->for($part)->create();
        $ownMovement = StockMovement::factory()
            ->for($ownUnit)
            ->for($part)
            ->for($ownUser, 'actor')
            ->create();
        $otherMovement = StockMovement::factory()
            ->for($otherUnit)
            ->for($part)
            ->for(User::factory()->unit($otherUnit), 'actor')
            ->create();

        $this->assertSame([$ownStock->id], InventoryStock::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertTrue($ownUser->can('view', $ownStock));
        $this->assertFalse($ownUser->can('view', $otherStock));
        $this->assertTrue($ownUser->can('createMovement', $ownStock));
        $this->assertFalse($ownUser->can('createMovement', $otherStock));

        $this->assertSame([$ownMovement->id], StockMovement::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertTrue($ownUser->can('view', $ownMovement));
        $this->assertFalse($ownUser->can('view', $otherMovement));
        $this->assertTrue($ownUser->can('correct', $ownMovement));
        $this->assertFalse($ownUser->can('correct', $otherMovement));

        $this->assertTrue($ownUser->can('viewAny', SparePart::class));
        $this->assertTrue($ownUser->can('view', $part));
        $this->assertFalse($ownUser->can('create', SparePart::class));
        $this->assertFalse($ownUser->can('update', $part));
        $this->assertFalse($ownUser->can('delete', $part));
    }

    public function test_stock_movements_are_immutable_for_every_role(): void
    {
        $unit = UnitKerja::factory()->create();
        $unitUser = User::factory()->unit($unit)->create();
        $pusat = User::factory()->pusat()->create();
        $movement = StockMovement::factory()
            ->for($unit)
            ->for($unitUser, 'actor')
            ->create();

        foreach ([$pusat, $unitUser] as $user) {
            $this->assertFalse($user->can('update', $movement));
            $this->assertFalse($user->can('delete', $movement));
        }
    }

    public function test_inactive_accounts_cannot_access_inventory_capabilities(): void
    {
        $inactivePusat = User::factory()->pusat()->inactive()->create();
        $inactiveUnit = User::factory()->unit()->inactive()->create();
        $part = SparePart::factory()->create();
        $stock = InventoryStock::factory()->for($inactiveUnit->unitKerja)->for($part)->create();
        $movement = StockMovement::factory()
            ->for($inactiveUnit->unitKerja)
            ->for($part)
            ->for($inactiveUnit, 'actor')
            ->create();

        foreach ([$inactivePusat, $inactiveUnit] as $user) {
            $this->assertFalse($user->can('viewAny', SparePart::class));
            $this->assertFalse($user->can('view', $part));
            $this->assertFalse($user->can('create', SparePart::class));
            $this->assertFalse($user->can('view', $stock));
            $this->assertFalse($user->can('createMovement', $stock));
            $this->assertFalse($user->can('view', $movement));
            $this->assertFalse($user->can('correct', $movement));
        }
    }
}
