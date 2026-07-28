<?php

namespace App\Services;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function record(
        UnitKerja $unit,
        SparePart $part,
        User $actor,
        StockMovementType $type,
        StockDirection $direction,
        int $quantity,
        CarbonInterface $movementDate,
        ?string $referenceNumber,
        ?string $notes,
        string $idempotencyKey,
        ?StockMovement $reverses = null,
    ): StockMovement {
        $this->authorizeActor($actor, $unit);
        $this->validateMovement($unit, $part, $type, $direction, $quantity, $reverses);

        try {
            return DB::transaction(function () use ($unit, $part, $actor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $idempotencyKey, $reverses): StockMovement {
                $existing = StockMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $this->resolveIdempotentMovement($existing, $unit, $part, $actor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $reverses);
                }

                $timestamp = now();
                InventoryStock::query()->insertOrIgnore([
                    'unit_kerja_id' => $unit->id,
                    'spare_part_id' => $part->id,
                    'quantity' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $stock = InventoryStock::query()
                    ->where('unit_kerja_id', $unit->id)
                    ->where('spare_part_id', $part->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingAfterLock = StockMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingAfterLock) {
                    return $this->resolveIdempotentMovement($existingAfterLock, $unit, $part, $actor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $reverses);
                }

                $before = $stock->quantity;
                $after = $direction->apply($before, $quantity);
                if ($after < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok keluar melebihi stok tersedia.',
                    ]);
                }

                $movement = StockMovement::query()->create([
                    'unit_kerja_id' => $unit->id,
                    'spare_part_id' => $part->id,
                    'actor_id' => $actor->id,
                    'type' => $type,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'movement_date' => $movementDate->toDateString(),
                    'reference_number' => $referenceNumber,
                    'notes' => $notes,
                    'reverses_movement_id' => $reverses?->id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $stock->update(['quantity' => $after]);
                $this->auditLogger->record(
                    action: 'stock.movement_created',
                    subject: $movement,
                    before: ['quantity' => $before],
                    after: ['quantity' => $after],
                    actor: $actor,
                );

                return $movement;
            }, 5);
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyCollision($exception)) {
                throw $exception;
            }

            $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->resolveIdempotentMovement($existing, $unit, $part, $actor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $reverses);
        }
    }

    private function authorizeActor(User $actor, UnitKerja $unit): void
    {
        if (! $actor->is_active || (! $actor->isPusat() && (! $actor->isUnit() || $actor->unit_kerja_id !== $unit->id))) {
            throw new AuthorizationException('Pengguna tidak berhak mencatat transaksi untuk unit kerja ini.');
        }
    }

    private function validateMovement(
        UnitKerja $unit,
        SparePart $part,
        StockMovementType $type,
        StockDirection $direction,
        int $quantity,
        ?StockMovement $reverses,
    ): void {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah minimal 1.']);
        }

        $validDirection = match ($type) {
            StockMovementType::In, StockMovementType::Opening => $direction === StockDirection::In,
            StockMovementType::Out => $direction === StockDirection::Out,
            StockMovementType::Correction => true,
        };
        if (! $validDirection) {
            throw ValidationException::withMessages(['direction' => 'Arah transaksi tidak sesuai dengan jenis transaksi.']);
        }

        if ($type === StockMovementType::Correction) {
            if (! $reverses || $reverses->unit_kerja_id !== $unit->id || $reverses->spare_part_id !== $part->id) {
                throw ValidationException::withMessages([
                    'reverses_movement_id' => 'Koreksi harus merujuk transaksi dari unit dan suku cadang yang sama.',
                ]);
            }

            return;
        }

        if ($reverses) {
            throw ValidationException::withMessages([
                'reverses_movement_id' => 'Referensi koreksi hanya dapat digunakan untuk transaksi koreksi.',
            ]);
        }
    }

    private function resolveIdempotentMovement(
        StockMovement $existing,
        UnitKerja $unit,
        SparePart $part,
        User $actor,
        StockMovementType $type,
        StockDirection $direction,
        int $quantity,
        CarbonInterface $movementDate,
        ?string $referenceNumber,
        ?string $notes,
        ?StockMovement $reverses,
    ): StockMovement {
        $samePayload = $existing->unit_kerja_id === $unit->id
            && $existing->spare_part_id === $part->id
            && $existing->actor_id === $actor->id
            && $existing->type === $type
            && $existing->direction === $direction
            && $existing->quantity === $quantity
            && $existing->movement_date->toDateString() === $movementDate->toDateString()
            && $existing->reference_number === $referenceNumber
            && $existing->notes === $notes
            && $existing->reverses_movement_id === $reverses?->id;

        if (! $samePayload) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Kunci idempotensi sudah digunakan untuk transaksi yang berbeda.',
            ]);
        }

        return $existing;
    }

    private function isIdempotencyCollision(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000'
            && str_contains(strtolower($exception->getMessage()), 'idempotency_key');
    }
}
