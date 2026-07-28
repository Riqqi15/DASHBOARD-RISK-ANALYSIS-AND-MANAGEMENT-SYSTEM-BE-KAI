<?php

namespace App\Console\Commands;

use App\Services\AssetCategoryBackfill;
use Illuminate\Console\Command;
use Throwable;

class BackfillAssetCategories extends Command
{
    protected $signature = 'rams:backfill-asset-categories';

    protected $description = 'Menghubungkan data aset lama ke kategori aset global';

    public function handle(AssetCategoryBackfill $backfill): int
    {
        try {
            $result = $backfill->run();

            $this->info('Backfill kategori aset selesai.');
            $this->line("Terhubung: {$result['linked']}");
            $this->line("Dilewati: {$result['skipped']}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backfill kategori aset gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
