<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RamsImportBatch;
use App\Models\RamsImportChange;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RamsImportRollbackService
{
    public function __construct(
        private readonly RamsImportChangeRecorder $recorder,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @return array{allowed: bool, reason: ?string} */
    public function availability(RamsImportBatch $batch, ?User $actor): array
    {
        if ($actor === null || ! $actor->isPusat()) {
            return ['allowed' => false, 'reason' => 'Hanya akun pusat yang dapat melakukan rollback.'];
        }
        if ($batch->status !== 'succeeded') {
            return ['allowed' => false, 'reason' => 'Hanya batch sukses yang dapat di-rollback.'];
        }
        if ($batch->dry_run) {
            return ['allowed' => false, 'reason' => 'Batch simulasi tidak mengubah data.'];
        }
        if (! $batch->changes()->exists()) {
            return ['allowed' => false, 'reason' => 'Snapshot perubahan batch tidak tersedia.'];
        }
        if (
            RamsImportBatch::query()
                ->where('id', '>', $batch->id)
                ->where('dry_run', false)
                ->whereIn('status', ['queued', 'processing', 'succeeded'])
                ->exists()
        ) {
            return ['allowed' => false, 'reason' => 'Ada batch import yang lebih baru. Rollback batch lama diblokir.'];
        }

        $conflict = $this->firstConflict($batch);
        if ($conflict !== null) {
            return ['allowed' => false, 'reason' => $conflict];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function rollback(RamsImportBatch $batch, User $actor): void
    {
        $availability = $this->availability($batch, $actor);
        if (! $availability['allowed']) {
            $batch->update(['rollback_error' => $availability['reason']]);
            throw new DomainException($availability['reason'] ?? 'Rollback tidak tersedia.');
        }

        $beforeBatch = $batch->only(['status', 'rolled_back_by_user_id', 'rolled_back_at']);
        try {
            DB::transaction(function () use ($batch, $actor, $beforeBatch): void {
                $changes = $batch->changes()->get()->groupBy('table_name');
                foreach (array_reverse(RamsImportChangeRecorder::TABLES) as $table) {
                    foreach ($changes->get($table, collect())->where('operation', 'created') as $change) {
                        DB::table($table)->where('id', $change->row_id)->delete();
                    }
                }

                foreach (RamsImportChangeRecorder::TABLES as $table) {
                    foreach (
                        $changes->get($table, collect())->whereIn('operation', ['updated', 'deleted']) as $change
                    ) {
                        $before = $change->before_values;
                        if (! is_array($before)) {
                            continue;
                        }
                        DB::table($table)->updateOrInsert(['id' => $change->row_id], $before);
                    }
                }

                $batch->update([
                    'status' => 'rolled_back',
                    'progress_stage' => 'Import dibatalkan',
                    'rolled_back_by_user_id' => $actor->id,
                    'rolled_back_at' => now(),
                    'rollback_error' => null,
                ]);
                $this->auditLogger->record(
                    'rams_import.rolled_back',
                    $batch,
                    $beforeBatch,
                    $batch->fresh()->only(['status', 'rolled_back_by_user_id', 'rolled_back_at']),
                    $actor,
                );
            }, 3);
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $message = 'Rollback dibatalkan karena data memiliki relasi atau perubahan yang tidak aman.';
            $batch->update(['rollback_error' => $message]);
            throw new DomainException($message, previous: $exception);
        }
    }

    private function firstConflict(RamsImportBatch $batch): ?string
    {
        $changes = $batch->changes()->orderBy('id')->get();
        $currentRows = [];
        foreach ($changes->groupBy('table_name') as $table => $tableChanges) {
            $ids = $tableChanges->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->unique()->values();
            $currentRows[$table] = DB::table($table)->whereIn('id', $ids)->get()->keyBy('id');
        }

        /** @var RamsImportChange $change */
        foreach ($changes as $change) {
            $current = $currentRows[$change->table_name]->get($change->row_id);
            if ($change->after_values === null) {
                if ($current !== null) {
                    return "Data {$change->table_name} #{$change->row_id} berubah setelah import.";
                }

                continue;
            }
            if ($current === null || $this->recorder->hash((array) $current) !== $change->after_hash) {
                return "Data {$change->table_name} #{$change->row_id} berubah setelah import.";
            }
        }

        return null;
    }
}
