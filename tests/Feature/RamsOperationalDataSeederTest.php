<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\ReliabilitySummary;
use App\Models\RiskMatrix;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\RamsOperationalDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RamsOperationalDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_seed_is_complete_idempotent_and_preserves_existing_users(): void
    {
        $admin = User::factory()->pusat()->create([
            'username' => 'admin.seed',
            'is_active' => true,
        ]);

        $this->seed(RamsOperationalDataSeeder::class);
        $firstCounts = $this->counts();
        $firstQuantities = InventoryStock::query()
            ->orderBy('spare_part_id')
            ->pluck('quantity', 'spare_part_id')
            ->all();

        $this->assertSame([
            'units' => 13,
            'assets' => 34,
            'risk_matrices' => 10,
            'risk_registers' => 8,
            'reliability_summaries' => 5,
            'failure_logs' => 8,
            'spare_parts' => 41,
            'inventory_stocks' => 41,
            'stock_movements' => 41,
        ], $firstCounts);
        $this->assertTrue(User::query()->whereKey($admin->id)->exists());

        $this->seed(RamsOperationalDataSeeder::class);

        $this->assertSame($firstCounts, $this->counts());
        $this->assertSame(
            $firstQuantities,
            InventoryStock::query()->orderBy('spare_part_id')->pluck('quantity', 'spare_part_id')->all(),
        );
        $this->assertTrue(User::query()->whereKey($admin->id)->exists());
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'units' => UnitKerja::query()->count(),
            'assets' => Asset::query()->count(),
            'risk_matrices' => RiskMatrix::query()->count(),
            'risk_registers' => RiskRegister::query()->count(),
            'reliability_summaries' => ReliabilitySummary::query()->count(),
            'failure_logs' => FailureLog::query()->count(),
            'spare_parts' => SparePart::query()->count(),
            'inventory_stocks' => InventoryStock::query()->count(),
            'stock_movements' => StockMovement::query()->count(),
        ];
    }
}
