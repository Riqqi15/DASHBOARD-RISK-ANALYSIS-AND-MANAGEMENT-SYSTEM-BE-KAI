<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RamsImportBatch;
use App\Services\FailureLogImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProcessRamsWorkbookImport implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $batchId) {}

    public function uniqueId(): string
    {
        return (string) $this->batchId;
    }

    public function handle(FailureLogImportService $service): void
    {
        $batch = RamsImportBatch::query()
            ->with(['unitKerja', 'uploadedBy'])
            ->findOrFail($this->batchId);
        $storedPath = $batch->stored_path;
        if (! is_string($storedPath) || ! Storage::disk('local')->exists($storedPath)) {
            throw new RuntimeException('File workbook antrean tidak ditemukan.');
        }

        $service->processBatch(
            $batch,
            Storage::disk('local')->path($storedPath),
            $batch->unitKerja,
            $batch->uploadedBy,
        );

        Storage::disk('local')->delete($storedPath);
        $batch->update(['stored_path' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $batch = RamsImportBatch::query()->find($this->batchId);
        if ($batch === null) {
            return;
        }

        if (is_string($batch->stored_path)) {
            Storage::disk('local')->delete($batch->stored_path);
        }
        $batch->update([
            'stored_path' => null,
            'status' => 'failed',
            'progress_stage' => 'Import gagal',
            'error_message' => $exception?->getMessage() ?? 'Job import gagal.',
            'finished_at' => now(),
        ]);
    }
}
