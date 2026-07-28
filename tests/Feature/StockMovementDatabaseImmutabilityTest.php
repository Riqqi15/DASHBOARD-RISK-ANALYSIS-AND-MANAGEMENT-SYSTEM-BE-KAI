<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class StockMovementDatabaseImmutabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertMysqlTestDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET @rams_allow_stock_movement_mutation = NULL');
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_database_blocks_instance_quiet_bulk_and_table_mutations(): void
    {
        $movement = StockMovement::factory()->create(['notes' => 'Asli']);

        foreach ([
            fn () => $movement->update(['notes' => 'Instance']),
            fn () => $movement->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected model immutability rejection.');
            } catch (LogicException $exception) {
                $this->assertSame('Ledger mutasi stok bersifat immutable.', $exception->getMessage());
                $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'notes' => 'Asli']);
            }
        }

        foreach ([
            fn () => StockMovement::query()->findOrFail($movement->id)->updateQuietly(['notes' => 'Quiet']),
            fn () => StockMovement::query()->findOrFail($movement->id)->deleteQuietly(),
            fn () => StockMovement::query()->whereKey($movement->id)->update(['notes' => 'Bulk']),
            fn () => StockMovement::query()->whereKey($movement->id)->delete(),
            fn () => DB::table('stock_movements')->where('id', $movement->id)->update(['notes' => 'Table']),
            fn () => DB::table('stock_movements')->where('id', $movement->id)->delete(),
        ] as $operation) {
            $this->assertTriggerRejects($operation);
            $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'notes' => 'Asli']);
        }
    }

    public function test_migration_up_and_down_toggle_both_immutable_triggers(): void
    {
        $migration = $this->immutableMigration();
        $movement = StockMovement::factory()->create(['notes' => 'Asli']);
        $this->assertSame(2, $this->immutableTriggerCount());

        $migration->down();

        $this->assertSame(0, $this->immutableTriggerCount());
        $this->assertSame(1, DB::table('stock_movements')->where('id', $movement->id)->update(['notes' => 'Diizinkan saat down']));

        $migration->up();

        $this->assertSame(2, $this->immutableTriggerCount());
        $this->assertTriggerRejects(fn () => DB::table('stock_movements')->where('id', $movement->id)->delete());
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'notes' => 'Diizinkan saat down']);
    }

    private function immutableMigration(): Migration
    {
        $path = database_path('migrations/2026_07_28_000007_make_stock_movements_immutable.php');

        $this->assertFileExists($path, 'The immutable-ledger migration has not been implemented.');

        return require $path;
    }

    private function immutableTriggerCount(): int
    {
        return (int) DB::scalar(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = DATABASE()
              AND TRIGGER_NAME IN ('stock_movements_prevent_update', 'stock_movements_prevent_delete')
            SQL);
    }

    private function assertTriggerRejects(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('Expected immutable ledger trigger rejection.');
        } catch (QueryException $exception) {
            $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            $this->assertStringContainsString('stock_movements ledger is immutable', $exception->getMessage());
        }
    }

    private function assertMysqlTestDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame(3307, (int) config('database.connections.mysql.port'));
        $this->assertSame('rams_testing', config('database.connections.mysql.database'));
    }
}
