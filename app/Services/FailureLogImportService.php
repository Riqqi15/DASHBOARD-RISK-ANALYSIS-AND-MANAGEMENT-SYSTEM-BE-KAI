<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class FailureLogImportService
{
    public const IMPORT_VERSION = 'kai-rams-web-import-v2.0.0';

    public function __construct(
        private readonly FailureLogWorkbookImporter $importer,
        private readonly ExcelReliabilitySnapshotImporter $snapshotImporter,
        private readonly ReliabilityParityService $parityService,
        private readonly MasterAssetWorkbookImporter $masterAssetImporter,
        private readonly RiskMatrixWorkbookImporter $riskMatrixImporter,
        private readonly RiskRegisterWorkbookImporter $riskRegisterImporter,
        private readonly SparePartWorkbookImporter $sparePartImporter,
        private readonly RamsImportChangeRecorder $changeRecorder,
    ) {}

    public static function progressCacheKey(int $batchId): string
    {
        return "rams-import-progress:{$batchId}";
    }

    /** @return array<string, mixed> */
    public function processBatch(
        RamsImportBatch $batch,
        string $path,
        UnitKerja $unit,
        ?User $actor = null,
    ): array {
        $batch->update([
            'status' => 'processing',
            'progress_stage' => 'Membaca workbook',
            'progress_percent' => 10,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $workbook = new UploadedFile(
            $path,
            $batch->workbook_name,
            null,
            null,
            true,
        );
        $result = $this->import($workbook, $unit, $batch->dry_run, $actor);
        $batch->refresh()->update([
            'progress_stage' => $result['status'] === 'succeeded' ? 'Import selesai' : 'Import gagal',
            'progress_percent' => $result['status'] === 'succeeded' ? 100 : $batch->progress_percent,
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    public function resultForBatch(RamsImportBatch $batch): array
    {
        $batch->loadMissing(['unitKerja', 'issues']);
        $summary = $batch->summary ?? [];
        $summary['issues'] = $batch->issues->map(fn ($issue): array => [
            'sheet_name' => $issue->sheet_name,
            'source_row' => $issue->source_row,
            'source_column' => $issue->source_column,
            'severity' => $issue->severity,
            'message' => $issue->message,
        ])->all();

        return $this->result($batch, $batch->unitKerja, $summary);
    }

    /** @return array<string, mixed> */
    public function import(UploadedFile $workbook, UnitKerja $unit, bool $dryRun = false, ?User $actor = null): array
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
            'uploaded_by_user_id' => $actor?->id,
            'import_version' => self::IMPORT_VERSION,
            'workbook_name' => $workbook->getClientOriginalName(),
            'file_size' => $workbook->getSize() ?: 0,
            'status' => 'processing',
            'dry_run' => $dryRun,
            'summary' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();
        $batch->issues()->delete();

        DB::beginTransaction();
        try {
            $beforeSnapshot = $dryRun ? null : $this->changeRecorder->snapshot();
            // Step 1: Auto-create/update master aset (AssetGroup, AssetSystem, AssetSubsystem, Asset)
            // berdasarkan sheet "Predictive Data Asset" dalam workbook yang sama.
            // Idempoten — tidak akan membuat duplikat jika sudah ada.
            $this->progress($batch, 'Sinkronisasi master aset', 20);
            $masterSummary = [
                'created' => 0,
                'updated' => 0,
                'issues' => [],
            ];
            if ($this->masterAssetImporter->supports($path)) {
                $masterSummary = $this->masterAssetImporter->import($path, $unit);
            } else {
                $masterSummary['issues'][] = [
                    'sheet_name' => 'Predictive Data Asset',
                    'severity' => 'warning',
                    'message' => 'Sheet Predictive Data Asset tidak tersedia; sinkronisasi master aset dilewati.',
                ];
            }
            $this->progress($batch, 'Memproses Risk Matrix dan warna dashboard', 35);
            $riskMatrixSummary = $this->riskMatrixImporter->supports($path)
                ? $this->riskMatrixImporter->import($path, $unit)
                : ['created' => 0, 'updated' => 0, 'colors_updated' => 0, 'issues' => []];
            $this->progress($batch, 'Memproses Risk Register', 50);
            $riskRegisterSummary = $this->riskRegisterImporter->supports($path)
                ? $this->riskRegisterImporter->import($path, $unit)
                : [
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'skipped' => 0,
                    'issues' => [[
                        'sheet_name' => 'LxC',
                        'severity' => 'warning',
                        'message' => 'Sheet LxC tidak tersedia; sinkronisasi Risk Register dilewati.',
                    ]],
                ];
            $this->progress($batch, 'Memproses kebutuhan suku cadang', 65);
            $sparePartSummary = $this->sparePartImporter->supports($path)
                ? $this->sparePartImporter->import($path, true, $unit)
                : [
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'skipped' => 0,
                    'issues' => [[
                        'sheet_name' => 'Reorder Stock',
                        'severity' => 'warning',
                        'message' => 'Sheet Reorder Stock tidak tersedia; sinkronisasi suku cadang dilewati.',
                    ]],
                ];

            $this->progress($batch, 'Memproses Trouble Report', 78);
            $summary = $this->importer->import($path, $unit, $workbookHash, $workbook->getClientOriginalName());
            $this->progress($batch, 'Membaca snapshot reliability Excel', 88);
            $snapshotSummary = $this->snapshotImporter->import($path, $unit, $workbook->getClientOriginalName());
            $this->progress($batch, 'Memeriksa kesesuaian rumus', 95);
            $paritySummary = $this->parityService->recalculateUnit($unit);
            $summary['snapshots'] = (int) ($snapshotSummary['snapshots'] ?? 0);
            $summary['parity'] = $paritySummary['counts'];
            $summary['master_assets_created'] = (int) ($masterSummary['created'] ?? 0);
            $summary['master_assets_updated'] = (int) ($masterSummary['updated'] ?? 0);
            $summary['risk_matrices_created'] = (int) ($riskMatrixSummary['created'] ?? 0);
            $summary['risk_matrices_updated'] = (int) ($riskMatrixSummary['updated'] ?? 0);
            $summary['dashboard_colors_updated'] = (int) ($riskMatrixSummary['colors_updated'] ?? 0);
            $summary['risk_registers_created'] = (int) ($riskRegisterSummary['created'] ?? 0);
            $summary['risk_registers_updated'] = (int) ($riskRegisterSummary['updated'] ?? 0);
            $summary['risk_registers_unchanged'] = (int) ($riskRegisterSummary['unchanged'] ?? 0);
            $summary['risk_registers_skipped'] = (int) ($riskRegisterSummary['skipped'] ?? 0);
            $summary['spare_parts_created'] = (int) ($sparePartSummary['created'] ?? 0);
            $summary['spare_parts_updated'] = (int) ($sparePartSummary['updated'] ?? 0);
            $summary['spare_parts_unchanged'] = (int) ($sparePartSummary['unchanged'] ?? 0);
            $summary['spare_parts_skipped'] = (int) ($sparePartSummary['skipped'] ?? 0);
            $summary['issues'] = [
                ...($summary['issues'] ?? []),
                ...($masterSummary['issues'] ?? []),
                ...($riskMatrixSummary['issues'] ?? []),
                ...($riskRegisterSummary['issues'] ?? []),
                ...($sparePartSummary['issues'] ?? []),
                ...($snapshotSummary['issues'] ?? []),
                ...($paritySummary['issues'] ?? []),
            ];

            if (is_array($beforeSnapshot)) {
                $this->changeRecorder->record($batch, $beforeSnapshot, $this->changeRecorder->snapshot());
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            foreach ($summary['issues'] ?? [] as $issue) {
                $batch->issues()->create([
                    'sheet_name' => $issue['sheet_name'] ?? null,
                    'source_row' => $issue['source_row'] ?? null,
                    'source_column' => $issue['source_column'] ?? null,
                    'severity' => $issue['severity'] ?? 'warning',
                    'message' => $issue['message'],
                ]);
            }

            $batch->update([
                'status' => 'succeeded',
                'progress_stage' => 'Import selesai',
                'progress_percent' => 100,
                'summary' => $summary,
                'finished_at' => now(),
            ]);
            Cache::forget(self::progressCacheKey($batch->id));

            return $this->result($batch, $unit, $summary);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $batch->update([
                'status' => 'failed',
                'progress_stage' => 'Import gagal',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            Cache::forget(self::progressCacheKey($batch->id));
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
                'risk_registers_created' => 0,
                'risk_registers_updated' => 0,
                'spare_parts_created' => 0,
                'spare_parts_updated' => 0,
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
            'dry_run' => $batch->dry_run,
            'workbook' => $batch->workbook_name,
            'unit' => $unit->only(['id', 'code', 'name']),
            'master_assets_created' => (int) ($summary['master_assets_created'] ?? 0),
            'master_assets_updated' => (int) ($summary['master_assets_updated'] ?? 0),
            'created' => (int) ($summary['created'] ?? 0),
            'updated' => (int) ($summary['updated'] ?? 0),
            'unchanged' => (int) ($summary['unchanged'] ?? 0),
            'skipped' => (int) ($summary['skipped'] ?? 0),
            'sheets' => (int) ($summary['sheets'] ?? 0),
            'snapshots' => (int) ($summary['snapshots'] ?? 0),
            'risk_registers_created' => (int) ($summary['risk_registers_created'] ?? 0),
            'risk_registers_updated' => (int) ($summary['risk_registers_updated'] ?? 0),
            'risk_registers_unchanged' => (int) ($summary['risk_registers_unchanged'] ?? 0),
            'risk_registers_skipped' => (int) ($summary['risk_registers_skipped'] ?? 0),
            'spare_parts_created' => (int) ($summary['spare_parts_created'] ?? 0),
            'spare_parts_updated' => (int) ($summary['spare_parts_updated'] ?? 0),
            'spare_parts_unchanged' => (int) ($summary['spare_parts_unchanged'] ?? 0),
            'spare_parts_skipped' => (int) ($summary['spare_parts_skipped'] ?? 0),
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
                'severity' => $issue['severity'] ?? 'warning',
                'message' => $issue['message'],
            ], $summary['issues'] ?? []),
        ];
    }

    private function progress(RamsImportBatch $batch, string $stage, int $percent): void
    {
        Cache::put(self::progressCacheKey($batch->id), [
            'stage' => $stage,
            'percent' => $percent,
        ], now()->addHours(2));
    }
}
