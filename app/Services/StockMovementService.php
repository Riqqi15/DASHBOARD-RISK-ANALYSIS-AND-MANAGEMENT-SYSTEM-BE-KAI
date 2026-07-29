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
        $this->validateStaticMovement($type, $direction, $quantity, $reverses);

        try {
            return DB::transaction(function () use ($unit, $part, $actor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $idempotencyKey, $reverses): StockMovement {
                [$authoritativeUnit, $authoritativePart, $authoritativeActor, $authoritativeReverses] = $this->authoritativeModels(
                    $unit,
                    $part,
                    $actor,
                    $type,
                    $reverses,
                );

                $existing = StockMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $this->resolveIdempotentMovement($existing, $authoritativeUnit, $authoritativePart, $authoritativeActor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $authoritativeReverses);
                }

                $timestamp = now();
                InventoryStock::query()->insertOrIgnore([
                    'unit_kerja_id' => $authoritativeUnit->id,
                    'spare_part_id' => $authoritativePart->id,
                    'quantity' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $stock = InventoryStock::query()
                    ->where('unit_kerja_id', $authoritativeUnit->id)
                    ->where('spare_part_id', $authoritativePart->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingAfterLock = StockMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingAfterLock) {
                    return $this->resolveIdempotentMovement($existingAfterLock, $authoritativeUnit, $authoritativePart, $authoritativeActor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $authoritativeReverses);
                }

                if ($type === StockMovementType::Opening && ($stock->quantity !== 0 || StockMovement::query()
                    ->where('unit_kerja_id', $authoritativeUnit->id)
                    ->where('spare_part_id', $authoritativePart->id)
                    ->exists())) {
                    throw ValidationException::withMessages([
                        'type' => 'Saldo awal hanya dapat dicatat sebagai transaksi pertama.',
                    ]);
                }

                $before = $stock->quantity;
                $after = $direction->apply($before, $quantity);
                if ($after < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok keluar melebihi stok tersedia.',
                    ]);
                }

                $movement = StockMovement::query()->create([
                    'unit_kerja_id' => $authoritativeUnit->id,
                    'spare_part_id' => $authoritativePart->id,
                    'actor_id' => $authoritativeActor->id,
                    'type' => $type,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'movement_date' => $movementDate->toDateString(),
                    'reference_number' => $referenceNumber,
                    'notes' => $notes,
                    'reverses_movement_id' => $authoritativeReverses?->id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $stock->update(['quantity' => $after]);
                $this->auditLogger->record(
                    action: 'stock.movement_created',
                    subject: $movement,
                    before: ['quantity' => $before],
                    after: ['quantity' => $after],
                    actor: $authoritativeActor,
                );

                return $movement;
            }, 5);
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyCollision($exception)) {
                throw $exception;
            }

            return DB::transaction(function () use ($exception, $unit, $part, $actor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $idempotencyKey, $reverses): StockMovement {
                [$authoritativeUnit, $authoritativePart, $authoritativeActor, $authoritativeReverses] = $this->authoritativeModels(
                    $unit,
                    $part,
                    $actor,
                    $type,
                    $reverses,
                );
                $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
                if (! $existing) {
                    throw $exception;
                }

                return $this->resolveIdempotentMovement($existing, $authoritativeUnit, $authoritativePart, $authoritativeActor, $type, $direction, $quantity, $movementDate, $referenceNumber, $notes, $authoritativeReverses);
            }, 5);
        }
    }

    /** @return array{UnitKerja, SparePart, User, StockMovement|null} */
    private function authoritativeModels(
        UnitKerja $unit,
        SparePart $part,
        User $actor,
        StockMovementType $type,
        ?StockMovement $reverses,
    ): array {
        if (! $actor->exists || $actor->getKey() === null) {
            throw new AuthorizationException('Pengguna pencatat transaksi tidak valid.');
        }
        $authoritativeActor = User::query()->whereKey($actor->getKey())->sharedLock()->first();
        if (! $authoritativeActor || ! $authoritativeActor->is_active) {
            throw new AuthorizationException('Pengguna pencatat transaksi tidak aktif atau tidak ditemukan.');
        }

        if (! $unit->exists || $unit->getKey() === null) {
            throw ValidationException::withMessages(['unit_kerja_id' => 'Unit kerja tidak valid.']);
        }
        $authoritativeUnit = UnitKerja::query()->whereKey($unit->getKey())->where('is_active', true)->sharedLock()->first();
        if (! $authoritativeUnit) {
            throw ValidationException::withMessages(['unit_kerja_id' => 'Unit kerja tidak aktif atau tidak ditemukan.']);
        }
        $this->authorizeActor($authoritativeActor, $authoritativeUnit);

        if (! $part->exists || $part->getKey() === null) {
            throw ValidationException::withMessages(['spare_part_id' => 'Suku cadang tidak valid.']);
        }
        $authoritativePart = SparePart::query()
            ->whereKey($part->getKey())
            ->when($type !== StockMovementType::Correction, fn ($query) => $query->where('is_active', true))
            ->sharedLock()
            ->first();
        if (! $authoritativePart) {
            throw ValidationException::withMessages(['spare_part_id' => 'Suku cadang tidak aktif, terhapus, atau tidak ditemukan.']);
        }

        $authoritativeReverses = null;
        if ($type === StockMovementType::Correction) {
            if (! $reverses || ! $reverses->exists || $reverses->getKey() === null || $reverses->isDirty()) {
                throw ValidationException::withMessages(['reverses_movement_id' => 'Transaksi sumber koreksi tidak valid.']);
            }
            $authoritativeReverses = StockMovement::query()->whereKey($reverses->getKey())->sharedLock()->first();
            if (! $authoritativeReverses
                || $authoritativeReverses->type === StockMovementType::Correction
                || $authoritativeReverses->unit_kerja_id !== $authoritativeUnit->id
                || $authoritativeReverses->spare_part_id !== $authoritativePart->id) {
                throw ValidationException::withMessages([
                    'reverses_movement_id' => 'Koreksi harus merujuk transaksi asli dari unit dan suku cadang yang sama.',
                ]);
            }
        }

        return [$authoritativeUnit, $authoritativePart, $authoritativeActor, $authoritativeReverses];
    }

    private function authorizeActor(User $actor, UnitKerja $unit): void
    {
        if (! $actor->is_active || (! $actor->isPusat() && (! $actor->isUnit() || $actor->unit_kerja_id !== $unit->id))) {
            throw new AuthorizationException('Pengguna tidak berhak mencatat transaksi untuk unit kerja ini.');
        }
    }

    private function validateStaticMovement(
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

        if ($type !== StockMovementType::Correction && $reverses) {
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
