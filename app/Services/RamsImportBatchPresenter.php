<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RamsImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class RamsImportBatchPresenter
{
    public function __construct(private readonly RamsImportRollbackService $rollback) {}

    /** @return array<string, mixed> */
    public function payload(RamsImportBatch $batch, bool $includeIssues = false, ?User $actor = null): array
    {
        $batch->loadMissing(['unitKerja:id,code,name', 'uploadedBy:id,name,username']);
        $progress = Cache::get(FailureLogImportService::progressCacheKey($batch->id));
        $persistedStage = match ($batch->status) {
            'succeeded' => 'Import selesai',
            'failed' => 'Import gagal',
            'rolled_back' => 'Import dibatalkan',
            default => $batch->progress_stage,
        };
        $persistedPercent = in_array($batch->status, ['succeeded', 'rolled_back'], true)
            ? 100
            : $batch->progress_percent;
        $rollback = $this->rollback->availability($batch, $actor);
        $payload = [
            'id' => $batch->id,
            'workbook_name' => $batch->workbook_name,
            'status' => $batch->status,
            'progress_stage' => is_array($progress) ? $progress['stage'] : $persistedStage,
            'progress_percent' => is_array($progress) ? (int) $progress['percent'] : $persistedPercent,
            'dry_run' => $batch->dry_run,
            'file_size' => $batch->file_size,
            'unit' => $batch->unitKerja?->only(['id', 'code', 'name']),
            'uploaded_by' => $batch->uploadedBy?->only(['id', 'name', 'username']),
            'issues_count' => $batch->getAttribute('issues_count') ?? $batch->issues()->count(),
            'summary' => $batch->summary,
            'error_message' => $batch->error_message,
            'started_at' => $batch->started_at?->toIso8601String(),
            'finished_at' => $batch->finished_at?->toIso8601String(),
            'can_rollback' => $rollback['allowed'],
            'rollback_unavailable_reason' => $rollback['reason'],
            'rolled_back_at' => $batch->rolled_back_at?->toIso8601String(),
        ];

        if ($includeIssues) {
            $payload['issues'] = $batch
                ->issues()
                ->orderBy('id')
                ->get()
                ->map(
                    fn ($issue): array => [
                        'id' => $issue->id,
                        'sheet_name' => $issue->sheet_name,
                        'source_row' => $issue->source_row,
                        'source_column' => $issue->source_column,
                        'severity' => $issue->severity,
                        'message' => $issue->message,
                    ],
                )
                ->values();
        }

        return $payload;
    }
}
