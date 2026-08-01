<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RamsWorkbookImportCoordinator;
use Illuminate\Console\Command;
use Throwable;

final class ImportRamsWorkbooks extends Command
{
    protected $signature = 'rams:import-workbooks
                            {directory : Folder berisi workbook XLSM RAMS}
                            {--dry-run : Validasi seluruh workbook lalu rollback}
                            {--bootstrap-categories : Buat kategori yang belum tersedia pada bootstrap awal}';

    protected $description = 'Mengimpor workbook RAMS Daop/Divre secara terlacak dan idempoten';

    public function handle(RamsWorkbookImportCoordinator $coordinator): int
    {
        try {
            $results = $coordinator->importDirectory(
                (string) $this->argument('directory'),
                (bool) $this->option('dry-run'),
                (bool) $this->option('bootstrap-categories'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $failed = false;
        foreach ($results as $result) {
            $status = (string) $result['status'];
            $this->line("{$result['unit']} | {$result['workbook']} | {$status}");
            if ($status === 'failed') {
                $failed = true;
                $this->error((string) $result['error']);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
