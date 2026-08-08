<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['asset_groups', 'asset_systems', 'asset_subsystems'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->char('dashboard_color', 7)->nullable()->after('sort_order');
                $table->string('dashboard_color_source', 10)->nullable()->after('dashboard_color');
            });
        }

        Schema::table('predictive_asset_snapshots', function (Blueprint $table): void {
            $table->integer('current_stock')->default(0)->change();
            $table->json('excel_values')->nullable()->after('calculation_status');
            $table->json('excel_formulas')->nullable()->after('excel_values');
            $table->string('parity_status', 30)->default('not_compared')->after('excel_formulas');
            $table->json('parity_differences')->nullable()->after('parity_status');
        });

        Schema::table('risk_matrices', function (Blueprint $table): void {
            $table->char('source_key', 64)->nullable()->unique()->after('asset_id');
            $table->char('workbook_hash', 64)->nullable()->after('source_key');
            $table->string('workbook_name')->nullable()->after('workbook_hash');
            $table->string('sheet_name')->nullable()->after('workbook_name');
            $table->unsignedInteger('source_row')->nullable()->after('sheet_name');
            $table->json('excel_values')->nullable()->after('consequence');
            $table->json('excel_formulas')->nullable()->after('excel_values');
            $table->string('parity_status', 30)->default('not_compared')->after('excel_formulas');
            $table->json('parity_differences')->nullable()->after('parity_status');
            $table->string('formula_version', 30)->nullable()->after('parity_differences');
        });
    }

    public function down(): void
    {
        Schema::table('risk_matrices', function (Blueprint $table): void {
            $table->dropUnique(['source_key']);
            $table->dropColumn([
                'source_key', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
                'excel_values', 'excel_formulas', 'parity_status', 'parity_differences', 'formula_version',
            ]);
        });

        Schema::table('predictive_asset_snapshots', function (Blueprint $table): void {
            $table->unsignedInteger('current_stock')->default(0)->change();
            $table->dropColumn(['excel_values', 'excel_formulas', 'parity_status', 'parity_differences']);
        });

        foreach (['asset_subsystems', 'asset_systems', 'asset_groups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['dashboard_color', 'dashboard_color_source']);
            });
        }
    }
};
