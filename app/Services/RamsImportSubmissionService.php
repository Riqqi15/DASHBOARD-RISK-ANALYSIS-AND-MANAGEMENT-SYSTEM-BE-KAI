<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessRamsWorkbookImport;
use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class RamsImportSubmissionService
{
    /** @return array{batch: RamsImportBatch, duplicate: bool} */
    public function submit(UploadedFile $workbook, UnitKerja $unit, bool $dryRun, User $actor): array
    {
        $path = $workbook->getRealPath();
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Temporary file workbook tidak tersedia.');
        }

        $workbookHash = hash_file('sha256', $path);
        if ($workbookHash === false) {
            throw new RuntimeException('Fingerprint workbook gagal dibuat.');
        }

        $fingerprint = hash('sha256', implode('|', [
            $workbookHash,
            (string) $unit->id,
            FailureLogImportService::IMPORT_VERSION,
        ]));
        $existing = RamsImportBatch::query()->where('fingerprint', $fingerprint)->first();
        if ($existing !== null && in_array($existing->status, ['queued', 'processing', 'succeeded'], true)) {
            return ['batch' => $existing, 'duplicate' => true];
        }

        $extension = strtolower($workbook->getClientOriginalExtension()) ?: 'xlsx';
        $storedPath = $workbook->storeAs('rams-imports', "{$fingerprint}.{$extension}", 'local');
        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('Workbook gagal disimpan ke penyimpanan private.');
        }

        $batch = $existing ?? new RamsImportBatch;
        $batch->fill([
            'unit_kerja_id' => $unit->id,
            'uploaded_by_user_id' => $actor->id,
            'fingerprint' => $fingerprint,
            'import_version' => FailureLogImportService::IMPORT_VERSION,
            'workbook_name' => $workbook->getClientOriginalName(),
            'file_size' => $workbook->getSize() ?: 0,
            'stored_path' => $storedPath,
            'status' => 'queued',
            'progress_stage' => 'Menunggu antrean',
            'progress_percent' => 0,
            'dry_run' => $dryRun,
            'summary' => null,
            'error_message' => null,
            'queued_at' => now(),
            'started_at' => now(),
            'finished_at' => null,
            'rolled_back_by_user_id' => null,
            'rolled_back_at' => null,
            'rollback_error' => null,
        ])->save();
        $batch->issues()->delete();

        ProcessRamsWorkbookImport::dispatch($batch->id)->onQueue('rams-imports');

        return ['batch' => $batch->fresh(), 'duplicate' => false];
    }
}
