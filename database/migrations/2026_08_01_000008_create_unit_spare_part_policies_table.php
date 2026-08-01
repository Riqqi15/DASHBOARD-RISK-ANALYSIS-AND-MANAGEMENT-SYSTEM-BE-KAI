<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_spare_part_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->char('source_key', 64)->unique();
            $table->char('workbook_hash', 64);
            $table->string('workbook_name');
            $table->unsignedInteger('source_row');
            $table->decimal('max_yearly_failure', 10, 2)->nullable();
            $table->decimal('average_yearly_failure', 10, 2)->nullable();
            $table->decimal('max_lead_time_months', 10, 2)->nullable();
            $table->decimal('average_lead_time_months', 10, 2)->nullable();
            $table->unsignedInteger('safety_stock')->nullable();
            $table->unsignedInteger('lead_time_demand')->nullable();
            $table->unsignedInteger('reorder_point')->nullable();
            $table->string('severity')->nullable();
            $table->string('calculation_status', 30)->default('insufficient_data');
            $table->string('formula_version', 30)->default('kai-reorder-v1.0.0');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_kerja_id', 'spare_part_id']);
            $table->index(['unit_kerja_id', 'reorder_point']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_spare_part_policies');
    }
};
