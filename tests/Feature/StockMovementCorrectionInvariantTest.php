<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementCorrectionInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_allows_multiple_normal_movements_but_only_one_correction_per_source(): void
    {
        $index = DB::selectOne(
            "SHOW INDEX FROM stock_movements WHERE Key_name = 'stock_movements_one_correction_per_source'",
        );
        $this->assertNotNull($index);
        $this->assertSame(0, (int) $index->Non_unique);

        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        StockMovement::factory()
            ->count(2)
            ->for($unit)
            ->for($part)
            ->for($actor, 'actor')
            ->create([
                'reverses_movement_id' => null,
            ]);
        $source = StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create();
        StockMovement::factory()
            ->for($unit)
            ->for($part)
            ->for($actor, 'actor')
            ->create([
                'type' => StockMovementType::Correction,
                'reverses_movement_id' => $source->id,
            ]);

        try {
            StockMovement::factory()
                ->for($unit)
                ->for($part)
                ->for($actor, 'actor')
                ->create([
                    'type' => StockMovementType::Correction,
                    'reverses_movement_id' => $source->id,
                ]);
            $this->fail('Expected the database correction invariant to reject a duplicate source.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0] ?? null);
        }

        $this->assertSame(3, StockMovement::query()->whereNull('reverses_movement_id')->count());
        $this->assertSame(1, StockMovement::query()->where('reverses_movement_id', $source->id)->count());
    }

    public function test_migration_down_and_up_cycles_preserve_the_foreign_key_lookup_index(): void
    {
        $migration = require database_path('migrations/2026_07_28_000008_enforce_single_stock_correction.php');

        foreach (range(1, 2) as $cycle) {
            $migration->down();

            $this->assertFalse(Schema::hasIndex('stock_movements', 'stock_movements_one_correction_per_source'));
            $this->assertTrue(
                Schema::hasIndex('stock_movements', 'stock_movements_reverses_movement_lookup'),
                "Fallback foreign-key index is missing after down cycle {$cycle}.",
            );

            $migration->up();

            $this->assertTrue(
                Schema::hasIndex('stock_movements', 'stock_movements_one_correction_per_source', 'unique'),
            );
            $this->assertFalse(
                Schema::hasIndex('stock_movements', 'stock_movements_reverses_movement_lookup'),
                "Fallback foreign-key index remains after up cycle {$cycle}.",
            );
        }
    }
}
