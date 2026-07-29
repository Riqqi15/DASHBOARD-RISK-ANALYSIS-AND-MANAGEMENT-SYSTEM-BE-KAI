<?php

namespace Tests\Feature;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_inventory_stock_equals_the_signed_sum_of_its_ledger(): void
    {
        $unit = UnitKerja::factory()->create();
        $actor = User::factory()->for($unit, 'unitKerja')->create();
        $firstPart = SparePart::factory()->create();
        $secondPart = SparePart::factory()->create();

        InventoryStock::factory()->for($unit)->for($firstPart)->create(['quantity' => 7]);
        InventoryStock::factory()->for($unit)->for($secondPart)->create(['quantity' => 4]);

        StockMovement::factory()->for($unit)->for($firstPart)->for($actor, 'actor')->create([
            'type' => StockMovementType::Opening,
            'direction' => StockDirection::In,
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);
        StockMovement::factory()->for($unit)->for($firstPart)->for($actor, 'actor')->create([
            'type' => StockMovementType::Out,
            'direction' => StockDirection::Out,
            'quantity' => 3,
            'stock_before' => 10,
            'stock_after' => 7,
        ]);
        StockMovement::factory()->for($unit)->for($secondPart)->for($actor, 'actor')->create([
            'type' => StockMovementType::Opening,
            'direction' => StockDirection::In,
            'quantity' => 4,
            'stock_before' => 0,
            'stock_after' => 4,
        ]);

        InventoryStock::query()->each(function (InventoryStock $stock): void {
            $ledger = StockMovement::query()
                ->where('unit_kerja_id', $stock->unit_kerja_id)
                ->where('spare_part_id', $stock->spare_part_id)
                ->get()
                ->sum(fn (StockMovement $movement): int => $movement->direction === StockDirection::In
                    ? $movement->quantity
                    : -$movement->quantity);

            $this->assertSame(
                $ledger,
                $stock->quantity,
                "Stock mismatch for inventory_stocks.id={$stock->id}",
            );
        });
    }
}
