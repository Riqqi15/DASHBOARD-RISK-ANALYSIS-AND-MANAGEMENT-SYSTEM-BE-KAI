<?php

namespace App\Console\Commands;

use App\Services\SparePartWorkbookImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportSpareParts extends Command
{
    protected $signature = 'rams:import-spare-parts
                            {workbook : Lokasi file Excel sumber}
                            {--bootstrap-categories : Buat hierarchy kategori Reorder Stock yang belum tersedia (khusus bootstrap awal)}';

    protected $description = 'Mengimpor master suku cadang global dari sheet Reorder Stock';

    public function handle(SparePartWorkbookImporter $importer): int
    {
        try {
            $result = $importer->import(
                (string) $this->argument('workbook'),
                (bool) $this->option('bootstrap-categories'),
            );

            $this->info('Import master suku cadang selesai.');
            $this->line("Dibuat: {$result['created']}");
            $this->line("Diperbarui: {$result['updated']}");
            $this->line("Tidak berubah: {$result['unchanged']}");
            $this->line("Dilewati: {$result['skipped']}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Import gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
