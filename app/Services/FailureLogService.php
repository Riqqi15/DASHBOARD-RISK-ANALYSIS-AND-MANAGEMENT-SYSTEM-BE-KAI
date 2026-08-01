<?php

namespace App\Services;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\Asset;
use App\Models\FailureLog;
use App\Models\ReliabilitySummary;
use App\Models\SparePart;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FailureLogService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
        private readonly ReliabilityCalculator $reliabilityCalculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data, User $actor): FailureLog
    {
        return DB::transaction(function () use ($data, $actor): FailureLog {
            $authoritativeActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->sharedLock()
                ->first();
            if (! $authoritativeActor) {
                throw new AuthorizationException('Pengguna tidak aktif atau tidak ditemukan.');
            }

            $asset = Asset::query()
                ->whereKey($data['asset_id'])
                ->with('unitKerja')
                ->sharedLock()
                ->firstOrFail();
            if ($authoritativeActor->isUnit() && $authoritativeActor->unit_kerja_id !== $asset->unit_kerja_id) {
                throw new AuthorizationException('Aset berada di luar unit kerja pengguna.');
            }

            $existing = FailureLog::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->asset_id !== $asset->id || $existing->created_by !== $authoritativeActor->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Kunci transaksi sudah digunakan untuk Trouble Report yang berbeda.',
                    ]);
                }

                return $existing;
            }

            $part = $this->resolveSparePart($data, $asset);
            $startedAt = CarbonImmutable::parse($data['started_at']);
            $resolvedAt = CarbonImmutable::parse($data['resolved_at']);

            if ($part) {
                $this->stockMovementService->record(
                    unit: $asset->unitKerja,
                    part: $part,
                    actor: $authoritativeActor,
                    type: StockMovementType::Out,
                    direction: StockDirection::Out,
                    quantity: (int) $data['spare_part_quantity'],
                    movementDate: $startedAt,
                    referenceNumber: 'FAILURE-'.$data['idempotency_key'],
                    notes: 'Penggantian suku cadang pada Trouble Report: '.$data['failure_event'],
                    idempotencyKey: $data['idempotency_key'],
                );
            }

            $failure = FailureLog::query()->create([
                'asset_id' => $asset->id,
                'spare_part_id' => $part?->id,
                'created_by' => $authoritativeActor->id,
                'idempotency_key' => $data['idempotency_key'],
                'location' => $data['location'],
                'resort' => $data['resort'] ?? null,
                'qc' => $data['qc'] ?? null,
                'failure_event' => $data['failure_event'],
                'cause' => $data['cause'],
                'action_taken' => $data['action_taken'],
                'started_at' => $startedAt,
                'resolved_at' => $resolvedAt,
                'downtime_minutes' => (int) $startedAt->diffInMinutes($resolvedAt),
                'spare_part_replaced' => $part !== null,
                'spare_part_quantity' => $part ? (int) $data['spare_part_quantity'] : null,
                'vandalism' => (bool) $data['vandalism'],
            ]);
            $failure->setRelation('asset', $asset);

            $this->recalculateReliability($asset, $startedAt, $resolvedAt);
            $this->auditLogger->record(
                action: 'failure_log.created',
                subject: $failure,
                before: [],
                after: [
                    'asset_id' => $asset->id,
                    'started_at' => $startedAt->toDateTimeString(),
                    'downtime_minutes' => $failure->downtime_minutes,
                    'spare_part_id' => $part?->id,
                    'spare_part_quantity' => $failure->spare_part_quantity,
                ],
                actor: $authoritativeActor,
            );

            return $failure;
        }, 5);
    }

    /** @param array<string, mixed> $data */
    private function resolveSparePart(array $data, Asset $asset): ?SparePart
    {
        if (! (bool) $data['spare_part_replaced']) {
            return null;
        }

        $part = SparePart::query()
            ->whereKey($data['spare_part_id'])
            ->where('is_active', true)
            ->sharedLock()
            ->firstOrFail();
        if ($part->asset_subsystem_id !== $asset->asset_subsystem_id) {
            throw ValidationException::withMessages([
                'spare_part_id' => 'Suku cadang tidak sesuai dengan subsystem aset.',
            ]);
        }

        return $part;
    }

    private function recalculateReliability(
        Asset $asset,
        CarbonImmutable $occurredAt,
        CarbonImmutable $resolvedAt,
    ): void {
        $snapshotPeriod = $occurredAt->startOfMonth();
        $installedAt = $asset->tanggal_pemasangan?->startOfDay();
        $periodStart = $installedAt && $installedAt->lessThan($resolvedAt)
            ? CarbonImmutable::instance($installedAt)
            : $snapshotPeriod;
        $periodEnd = $resolvedAt;
        $failures = FailureLog::query()
            ->where('asset_id', $asset->id)
            ->where('started_at', '>=', $periodStart)
            ->where('started_at', '<', $periodEnd)
            ->get(['started_at', 'resolved_at'])
            ->map(fn (FailureLog $failure): array => [
                'started_at' => $failure->started_at,
                'resolved_at' => $failure->resolved_at,
            ]);
        $metrics = $this->reliabilityCalculator->calculate(
            $asset->jumlah_unit,
            $periodStart,
            $periodEnd,
            $failures,
        );

        ReliabilitySummary::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'period' => $snapshotPeriod->toDateString()],
            [...$metrics, 'calculated_at' => now()],
        );
    }
}
