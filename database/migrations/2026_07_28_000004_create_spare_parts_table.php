<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_subsystem_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('source_key')->unique();
            $table->string('equipment')->nullable();
            $table->string('detail_equipment');
            $table->decimal('max_yearly_failure', 10, 2)->nullable();
            $table->decimal('average_yearly_failure', 10, 2)->nullable();
            $table->decimal('max_lead_time_months', 10, 2)->nullable();
            $table->decimal('average_lead_time_months', 10, 2)->nullable();
            $table->unsignedInteger('safety_stock')->nullable();
            $table->unsignedInteger('lead_time_demand')->nullable();
            $table->unsignedInteger('reorder_point')->nullable();
            $table->string('reorder_calculation_status', 30)->default('insufficient_data');
            $table->string('reorder_formula_version', 30)->default('kai-reorder-v1.0.0');
            $table->timestamp('reorder_calculated_at')->nullable();
            $table->string('severity')->nullable();
            $table->string('unit_of_measure', 30)->default('unit');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_subsystem_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};
