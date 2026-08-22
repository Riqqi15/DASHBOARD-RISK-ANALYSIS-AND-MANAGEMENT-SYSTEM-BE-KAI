<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('spare_parts', 'reorder_calculation_status')) {
            Schema::table('spare_parts', function (Blueprint $table): void {
                $table->string('reorder_calculation_status', 30)->default('insufficient_data')->after('reorder_point');
                $table
                    ->string('reorder_formula_version', 30)
                    ->default('kai-reorder-v1.0.0')
                    ->after('reorder_calculation_status');
                $table->timestamp('reorder_calculated_at')->nullable()->after('reorder_formula_version');
            });
        }

        DB::table('spare_parts')
            ->whereNotNull('max_yearly_failure')
            ->whereNotNull('average_yearly_failure')
            ->whereNotNull('max_lead_time_months')
            ->whereNotNull('average_lead_time_months')
            ->update([
                'safety_stock' => DB::raw(
                    'CEIL(GREATEST(0, '
                        .'(max_yearly_failure * max_lead_time_months) '
                        .'- (average_yearly_failure * average_lead_time_months)))',
                ),
                'lead_time_demand' => DB::raw('CEIL(average_yearly_failure * average_lead_time_months)'),
                'reorder_point' => DB::raw(
                    'CEIL(GREATEST(0, '
                        .'(max_yearly_failure * max_lead_time_months) '
                        .'- (average_yearly_failure * average_lead_time_months)) '
                        .'+ (average_yearly_failure * average_lead_time_months))',
                ),
                'reorder_calculation_status' => 'calculated',
                'reorder_formula_version' => 'kai-reorder-v1.0.0',
                'reorder_calculated_at' => now(),
            ]);

        DB::table('spare_parts')
            ->where(function ($query): void {
                $query
                    ->whereNull('max_yearly_failure')
                    ->orWhereNull('average_yearly_failure')
                    ->orWhereNull('max_lead_time_months')
                    ->orWhereNull('average_lead_time_months');
            })
            ->update([
                'safety_stock' => null,
                'lead_time_demand' => null,
                'reorder_point' => null,
                'reorder_calculation_status' => 'insufficient_data',
                'reorder_formula_version' => 'kai-reorder-v1.0.0',
                'reorder_calculated_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table): void {
            $table->dropColumn(['reorder_calculation_status', 'reorder_formula_version', 'reorder_calculated_at']);
        });
    }
};
