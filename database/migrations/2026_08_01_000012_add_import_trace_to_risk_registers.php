<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_registers', function (Blueprint $table): void {
            $table->char('source_key', 64)->nullable()->unique()->after('asset_id');
            $table->char('workbook_hash', 64)->nullable()->index()->after('source_key');
            $table->string('workbook_name')->nullable()->after('workbook_hash');
            $table->string('sheet_name')->nullable()->after('workbook_name');
            $table->unsignedInteger('source_row')->nullable()->after('sheet_name');
        });

        DB::table('risk_registers')
            ->whereIn('part_number', [
                'SIL-01', 'PSE-01', 'PWE-01', 'TCR-01',
                'CDS-01', 'SIM-01', 'KTD-01', 'PWE-02',
            ])
            ->whereNull('source_key')
            ->delete();
    }

    public function down(): void
    {
        Schema::table('risk_registers', function (Blueprint $table): void {
            $table->dropUnique(['source_key']);
            $table->dropIndex(['workbook_hash']);
            $table->dropColumn([
                'source_key', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
            ]);
        });
    }
};
