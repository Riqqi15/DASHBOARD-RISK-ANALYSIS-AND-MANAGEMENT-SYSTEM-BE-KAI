<?php

namespace Tests\Feature;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\AssetSubsystem;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_schema_contains_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('spare_parts', [
            'id',
            'asset_subsystem_id',
            'code',
            'source_key',
            'equipment',
            'detail_equipment',
            'max_yearly_failure',
            'average_yearly_failure',
            'max_lead_time_months',
            'average_lead_time_months',
            'safety_stock',
            'lead_time_demand',
            'reorder_point',
            'severity',
            'unit_of_measure',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('inventory_stocks', [
            'id',
            'unit_kerja_id',
            'spare_part_id',
            'quantity',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('stock_movements', [
            'id',
            'unit_kerja_id',
            'spare_part_id',
            'actor_id',
            'type',
            'direction',
            'quantity',
            'stock_before',
            'stock_after',
            'movement_date',
            'reference_number',
            'notes',
            'reverses_movement_id',
            'idempotency_key',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_inventory_relations_and_movement_casts_are_available(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->for($unit)->unit()->create();
        $stock = InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => 10]);
        $movement = StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create([
            'type' => StockMovementType::In,
            'direction' => StockDirection::In,
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);

        $this->assertTrue($stock->unitKerja->is($unit));
        $this->assertTrue($stock->sparePart->is($part));
        $this->assertSame(StockMovementType::In, $movement->type);
        $this->assertSame(StockDirection::In, $movement->direction);
        $this->assertTrue($movement->actor->is($actor));
        $this->assertTrue($movement->unitKerja->is($unit));
        $this->assertTrue($movement->sparePart->is($part));
        $this->assertTrue($unit->inventoryStocks->contains($stock));
        $this->assertTrue($unit->stockMovements->contains($movement));
        $this->assertTrue($actor->stockMovements->contains($movement));
        $this->assertTrue($part->inventoryStocks->contains($stock));
        $this->assertTrue($part->stockMovements->contains($movement));
        $this->assertTrue($part->assetSubsystem->spareParts->contains($part));
    }

    public function test_inventory_scopes_only_limit_regional_users_to_their_unit(): void
    {
        $ownUser = User::factory()->unit()->create();
        $otherUnit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $ownStock = InventoryStock::factory()->for($ownUser->unitKerja)->for($part)->create();
        $otherStock = InventoryStock::factory()->for($otherUnit)->for($part)->create();
        $ownMovement = StockMovement::factory()->for($ownUser->unitKerja)->for($part)->for($ownUser, 'actor')->create();
        $otherActor = User::factory()->unit($otherUnit)->create();
        $otherMovement = StockMovement::factory()->for($otherUnit)->for($part)->for($otherActor, 'actor')->create();
        $pusat = User::factory()->pusat()->create();

        $this->assertSame([$ownStock->id], InventoryStock::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertSame([$ownMovement->id], StockMovement::query()->visibleTo($ownUser)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$ownStock->id, $otherStock->id],
            InventoryStock::query()->visibleTo($pusat)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$ownMovement->id, $otherMovement->id],
            StockMovement::query()->visibleTo($pusat)->pluck('id')->all(),
        );
    }

    public function test_stock_direction_applies_signed_quantities(): void
    {
        $this->assertSame(14, StockDirection::In->apply(10, 4));
        $this->assertSame(6, StockDirection::Out->apply(10, 4));
    }

    public function test_inventory_unique_keys_and_restricting_foreign_keys_are_enforced(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create(['code' => 'SP-001', 'source_key' => 'source-001']);
        InventoryStock::factory()->for($unit)->for($part)->create();

        $this->assertMysqlError(1062, fn () => SparePart::factory()->create(['code' => 'SP-001']));
        $this->assertMysqlError(1062, fn () => SparePart::factory()->create(['source_key' => 'source-001']));
        $this->assertMysqlError(1062, fn () => InventoryStock::factory()->for($unit)->for($part)->create());

        $actor = User::factory()->unit($unit)->create();
        StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create([
            'idempotency_key' => '98d4bb31-49f7-4e04-af74-e1b884de0b63',
        ]);
        $this->assertMysqlError(1062, fn () => StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create([
            'idempotency_key' => '98d4bb31-49f7-4e04-af74-e1b884de0b63',
        ]));
        $this->assertMysqlError(1451, fn () => $unit->forceDelete());
        $this->assertMysqlError(1451, fn () => $part->forceDelete());
    }

    public function test_sparepart_values_are_cast_and_soft_deleted(): void
    {
        $subsystem = AssetSubsystem::factory()->create();
        $part = SparePart::factory()->for($subsystem)->create([
            'max_yearly_failure' => '4.25',
            'average_yearly_failure' => '2.50',
            'max_lead_time_months' => '3.00',
            'average_lead_time_months' => '2.25',
            'safety_stock' => 8,
            'lead_time_demand' => 5,
            'reorder_point' => 13,
            'is_active' => 1,
        ]);

        $this->assertSame('4.25', $part->max_yearly_failure);
        $this->assertSame('2.50', $part->average_yearly_failure);
        $this->assertSame('3.00', $part->max_lead_time_months);
        $this->assertSame('2.25', $part->average_lead_time_months);
        $this->assertSame(8, $part->safety_stock);
        $this->assertSame(5, $part->lead_time_demand);
        $this->assertSame(13, $part->reorder_point);
        $this->assertTrue($part->is_active);

        $part->delete();

        $this->assertSoftDeleted('spare_parts', ['id' => $part->id]);
    }

    private function assertMysqlError(int $expectedErrorNumber, Closure $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame($expectedErrorNumber, $exception->errorInfo[1] ?? null);

            return;
        }

        $this->fail('Expected the database to reject the query.');
    }
}
