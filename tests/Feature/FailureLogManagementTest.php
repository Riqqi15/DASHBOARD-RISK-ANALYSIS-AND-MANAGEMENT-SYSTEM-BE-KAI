<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\ReliabilitySummary;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailureLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_user_can_store_an_idempotent_failure_log_and_consume_stock(): void
    {
        [$user, $asset, $part, $stock] = $this->context(5);
        $payload = $this->payload($asset, $part);

        $this->actingAs($user)->post('/trouble-report', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $failure = FailureLog::query()->sole();
        $this->assertSame($asset->id, $failure->asset_id);
        $this->assertSame($part->id, $failure->spare_part_id);
        $this->assertSame(120, $failure->downtime_minutes);
        $this->assertSame(3, $stock->fresh()->quantity);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Out)->count());
        $this->assertSame(1, ReliabilitySummary::query()->where('asset_id', $asset->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'failure_log.created')->count());

        $this->actingAs($user)->post('/trouble-report', $payload)->assertRedirect();

        $this->assertSame(1, FailureLog::query()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Out)->count());
        $this->assertSame(3, $stock->fresh()->quantity);
    }

    public function test_cross_unit_asset_is_forbidden(): void
    {
        [$user, $asset, $part] = $this->context(5);
        $otherUnit = UnitKerja::factory()->create();
        $outsider = User::factory()->unit($otherUnit)->create(['is_active' => true]);

        $this->actingAs($outsider)
            ->post('/trouble-report', $this->payload($asset, $part))
            ->assertForbidden();

        $this->assertDatabaseCount('failure_logs', 0);
        $this->assertTrue($user->exists);
    }

    public function test_insufficient_stock_rolls_back_the_failure_log(): void
    {
        [$user, $asset, $part, $stock] = $this->context(1);

        $this->actingAs($user)
            ->post('/trouble-report', $this->payload($asset, $part))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('failure_logs', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1, $stock->fresh()->quantity);
    }

    public function test_resolved_time_must_not_precede_started_time(): void
    {
        [$user, $asset, $part] = $this->context(5);
        $payload = $this->payload($asset, $part);
        $payload['resolved_at'] = '2026-07-10 07:00:00';

        $this->actingAs($user)
            ->post('/trouble-report', $payload)
            ->assertSessionHasErrors('resolved_at');

        $this->assertDatabaseCount('failure_logs', 0);
    }

    /** @return array{User, Asset, SparePart, InventoryStock} */
    private function context(int $stockQuantity): array
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create(['is_active' => true]);
        $asset = Asset::factory()->for($unit)->create(['jumlah_unit' => 2]);
        $part = SparePart::factory()->for($asset->assetSubsystem)->create(['is_active' => true]);
        $stock = InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => $stockQuantity]);

        return [$user, $asset, $part, $stock];
    }

    /** @return array<string, mixed> */
    private function payload(Asset $asset, SparePart $part): array
    {
        return [
            'asset_id' => $asset->id,
            'location' => 'Stasiun Gambir',
            'resort' => 'Resor 1.2',
            'qc' => 'QC-01',
            'failure_event' => 'Track Circuit Failure',
            'cause' => 'Isolasi rel rusak',
            'action_taken' => 'Penggantian isolasi',
            'started_at' => '2026-07-10 08:00:00',
            'resolved_at' => '2026-07-10 10:00:00',
            'spare_part_replaced' => true,
            'spare_part_id' => $part->id,
            'spare_part_quantity' => 2,
            'vandalism' => false,
            'idempotency_key' => 'd977deeb-ddd8-4c8d-8b4e-18e805696b7d',
        ];
    }
}
