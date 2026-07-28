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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-28 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_record_or_correct_stock_movements(): void
    {
        $movement = StockMovement::factory()->create();

        $this->post(route('stock-movements.store'), [])->assertRedirect(route('login'));
        $this->post(route('stock-movements.correct', $movement), [])->assertRedirect(route('login'));
    }

    public function test_regional_user_records_movement_for_own_unit_and_input_is_trimmed(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $part = SparePart::factory()->create();
        InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => 10]);

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.store'), $this->storePayload($part, [
                'type' => 'out',
                'direction' => 'out',
                'quantity' => 3,
                'reference_number' => '  WO-001  ',
                'notes' => "  Penggantian   relay  \n  jalur A  ",
            ]))
            ->assertRedirect('/inventory')
            ->assertSessionHas('success', 'Transaksi stok berhasil dicatat.');

        $this->assertDatabaseHas('inventory_stocks', [
            'unit_kerja_id' => $unit->id,
            'spare_part_id' => $part->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'unit_kerja_id' => $unit->id,
            'spare_part_id' => $part->id,
            'actor_id' => $user->id,
            'reference_number' => 'WO-001',
            'notes' => 'Penggantian relay jalur A',
        ]);
    }

    public function test_regional_user_cannot_submit_a_unit_id_or_move_other_unit_stock(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($ownUnit)->create();
        $part = SparePart::factory()->create();
        InventoryStock::factory()->for($otherUnit)->for($part)->create(['quantity' => 10]);

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.store'), $this->storePayload($part, [
                'unit_kerja_id' => $otherUnit->id,
            ]))
            ->assertRedirect('/inventory')
            ->assertSessionHasErrors('unit_kerja_id');

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $otherUnit->inventoryStocks()->sole()->quantity);
    }

    public function test_pusat_must_choose_an_active_unit_and_can_record_for_that_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $activeUnit = UnitKerja::factory()->create();
        $inactiveUnit = UnitKerja::factory()->create(['is_active' => false]);
        $part = SparePart::factory()->create();

        $this->actingAs($pusat)
            ->from('/inventory')
            ->post(route('stock-movements.store'), $this->storePayload($part))
            ->assertSessionHasErrors('unit_kerja_id');

        $this->actingAs($pusat)
            ->from('/inventory')
            ->post(route('stock-movements.store'), $this->storePayload($part, ['unit_kerja_id' => $inactiveUnit->id]))
            ->assertSessionHasErrors('unit_kerja_id');

        $this->actingAs($pusat)
            ->post(route('stock-movements.store'), $this->storePayload($part, ['unit_kerja_id' => $activeUnit->id]))
            ->assertRedirect('/inventory');

        $this->assertDatabaseHas('inventory_stocks', [
            'unit_kerja_id' => $activeUnit->id,
            'spare_part_id' => $part->id,
            'quantity' => 5,
        ]);
    }

    public function test_store_rejects_invalid_domains_future_dates_and_oversized_text(): void
    {
        $user = User::factory()->unit()->create();
        $part = SparePart::factory()->create();

        foreach ([
            [['type' => 'in', 'direction' => 'out'], 'direction'],
            [['type' => 'out', 'direction' => 'in'], 'direction'],
            [['type' => 'opening', 'direction' => 'out'], 'direction'],
            [['type' => 'correction', 'direction' => 'in'], 'type'],
        ] as [$invalidPair, $errorKey]) {
            $this->actingAs($user)
                ->from('/inventory')
                ->post(route('stock-movements.store'), $this->storePayload($part, $invalidPair))
                ->assertSessionHasErrors($errorKey);
        }

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.store'), $this->storePayload($part, [
                'movement_date' => '2026-07-29',
                'reference_number' => str_repeat('R', 101),
                'notes' => str_repeat('N', 1001),
            ]))
            ->assertSessionHasErrors(['movement_date', 'reference_number', 'notes']);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_store_rejects_inactive_and_soft_deleted_spareparts(): void
    {
        $user = User::factory()->unit()->create();
        $inactive = SparePart::factory()->create(['is_active' => false]);
        $deleted = SparePart::factory()->create();
        $deleted->delete();

        foreach ([$inactive, $deleted] as $part) {
            $this->actingAs($user)
                ->from('/inventory')
                ->post(route('stock-movements.store'), $this->storePayload($part))
                ->assertSessionHasErrors('spare_part_id');
        }

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_store_required_validation_is_friendly_and_localized(): void
    {
        $user = User::factory()->unit()->create();

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.store'), [])
            ->assertSessionHasErrors([
                'spare_part_id' => 'Pilih suku cadang.',
                'type' => 'Pilih jenis transaksi.',
                'direction' => 'Pilih arah transaksi.',
                'quantity' => 'Masukkan jumlah transaksi.',
                'movement_date' => 'Pilih tanggal transaksi.',
                'idempotency_key' => 'Kunci transaksi tidak tersedia. Tutup lalu buka kembali formulir.',
            ]);
    }

    public function test_store_is_idempotent_for_an_identical_retry(): void
    {
        $user = User::factory()->unit()->create();
        $part = SparePart::factory()->create();
        $payload = $this->storePayload($part);

        $this->actingAs($user)->post(route('stock-movements.store'), $payload)->assertRedirect('/inventory');
        $this->actingAs($user)->post(route('stock-movements.store'), $payload)->assertRedirect('/inventory');

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(5, InventoryStock::query()->sole()->quantity);
    }

    public function test_correction_copies_source_scope_and_supports_an_inactive_historical_part(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $part = SparePart::factory()->create();
        InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => 10]);
        $source = StockMovement::factory()
            ->for($unit)
            ->for($part)
            ->for($user, 'actor')
            ->create([
                'type' => StockMovementType::Opening,
                'direction' => StockDirection::In,
                'quantity' => 10,
                'stock_before' => 0,
                'stock_after' => 10,
            ]);
        $sourceAttributes = (array) DB::table('stock_movements')->where('id', $source->id)->first();
        $part->update(['is_active' => false]);

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.correct', $source), array_replace($this->correctionPayload(), [
                'unit_kerja_id' => UnitKerja::factory()->create()->id,
                'spare_part_id' => SparePart::factory()->create()->id,
            ]))
            ->assertSessionHasErrors(['unit_kerja_id', 'spare_part_id']);

        $this->actingAs($user)
            ->post(route('stock-movements.correct', $source), $this->correctionPayload())
            ->assertRedirect('/inventory')
            ->assertSessionHas('success', 'Koreksi stok berhasil dicatat.');
        $this->actingAs($user)
            ->post(route('stock-movements.correct', $source), $this->correctionPayload())
            ->assertRedirect('/inventory');

        $correction = StockMovement::query()->where('type', StockMovementType::Correction)->sole();
        $this->assertSame($unit->id, $correction->unit_kerja_id);
        $this->assertSame($part->id, $correction->spare_part_id);
        $this->assertSame($source->id, $correction->reverses_movement_id);
        $this->assertSame(8, $correction->stock_after);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Correction)->count());
        $this->assertSame($sourceAttributes, (array) DB::table('stock_movements')->where('id', $source->id)->first());
    }

    public function test_correction_rejects_a_correction_source_and_cross_unit_lookup_is_404(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($ownUnit)->create();
        $part = SparePart::factory()->create();
        InventoryStock::factory()->for($ownUnit)->for($part)->create(['quantity' => 5]);
        $original = StockMovement::factory()->for($ownUnit)->for($part)->for($user, 'actor')->create();
        $correction = StockMovement::factory()->for($ownUnit)->for($part)->for($user, 'actor')->create([
            'type' => StockMovementType::Correction,
            'direction' => StockDirection::In,
            'reverses_movement_id' => $original->id,
        ]);
        $otherMovement = StockMovement::factory()->for($otherUnit)->for($part)->create();

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.correct', $correction), $this->correctionPayload())
            ->assertSessionHasErrors('movement');

        $this->actingAs($user)
            ->post(route('stock-movements.correct', $otherMovement), $this->correctionPayload())
            ->assertNotFound();
    }

    public function test_correction_required_validation_is_friendly_and_localized(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $part = SparePart::factory()->create();
        $source = StockMovement::factory()->for($unit)->for($part)->for($user, 'actor')->create();

        $this->actingAs($user)
            ->from('/inventory')
            ->post(route('stock-movements.correct', $source), [])
            ->assertSessionHasErrors([
                'direction' => 'Pilih arah koreksi.',
                'quantity' => 'Masukkan jumlah koreksi.',
                'movement_date' => 'Pilih tanggal koreksi.',
                'idempotency_key' => 'Kunci transaksi tidak tersedia. Tutup lalu buka kembali formulir.',
            ]);
    }

    public function test_inactive_user_is_logged_out_before_inventory_mutation(): void
    {
        $user = User::factory()->unit()->inactive()->create();
        $part = SparePart::factory()->create();

        $this->actingAs($user)
            ->post(route('stock-movements.store'), $this->storePayload($part))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('stock_movements', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function storePayload(SparePart $part, array $overrides = []): array
    {
        return array_replace([
            'spare_part_id' => $part->id,
            'type' => 'in',
            'direction' => 'in',
            'quantity' => 5,
            'movement_date' => '2026-07-28',
            'reference_number' => 'IN-001',
            'notes' => 'Penerimaan stok',
            'idempotency_key' => '98d4bb31-49f7-4e04-af74-e1b884de0b63',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function correctionPayload(): array
    {
        return [
            'direction' => 'out',
            'quantity' => 2,
            'movement_date' => '2026-07-28',
            'notes' => 'Koreksi hasil pemeriksaan',
            'idempotency_key' => '558de641-9789-4f83-84dc-fc02ce0f7fa5',
        ];
    }
}
