<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

final class FailureLogImportService
{
    public const IMPORT_VERSION = 'failure-log-import-v1.0.0';

    public function __construct(
        private readonly FailureLogWorkbookImporter $importer,
        private readonly ExcelReliabilitySnapshotImporter $snapshotImporter,
        private readonly ReliabilityParityService $parityService,
    ) {}

    /** @return array<string, mixed> */
    public function import(UploadedFile $workbook, UnitKerja $unit): array
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
            self::IMPORT_VERSION,
        ]));
        $batch = RamsImportBatch::query()->firstOrNew(['fingerprint' => $fingerprint]);
        $batch->fill([
            'unit_kerja_id' => $unit->id,
            'import_version' => self::IMPORT_VERSION,
            'workbook_name' => $workbook->getClientOriginalName(),
            'file_size' => $workbook->getSize() ?: 0,
            'status' => 'processing',
            'dry_run' => false,
            'summary' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();
        $batch->issues()->delete();

        try {
            $summary = $this->importer->import($path, $unit, $workbookHash, $workbook->getClientOriginalName());
            $snapshotSummary = $this->snapshotImporter->import($path, $unit, $workbook->getClientOriginalName());
            $paritySummary = $this->parityService->recalculateUnit($unit);
            $summary['snapshots'] = (int) ($snapshotSummary['snapshots'] ?? 0);
            $summary['parity'] = $paritySummary;
            $summary['issues'] = [
                ...($summary['issues'] ?? []),
                ...($snapshotSummary['issues'] ?? []),
            ];
            foreach ($summary['issues'] ?? [] as $issue) {
                $batch->issues()->create([
                    'sheet_name' => $issue['sheet_name'] ?? null,
                    'source_row' => $issue['source_row'] ?? null,
                    'source_column' => $issue['source_column'] ?? null,
                    'severity' => 'warning',
                    'message' => $issue['message'],
                ]);
            }

            $batch->update([
                'status' => 'succeeded',
                'summary' => $summary,
                'finished_at' => now(),
            ]);

            return $this->result($batch, $unit, $summary);
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $issue = $batch->issues()->create([
                'severity' => 'error',
                'message' => $exception->getMessage(),
                'context' => ['exception' => $exception::class],
            ]);

            return [
                'batch_id' => $batch->id,
                'status' => 'failed',
                'workbook' => $batch->workbook_name,
                'unit' => $unit->only(['id', 'code', 'name']),
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'skipped' => 0,
                'sheets' => 0,
                'snapshots' => 0,
                'parity' => [
                    'calculated' => 0,
                    'matched' => 0,
                    'mismatch' => 0,
                    'excel_data_missing' => 0,
                    'not_compared' => 0,
                ],
                'issues' => [[
                    'sheet_name' => null,
                    'source_row' => null,
                    'source_column' => null,
                    'severity' => $issue->severity,
                    'message' => $issue->message,
                ]],
            ];
        }
    }

    /** @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function result(RamsImportBatch $batch, UnitKerja $unit, array $summary): array
    {
        return [
            'batch_id' => $batch->id,
            'status' => 'succeeded',
            'workbook' => $batch->workbook_name,
            'unit' => $unit->only(['id', 'code', 'name']),
            'created' => (int) ($summary['created'] ?? 0),
            'updated' => (int) ($summary['updated'] ?? 0),
            'unchanged' => (int) ($summary['unchanged'] ?? 0),
            'skipped' => (int) ($summary['skipped'] ?? 0),
            'sheets' => (int) ($summary['sheets'] ?? 0),
            'snapshots' => (int) ($summary['snapshots'] ?? 0),
            'parity' => $summary['parity'] ?? [
                'calculated' => 0,
                'matched' => 0,
                'mismatch' => 0,
                'excel_data_missing' => 0,
                'not_compared' => 0,
            ],
            'issues' => array_map(fn (array $issue): array => [
                'sheet_name' => $issue['sheet_name'] ?? null,
                'source_row' => $issue['source_row'] ?? null,
                'source_column' => $issue['source_column'] ?? null,
                'severity' => 'warning',
                'message' => $issue['message'],
            ], $summary['issues'] ?? []),
        ];
    }
}
