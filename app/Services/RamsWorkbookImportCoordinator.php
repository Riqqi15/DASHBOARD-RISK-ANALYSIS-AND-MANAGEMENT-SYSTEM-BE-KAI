<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PredictiveAssetSnapshot;
use App\Models\RamsImportBatch;
use App\Models\RiskMatrix;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RamsWorkbookImportCoordinator
{
    public const IMPORT_VERSION = 'kai-rams-import-v2.0.0';

    public function __construct(
        private readonly MasterAssetWorkbookImporter $masterAssetImporter,
        private readonly SparePartWorkbookImporter $sparePartImporter,
        private readonly FailureLogWorkbookImporter $failureLogImporter,
        private readonly RiskRegisterWorkbookImporter $riskRegisterImporter,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function importDirectory(
        string $directory,
        bool $dryRun = false,
        bool $bootstrapCategories = false,
    ): array {
        if (! is_dir($directory)) {
            throw new RuntimeException("Folder workbook tidak ditemukan: {$directory}");
        }

        $paths = array_values(array_filter(
            glob(rtrim($directory, '\\/').DIRECTORY_SEPARATOR.'*.xlsm') ?: [],
            fn (string $path): bool => ! str_starts_with(basename($path), '~$')
                && $this->unitCodeFromFilename(basename($path)) !== null,
        ));
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        if ($paths === []) {
            throw new RuntimeException('Tidak ada workbook RAMS Daop/Divre yang dikenali.');
        }

        return array_map(
            fn (string $path): array => $this->importWorkbook($path, $dryRun, $bootstrapCategories),
            $paths,
        );
    }

    /** @return array<string, mixed> */
    public function importWorkbook(
        string $path,
        bool $dryRun = false,
        bool $bootstrapCategories = false,
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook tidak ditemukan: {$path}");
        }

        $workbookName = basename($path);
        $unitCode = $this->unitCodeFromFilename($workbookName);
        if ($unitCode === null) {
            throw new RuntimeException("Nama workbook tidak dapat dipetakan ke Daop/Divre: {$workbookName}");
        }
        $unit = UnitKerja::query()->where('code', $unitCode)->where('is_active', true)->firstOrFail();
        $workbookHash = hash_file('sha256', $path);
        if ($workbookHash === false) {
            throw new RuntimeException("Fingerprint workbook gagal dibuat: {$path}");
        }
        $fingerprint = hash('sha256', $workbookHash.'|'.self::IMPORT_VERSION);

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $summary = $this->runImporters($path, $unit, $bootstrapCategories);
                DB::rollBack();

                return [
                    'workbook' => $workbookName,
                    'unit' => $unitCode,
                    'status' => 'validated',
                    'dry_run' => true,
                    'summary' => $summary,
                ];
            } catch (Throwable $exception) {
                DB::rollBack();

                return [
                    'workbook' => $workbookName,
                    'unit' => $unitCode,
                    'status' => 'failed',
                    'dry_run' => true,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $batch = RamsImportBatch::query()->firstOrNew(['fingerprint' => $fingerprint]);
        if ($batch->exists && $batch->status === 'succeeded') {
            return [
                'workbook' => $workbookName,
                'unit' => $unitCode,
                'status' => 'skipped_duplicate',
                'dry_run' => false,
                'summary' => $batch->summary,
            ];
        }
        $batch->fill([
            'unit_kerja_id' => $unit->id,
            'import_version' => self::IMPORT_VERSION,
            'workbook_name' => $workbookName,
            'file_size' => filesize($path) ?: 0,
            'status' => 'processing',
            'dry_run' => false,
            'summary' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);
        $batch->save();
        $batch->issues()->delete();

        DB::beginTransaction();
        try {
            $summary = $this->runImporters($path, $unit, $bootstrapCategories);
            DB::commit();
            foreach (['risk_registers', 'failure_logs'] as $section) {
                foreach ($summary[$section]['issues'] ?? [] as $issue) {
                    $batch->issues()->create([
                        'sheet_name' => $issue['sheet_name'] ?? null,
                        'source_row' => $issue['source_row'] ?? null,
                        'severity' => 'warning',
                        'message' => $issue['message'],
                    ]);
                }
            }
            $batch->update([
                'status' => 'succeeded',
                'summary' => $summary,
                'finished_at' => now(),
            ]);

            return [
                'workbook' => $workbookName,
                'unit' => $unitCode,
                'status' => 'succeeded',
                'dry_run' => false,
                'summary' => $summary,
            ];
        } catch (Throwable $exception) {
            DB::rollBack();
            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $batch->issues()->create([
                'severity' => 'error',
                'message' => $exception->getMessage(),
                'context' => ['exception' => $exception::class],
            ]);

            return [
                'workbook' => $workbookName,
                'unit' => $unitCode,
                'status' => 'failed',
                'dry_run' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function runImporters(string $path, UnitKerja $unit, bool $bootstrapCategories): array
    {
        $masterAssets = $this->masterAssetImporter->import($path, $unit);

        return [
            'master_assets' => $masterAssets,
            'risk_matrices' => $this->syncRiskMatrices($unit),
            'risk_registers' => $this->riskRegisterImporter->import($path, $unit),
            'spare_parts' => $this->sparePartImporter->import($path, $bootstrapCategories, $unit),
            'failure_logs' => $this->failureLogImporter->import($path, $unit),
        ];
    }

    private function syncRiskMatrices(UnitKerja $unit): int
    {
        $snapshots = PredictiveAssetSnapshot::query()
            ->whereHas('asset', fn ($query) => $query->where('unit_kerja_id', $unit->id))
            ->whereNotNull('likelihood')
            ->whereNotNull('consequence')
            ->orderByDesc('calculated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('asset_id');

        foreach ($snapshots as $snapshot) {
            RiskMatrix::query()->updateOrCreate(
                ['asset_id' => $snapshot->asset_id],
                [
                    'likelihood' => $snapshot->likelihood,
                    'consequence' => $snapshot->consequence,
                    'assessed_at' => $snapshot->calculated_at,
                ],
            );
        }

        return $snapshots->count();
    }

    private function unitCodeFromFilename(string $filename): ?string
    {
        $normalized = mb_strtolower($filename);

        return match (true) {
            preg_match('/daop\s*1\b/u', $normalized) === 1 => 'DAOP-1',
            preg_match('/daop\s*4\b/u', $normalized) === 1 => 'DAOP-4',
            preg_match('/daop\s*8\b/u', $normalized) === 1 => 'DAOP-8',
            preg_match('/divre\s*iii\b/u', $normalized) === 1 => 'DIVRE-III',
            preg_match('/divre\s*iv\b/u', $normalized) === 1 => 'DIVRE-IV',
            default => null,
        };
    }
}
