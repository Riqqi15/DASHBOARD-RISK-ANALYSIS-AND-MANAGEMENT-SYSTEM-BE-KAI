<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTableCounts extends Command
{
    protected $signature = 'rams:table-counts';

    protected $description = 'Tampilkan jumlah baris semua tabel';

    public function handle(): int
    {
        $tables = [
            'failure_logs',
            'reliability_summaries',
            'reliability_excel_snapshots',
            'risk_registers',
            'risk_matrices',
            'predictive_asset_snapshots',
            'rams_import_batches',
            'rams_import_issues',
            'stock_movements',
            'audit_logs',
            'assets',
            'users',
            'spare_parts',
            'inventory_stocks',
            'unit_subsystem_openings',
        ];

        $rows = [];
        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $rows[] = [$table, $count, $count > 0 ? '<fg=yellow>⚠ Ada data</>' : '<fg=green>Kosong</>'];
            } catch (\Exception $e) {
                $rows[] = [$table, '-', '<fg=red>Tabel tidak ada</>'];
            }
        }

        $this->table(['Tabel', 'Baris', 'Status'], $rows);

        return self::SUCCESS;
    }
}
