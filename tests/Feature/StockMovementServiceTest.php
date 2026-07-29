<?php

namespace Tests\Feature;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_creates_stock_movement_materialized_balance_and_explicit_actor_audit(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $authenticatedUser = User::factory()->pusat()->create();
        $this->actingAs($authenticatedUser);

        $movement = $this->service()->record(
            unit: $unit,
            part: $part,
            actor: $actor,
            type: StockMovementType::In,
            direction: StockDirection::In,
            quantity: 7,
            movementDate: Carbon::parse('2026-07-28'),
            referenceNumber: 'IN-001',
            notes: 'Penerimaan awal',
            idempotencyKey: 'cf10ea43-345e-471e-98f9-2e0678900560',
        );

        $this->assertSame(0, $movement->stock_before);
        $this->assertSame(7, $movement->stock_after);
        $this->assertSame(7, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
        $audit = AuditLog::query()->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame($unit->id, $audit->unit_kerja_id);
        $this->assertSame(['quantity' => 0], $audit->old_values);
        $this->assertSame(['quantity' => 7], $audit->new_values);
        $this->assertArrayNotHasKey('idempotency_key', $audit->old_values);
        $this->assertArrayNotHasKey('idempotency_key', $audit->new_values);
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_opening_increases_stock_and_out_decreases_it(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();

        $opening = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 10, '35c064df-d9cf-40df-805f-0f79d61ae2c6');
        $out = $this->record($unit, $part, $actor, StockMovementType::Out, StockDirection::Out, 4, 'e02c9957-ac2d-46a1-84d4-10ca824741a6');

        $this->assertSame(0, $opening->stock_before);
        $this->assertSame(10, $opening->stock_after);
        $this->assertSame(10, $out->stock_before);
        $this->assertSame(6, $out->stock_after);
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_opening_is_authoritative_and_rejected_after_the_first_ledger_movement(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();

        $first = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 4, (string) Str::uuid());
        $this->assertSame(4, $first->stock_after);

        try {
            $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 2, (string) Str::uuid());
            $this->fail('Expected a second opening to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(4, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
    }

    public function test_out_rejects_insufficient_stock_without_partial_write(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 3, '3e949554-5d79-4e15-8f1b-c5d3383a2f13');

        try {
            $this->record($unit, $part, $actor, StockMovementType::Out, StockDirection::Out, 4, '6a887dcf-7ff6-4f70-a9a5-34c641322159');
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(3, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_repeated_identical_idempotency_key_returns_original_without_changing_balance_or_audit(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $key = 'cd8741cb-87d5-4a0f-ac7c-31e95029c62a';

        $first = $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 5, $key);
        $retry = $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 5, $key);

        $this->assertTrue($retry->is($first));
        $this->assertSame(5, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $key = '4e90f696-e61f-4737-bd7a-02bd2a0a8d6b';
        $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 5, $key);

        try {
            $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 6, $key);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertSame(5, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_correction_creates_linked_movement_without_mutating_original(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $original = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 10, '779a22b4-9050-4de7-be9f-240a9489fbf9');
        $originalAttributes = (array) DB::table('stock_movements')->where('id', $original->id)->first();

        $correction = $this->service()->record(
            unit: $unit,
            part: $part,
            actor: $actor,
            type: StockMovementType::Correction,
            direction: StockDirection::Out,
            quantity: 2,
            movementDate: Carbon::parse('2026-07-28'),
            referenceNumber: null,
            notes: 'Koreksi saldo awal',
            idempotencyKey: '314a3f59-84ea-4d0d-ad32-7b81c81c1cb0',
            reverses: $original,
        );

        $this->assertSame($original->id, $correction->reverses_movement_id);
        $this->assertSame(StockMovementType::Correction, $correction->type);
        $this->assertSame(8, $correction->stock_after);
        $this->assertSame($originalAttributes, (array) DB::table('stock_movements')->where('id', $original->id)->first());
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_service_rejects_invalid_type_direction_and_correction_domains(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $otherPart = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $original = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 5, 'b206a4f3-cb20-493e-909b-aa859ce5ca65');

        foreach ([
            [StockMovementType::In, StockDirection::Out, null],
            [StockMovementType::Opening, StockDirection::Out, null],
            [StockMovementType::Correction, StockDirection::In, null],
            [StockMovementType::In, StockDirection::In, $original],
        ] as $index => [$type, $direction, $reverses]) {
            try {
                $this->service()->record($unit, $part, $actor, $type, $direction, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $reverses);
                $this->fail("Expected validation failure for invalid case {$index}.");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        try {
            $this->service()->record($otherUnit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $original);
            $this->fail('Expected correction scope validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reverses_movement_id', $exception->errors());
        }

        try {
            $this->service()->record($unit, $otherPart, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $original);
            $this->fail('Expected correction part validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reverses_movement_id', $exception->errors());
        }

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_quantity_must_be_at_least_one_without_creating_an_empty_stock_row(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();

        try {
            $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 0, (string) Str::uuid());
            $this->fail('Expected quantity validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_stocks', 0);
    }

    public function test_unit_actor_cannot_record_for_another_unit_and_inactive_actor_cannot_record(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $unitActor = User::factory()->unit($ownUnit)->create();
        $inactiveActor = User::factory()->pusat()->inactive()->create();

        foreach ([[$unitActor, $otherUnit], [$inactiveActor, $ownUnit]] as [$actor, $unit]) {
            try {
                $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 1, (string) Str::uuid());
                $this->fail('Expected AuthorizationException.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_stocks', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_dirty_actor_role_cannot_bypass_authoritative_unit_scope(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->unit($ownUnit)->create();
        $actor->role = UserRole::Pusat;
        $actor->unit_kerja_id = null;

        $this->expectException(AuthorizationException::class);

        try {
            $this->record($otherUnit, $part, $actor, StockMovementType::In, StockDirection::In, 1, (string) Str::uuid());
        } finally {
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertDatabaseCount('inventory_stocks', 0);
        }
    }

    public function test_stale_actor_cannot_bypass_database_deactivation_and_unsaved_actor_is_rejected(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $staleActor = User::factory()->pusat()->create();
        User::query()->whereKey($staleActor->id)->update(['is_active' => false]);

        foreach ([$staleActor, User::factory()->pusat()->make()] as $actor) {
            try {
                $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 1, (string) Str::uuid());
                $this->fail('Expected authoritative actor rejection.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_stocks', 0);
    }

    public function test_stale_unit_and_part_cannot_bypass_database_deactivation_for_normal_movement(): void
    {
        $actor = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        UnitKerja::query()->whereKey($unit->id)->update(['is_active' => false]);
        SparePart::query()->whereKey($part->id)->update(['is_active' => false]);

        foreach ([
            [$unit, SparePart::factory()->create(), 'unit_kerja_id'],
            [UnitKerja::factory()->create(), $part, 'spare_part_id'],
        ] as [$candidateUnit, $candidatePart, $field]) {
            try {
                $this->record($candidateUnit, $candidatePart, $actor, StockMovementType::In, StockDirection::In, 1, (string) Str::uuid());
                $this->fail("Expected {$field} validation failure.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_stocks', 0);
    }

    public function test_unsaved_unit_and_part_are_rejected_before_any_ledger_write(): void
    {
        $actor = User::factory()->pusat()->create();
        $persistedUnit = UnitKerja::factory()->create();
        $persistedPart = SparePart::factory()->create();

        foreach ([
            [UnitKerja::factory()->make(), $persistedPart, 'unit_kerja_id'],
            [$persistedUnit, SparePart::factory()->make(), 'spare_part_id'],
        ] as [$unit, $part, $field]) {
            try {
                $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 1, (string) Str::uuid());
                $this->fail("Expected unsaved {$field} validation failure.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_stocks', 0);
    }

    public function test_idempotent_retry_revalidates_authoritative_part_state(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $key = (string) Str::uuid();
        $original = $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 2, $key);
        SparePart::query()->whereKey($part->id)->update(['is_active' => false]);

        try {
            $this->record($unit, $part, $actor, StockMovementType::In, StockDirection::In, 2, $key);
            $this->fail('Expected inactive authoritative part rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('spare_part_id', $exception->errors());
        }

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(2, $original->stock_after);
        $this->assertSame(2, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
    }

    public function test_correction_allows_inactive_historical_part_but_rejects_a_second_adjustment_to_original(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $original = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 10, (string) Str::uuid());
        SparePart::query()->whereKey($part->id)->update(['is_active' => false]);

        $first = $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::Out, 2, Carbon::parse('2026-07-28'), null, 'Koreksi pertama', (string) Str::uuid(), $original);

        try {
            $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, 'Koreksi kedua', (string) Str::uuid(), $original);
            $this->fail('Expected a repeated correction validation failure.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Transaksi sumber sudah pernah dikoreksi.',
                $exception->errors()['reverses_movement_id'][0] ?? null,
            );
        }

        $this->assertSame($original->id, $first->reverses_movement_id);
        $this->assertSame(8, InventoryStock::query()->whereBelongsTo($unit)->whereBelongsTo($part)->value('quantity'));
        $this->assertSame(1, StockMovement::query()->where('reverses_movement_id', $original->id)->count());
        $this->assertLedgerMatchesStock($unit, $part);
    }

    public function test_correction_rejects_trashed_part_dirty_missing_and_unsaved_source(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $original = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 5, (string) Str::uuid());

        $dirty = $original->replicate();
        $dirty->exists = true;
        $dirty->id = $original->id;
        $dirty->unit_kerja_id = UnitKerja::factory()->create()->id;
        try {
            $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $dirty);
            $this->fail('Expected dirty source validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reverses_movement_id', $exception->errors());
        }

        $unsaved = new StockMovement([
            'unit_kerja_id' => $unit->id,
            'spare_part_id' => $part->id,
            'type' => StockMovementType::Opening,
        ]);
        try {
            $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $unsaved);
            $this->fail('Expected unsaved source validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reverses_movement_id', $exception->errors());
        }

        $missing = $original->replicate();
        $missing->id = $original->id + 1_000_000;
        $missing->exists = true;
        $missing->syncOriginal();
        try {
            $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $missing);
            $this->fail('Expected missing source validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reverses_movement_id', $exception->errors());
        }

        $replacement = StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create();
        $part->delete();
        try {
            $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $replacement);
            $this->fail('Expected trashed part validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('spare_part_id', $exception->errors());
        }
    }

    public function test_correction_cannot_reverse_another_correction(): void
    {
        $unit = UnitKerja::factory()->create();
        $part = SparePart::factory()->create();
        $actor = User::factory()->pusat()->create();
        $original = $this->record($unit, $part, $actor, StockMovementType::Opening, StockDirection::In, 5, (string) Str::uuid());
        $correction = $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::Out, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $original);

        $this->expectException(ValidationException::class);

        $this->service()->record($unit, $part, $actor, StockMovementType::Correction, StockDirection::In, 1, Carbon::parse('2026-07-28'), null, null, (string) Str::uuid(), $correction);
    }

    public function test_stock_movement_model_rejects_update_delete_and_force_delete(): void
    {
        $movement = StockMovement::factory()->create(['notes' => 'Asli']);

        foreach (['update', 'delete', 'forceDelete'] as $operation) {
            try {
                match ($operation) {
                    'update' => $movement->update(['notes' => 'Diubah']),
                    'delete' => $movement->delete(),
                    'forceDelete' => $movement->forceDelete(),
                };
                $this->fail("Expected immutable movement {$operation} guard.");
            } catch (\LogicException $exception) {
                $this->assertSame('Ledger mutasi stok bersifat immutable.', $exception->getMessage());
            }
        }

        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'notes' => 'Asli']);
    }

    public function test_concurrent_out_never_overspends_and_keeps_ledger_consistent(): void
    {
        $scope = 'movement-out-'.Str::lower((string) Str::uuid());
        $barrier = storage_path("framework/testing/{$scope}");
        $processes = [];

        try {
            $setup = $this->setupConcurrencyFixture('setup-out', $scope);
            foreach ([1, 2] as $worker) {
                $process = $this->movementProcess([
                    'record', (string) $setup['unit_id'], (string) $setup['part_id'], (string) $setup['actor_id'],
                    'out', 'out', '4', (string) Str::uuid(), $barrier, (string) $worker,
                ]);
                $process->start();
                $processes[(string) $worker] = $process;
            }

            $this->releaseBarrier($barrier, $processes);
            $results = $this->waitForProcesses($processes);

            $this->assertSame(1, collect($results)->where('success', true)->count());
            $this->assertSame(1, collect($results)->where('validation', true)->count());
            $this->assertSame(1, InventoryStock::query()->where('unit_kerja_id', $setup['unit_id'])->where('spare_part_id', $setup['part_id'])->value('quantity'));
            $this->assertSame(2, StockMovement::query()->where('unit_kerja_id', $setup['unit_id'])->where('spare_part_id', $setup['part_id'])->count());
            $this->assertLedgerMatchesStockIds($setup['unit_id'], $setup['part_id']);
        } finally {
            $this->cleanupConcurrencyFixture($scope, $barrier, $processes);
        }
    }

    public function test_concurrent_identical_in_on_absent_stock_creates_one_stock_one_movement_and_one_audit(): void
    {
        $scope = 'movement-idempotent-'.Str::lower((string) Str::uuid());
        $barrier = storage_path("framework/testing/{$scope}");
        $processes = [];
        $key = (string) Str::uuid();

        try {
            $setup = $this->setupConcurrencyFixture('setup-empty', $scope);
            foreach ([1, 2] as $worker) {
                $process = $this->movementProcess([
                    'record', (string) $setup['unit_id'], (string) $setup['part_id'], (string) $setup['actor_id'],
                    'in', 'in', '5', $key, $barrier, (string) $worker,
                ]);
                $process->start();
                $processes[(string) $worker] = $process;
            }

            $this->releaseBarrier($barrier, $processes);
            $results = $this->waitForProcesses($processes);

            $this->assertSame(2, collect($results)->where('success', true)->count());
            $this->assertCount(1, collect($results)->pluck('movement_id')->unique());
            $this->assertSame(1, InventoryStock::query()->where('unit_kerja_id', $setup['unit_id'])->where('spare_part_id', $setup['part_id'])->count());
            $this->assertSame(5, InventoryStock::query()->where('unit_kerja_id', $setup['unit_id'])->where('spare_part_id', $setup['part_id'])->value('quantity'));
            $this->assertSame(1, StockMovement::query()->where('idempotency_key', $key)->count());
            $this->assertSame(1, AuditLog::query()->where('action', 'stock.movement_created')->where('unit_kerja_id', $setup['unit_id'])->count());
            $this->assertLedgerMatchesStockIds($setup['unit_id'], $setup['part_id']);
        } finally {
            $this->cleanupConcurrencyFixture($scope, $barrier, $processes);
        }
    }

    private function service(): StockMovementService
    {
        return app(StockMovementService::class);
    }

    private function record(UnitKerja $unit, SparePart $part, User $actor, StockMovementType $type, StockDirection $direction, int $quantity, string $key): StockMovement
    {
        return $this->service()->record($unit, $part, $actor, $type, $direction, $quantity, Carbon::parse('2026-07-28'), null, null, $key);
    }

    private function assertLedgerMatchesStock(UnitKerja $unit, SparePart $part): void
    {
        $this->assertLedgerMatchesStockIds($unit->id, $part->id);
    }

    private function assertLedgerMatchesStockIds(int $unitId, int $partId): void
    {
        $ledger = StockMovement::query()->where('unit_kerja_id', $unitId)->where('spare_part_id', $partId)->get()
            ->sum(fn (StockMovement $movement): int => $movement->direction === StockDirection::In ? $movement->quantity : -$movement->quantity);
        $stock = (int) InventoryStock::query()->where('unit_kerja_id', $unitId)->where('spare_part_id', $partId)->value('quantity');

        $this->assertSame($ledger, $stock);
    }

    /** @return array{unit_id: int, part_id: int, actor_id: int} */
    private function setupConcurrencyFixture(string $action, string $scope): array
    {
        $process = $this->movementProcess([$action, $scope]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $arguments */
    private function movementProcess(array $arguments): Process
    {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/StockMovementProcess.php'), ...$arguments],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '3307',
                'DB_DATABASE' => 'rams_testing',
            ],
        );
        $process->setTimeout(20);

        return $process;
    }

    /** @param array<string, Process> $processes */
    private function releaseBarrier(string $barrier, array $processes): void
    {
        File::ensureDirectoryExists(dirname($barrier));
        $deadline = microtime(true) + 10;
        foreach (array_keys($processes) as $worker) {
            while (! File::exists("{$barrier}.{$worker}.ready")) {
                if ($processes[$worker]->isTerminated()) {
                    $this->fail("Stock movement worker {$worker} exited before the barrier: ".$processes[$worker]->getErrorOutput());
                }
                if (microtime(true) >= $deadline) {
                    $this->fail('Timed out waiting for stock movement workers.');
                }
                usleep(10_000);
            }
        }
        File::put("{$barrier}.go", 'go');
    }

    /** @param array<string, Process> $processes
     * @return array<string, array{success: bool, validation?: bool, movement_id?: int}>
     */
    private function waitForProcesses(array $processes): array
    {
        $results = [];
        foreach ($processes as $worker => $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), trim($process->getErrorOutput().' '.$process->getOutput()));
            $results[$worker] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    /** @param array<string, Process> $processes */
    private function cleanupConcurrencyFixture(string $scope, string $barrier, array $processes): void
    {
        File::put("{$barrier}.go", 'go');
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        $cleanup = $this->movementProcess(['cleanup', $scope]);
        $cleanup->mustRun();
        File::delete(array_merge(
            ["{$barrier}.go"],
            collect(array_keys($processes))->map(fn (string $worker): string => "{$barrier}.{$worker}.ready")->all(),
        ));
    }
}
