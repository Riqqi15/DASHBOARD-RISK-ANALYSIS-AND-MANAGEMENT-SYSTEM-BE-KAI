<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reliability_excel_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->char('workbook_hash', 64);
            $table->string('workbook_name');
            $table->string('sheet_name');
            $table->unsignedInteger('source_row');
            $table->date('baseline_date')->nullable();
            $table->date('calculation_date')->nullable();
            $table->json('summary_values');
            $table->json('summary_formulas')->nullable();
            $table->json('summary_errors')->nullable();
            $table->json('formula_profile')->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(['asset_id', 'workbook_hash', 'sheet_name'], 'rel_excel_snap_asset_workbook_sheet_unique');
            $table->index(['asset_id', 'imported_at'], 'rel_excel_snap_asset_imported_idx');
        });

        Schema::table('failure_logs', function (Blueprint $table): void {
            $table->string('spare_part_marker')->nullable()->after('spare_part_replaced');
            $table->string('vandalism_marker')->nullable()->after('vandalism');
            $table->char('workbook_hash', 64)->nullable()->after('vandalism_marker');
            $table->string('workbook_name')->nullable()->after('workbook_hash');
            $table->string('sheet_name')->nullable()->after('workbook_name');
            $table->unsignedInteger('source_row')->nullable()->after('sheet_name');
        });

        Schema::table('reliability_summaries', function (Blueprint $table): void {
            $table->foreignId('excel_snapshot_id')->nullable()->after('asset_id')->constrained('reliability_excel_snapshots')->nullOnDelete();
            $table->date('baseline_date')->nullable()->after('period');
            $table->date('calculation_date')->nullable()->after('baseline_date');
            $table->unsignedInteger('unit_count')->default(0)->after('calculation_date');
            $table->decimal('operating_hours', 18, 6)->nullable()->after('unit_count');
            $table->decimal('downtime_value', 18, 6)->nullable()->after('operating_hours');
            $table->decimal('uptime_hours', 18, 6)->nullable()->after('downtime_value');
            $table->unsignedInteger('spare_part_replacement_count')->default(0)->after('availability');
            $table->unsignedInteger('vandalism_count')->default(0)->after('spare_part_replacement_count');
            $table->json('calculation_profile')->nullable()->after('vandalism_count');
            $table->string('parity_status', 30)->default('not_compared')->after('calculation_profile');
            $table->json('parity_differences')->nullable()->after('parity_status');
        });
    }

    public function down(): void
    {
        Schema::table('reliability_summaries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('excel_snapshot_id');
            $table->dropColumn([
                'baseline_date',
                'calculation_date',
                'unit_count',
                'operating_hours',
                'downtime_value',
                'uptime_hours',
                'spare_part_replacement_count',
                'vandalism_count',
                'calculation_profile',
                'parity_status',
                'parity_differences',
            ]);
        });

        Schema::table('failure_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'spare_part_marker',
                'vandalism_marker',
                'workbook_hash',
                'workbook_name',
                'sheet_name',
                'source_row',
            ]);
        });

        Schema::dropIfExists('reliability_excel_snapshots');
    }
};
