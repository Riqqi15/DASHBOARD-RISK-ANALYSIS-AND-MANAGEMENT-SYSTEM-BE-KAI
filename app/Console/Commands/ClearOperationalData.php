<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearOperationalData extends Command
{
    protected $signature = 'rams:clear-operational-data {--force : Skip confirmation}';

    protected $description = 'Hapus semua data operasional (failure logs, reliability, risk, dll) '
        .'tapi pertahankan master data';

    private const TABLES = [
        'failure_logs',
        'reliability_summaries',
        'risk_registers',
        'risk_matrices',
        'predictive_asset_snapshots',
        'rams_import_issues',
        'rams_import_batches',
        'stock_movements',
        'audit_logs',
    ];

    public function handle(): int
    {
        if (
            ! $this->option('force') &&
            ! $this->confirm('Yakin ingin menghapus semua data operasional? Tindakan ini tidak dapat dibatalkan.')
        ) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::TABLES as $table) {
            $count = DB::table($table)->count();
            DB::table($table)->truncate();
            $this->line("  <fg=green>✓</> TRUNCATE <fg=cyan>{$table}</> — {$count} baris dihapus");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Selesai. Semua data operasional telah dihapus. Master data tetap utuh.');

        return self::SUCCESS;
    }
}
