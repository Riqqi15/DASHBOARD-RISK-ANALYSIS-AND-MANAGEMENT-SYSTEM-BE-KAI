<?php

namespace App\Console\Commands;

use App\Models\UnitKerja;
use App\Services\MasterAssetWorkbookImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportMasterAssets extends Command
{
    protected $signature = 'rams:import-master-assets
                            {workbook : Lokasi file Excel sumber}
                            {--unit= : Kode unit kerja, misalnya DAOP-1}';

    protected $description = 'Mengimpor Master Aset dari sheet Predictive Data Asset';

    public function handle(MasterAssetWorkbookImporter $importer): int
    {
        try {
            $unitCode = trim((string) $this->option('unit'));

            if ($unitCode === '') {
                $this->error('Opsi --unit wajib diisi.');

                return self::FAILURE;
            }

            $unit = UnitKerja::query()
                ->where('code', $unitCode)
                ->where('is_active', true)
                ->first();

            if (! $unit) {
                $this->error("Unit kerja aktif dengan kode {$unitCode} tidak ditemukan.");

                return self::FAILURE;
            }

            $result = $importer->import((string) $this->argument('workbook'), $unit);

            $this->info('Import Master Aset selesai.');
            $this->line("Dibuat: {$result['created']}");
            $this->line("Diperbarui: {$result['updated']}");
            $this->line("Dilewati: {$result['skipped']}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Import gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
