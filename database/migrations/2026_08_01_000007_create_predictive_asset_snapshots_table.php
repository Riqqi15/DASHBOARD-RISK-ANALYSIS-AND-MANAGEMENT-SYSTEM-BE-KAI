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
        Schema::create('predictive_asset_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->char('source_key', 64)->unique();
            $table->char('workbook_hash', 64);
            $table->string('workbook_name');
            $table->string('sheet_name')->default('Predictive Data Asset');
            $table->unsignedInteger('source_row');
            $table->unsignedTinyInteger('function_criterion');
            $table->unsignedTinyInteger('production_impact');
            $table->decimal('lead_time_months', 10, 2);
            $table->string('price_category', 20);
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('total_assets')->default(0);
            $table->decimal('average_yearly_usage', 12, 4)->default(0);
            $table->decimal('sla_percentage', 8, 4)->default(1.5);
            $table->decimal('failure_safety_stock', 12, 4)->default(0);
            $table->string('item_classification', 30)->nullable();
            $table->boolean('repairable')->nullable();
            $table->date('installed_at')->nullable();
            $table->decimal('lifetime_years', 8, 2)->nullable();
            $table->unsignedInteger('vandalism_count')->default(0);
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->unsignedTinyInteger('consequence')->nullable();
            $table->string('criticality', 20);
            $table->string('lead_time_category', 20);
            $table->string('inventory_policy', 40);
            $table->unsignedInteger('needed_stock');
            $table->unsignedInteger('proposal_quantity');
            $table->string('proposal_reasonableness', 40)->nullable();
            $table->decimal('safety_stock_usage', 14, 4);
            $table->decimal('safety_stock_mca', 14, 4);
            $table->decimal('safety_stock_failure', 14, 4);
            $table->unsignedInteger('final_safety_stock');
            $table->decimal('age_years', 10, 4)->nullable();
            $table->string('age_condition', 20)->nullable();
            $table->string('lifetime_status', 30)->nullable();
            $table->unsignedSmallInteger('risk_rating')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->string('calculation_status', 30);
            $table->string('formula_version', 30);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['asset_id', 'calculated_at']);
        });

        DB::statement('ALTER TABLE predictive_asset_snapshots ADD CONSTRAINT chk_predictive_function CHECK (function_criterion BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE predictive_asset_snapshots ADD CONSTRAINT chk_predictive_impact CHECK (production_impact BETWEEN 0 AND 3)');
        DB::statement('ALTER TABLE predictive_asset_snapshots ADD CONSTRAINT chk_predictive_likelihood CHECK (likelihood IS NULL OR likelihood BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE predictive_asset_snapshots ADD CONSTRAINT chk_predictive_consequence CHECK (consequence IS NULL OR consequence BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_asset_snapshots');
    }
};
