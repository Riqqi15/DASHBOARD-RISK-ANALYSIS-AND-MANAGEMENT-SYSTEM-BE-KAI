<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RamsImportBatch;
use App\Services\FailureLogImportService;
use App\Services\RamsImportWorkbookStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(FailureLogImportService $service, RamsImportWorkbookStorage $storage): void
    {
        $batch = RamsImportBatch::query()
            ->with(['unitKerja', 'uploadedBy'])
            ->findOrFail($this->batchId);
        $storedPath = $batch->stored_path;
        $storageDisk = is_string($batch->storage_disk) && $batch->storage_disk !== ''
            ? $batch->storage_disk
            : 'local';
        if (! is_string($storedPath) || ! $storage->exists($storageDisk, $storedPath)) {
            throw new RuntimeException('File workbook antrean tidak ditemukan.');
        }

        $storage->withLocalCopy(
            $storageDisk,
            $storedPath,
            fn (string $localPath) => $service->processBatch(
                $batch,
                $localPath,
                $batch->unitKerja,
                $batch->uploadedBy,
            ),
        );

        $storage->delete($storageDisk, $storedPath);
        $batch->update(['stored_path' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $batch = RamsImportBatch::query()->find($this->batchId);
        if ($batch === null) {
            return;
        }

        if (is_string($batch->stored_path)) {
            app(RamsImportWorkbookStorage::class)->delete(
                is_string($batch->storage_disk) && $batch->storage_disk !== '' ? $batch->storage_disk : 'local',
                $batch->stored_path,
            );
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
