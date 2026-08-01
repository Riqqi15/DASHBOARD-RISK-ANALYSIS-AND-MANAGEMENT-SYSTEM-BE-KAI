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
        if (! Schema::hasColumn('reliability_summaries', 'mttf_hours')) {
            Schema::table('reliability_summaries', function (Blueprint $table): void {
                $table->decimal('mttf_hours', 14, 4)->nullable()->after('failure_count');
            });
        }
        if (! Schema::hasColumn('reliability_summaries', 'calculation_status')) {
            Schema::table('reliability_summaries', function (Blueprint $table): void {
                $table->string('calculation_status', 30)->default('insufficient_data')->after('availability');
            });
        }
        if (! Schema::hasColumn('reliability_summaries', 'formula_version')) {
            Schema::table('reliability_summaries', function (Blueprint $table): void {
                $table->string('formula_version', 30)->default('kai-rams-v1.0.0')->after('calculation_status');
            });
        }
        if (! Schema::hasColumn('reliability_summaries', 'calculated_at')) {
            Schema::table('reliability_summaries', function (Blueprint $table): void {
                $table->timestamp('calculated_at')->nullable()->after('formula_version');
            });
        }

        DB::statement('ALTER TABLE reliability_summaries MODIFY reliability DECIMAL(12,10) NULL');
        DB::statement('ALTER TABLE reliability_summaries MODIFY availability DECIMAL(12,10) NULL');
        DB::table('reliability_summaries')->update([
            'calculation_status' => 'calculated',
            'formula_version' => 'kai-rams-v1.0.0',
            'calculated_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        DB::table('reliability_summaries')->whereNull('reliability')->update(['reliability' => 1]);
        DB::table('reliability_summaries')->whereNull('availability')->update(['availability' => 1]);
        DB::statement('ALTER TABLE reliability_summaries MODIFY reliability DECIMAL(12,10) NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE reliability_summaries MODIFY availability DECIMAL(12,10) NOT NULL DEFAULT 1');

        Schema::table('reliability_summaries', function (Blueprint $table): void {
            $table->dropColumn(['mttf_hours', 'calculation_status', 'formula_version', 'calculated_at']);
        });
    }
};
