<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rams_import_batches')->where('status', 'succeeded')->update([
            'progress_stage' => 'Import selesai',
            'progress_percent' => 100,
        ]);
        DB::table('rams_import_batches')->where('status', 'failed')->update([
            'progress_stage' => 'Import gagal',
        ]);
        DB::table('rams_import_batches')->where('status', 'rolled_back')->update([
            'progress_stage' => 'Import dibatalkan',
            'progress_percent' => 100,
        ]);
    }

    public function down(): void
    {
        DB::table('rams_import_batches')->whereIn('status', ['succeeded', 'failed', 'rolled_back'])->update([
            'progress_stage' => 'queued',
            'progress_percent' => 0,
        ]);
    }
};
