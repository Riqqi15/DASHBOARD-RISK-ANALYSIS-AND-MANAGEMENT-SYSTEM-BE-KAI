<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reliability_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->unsignedBigInteger('operating_minutes')->default(0);
            $table->unsignedBigInteger('downtime_minutes')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->decimal('mttf_hours', 14, 4)->nullable();
            $table->decimal('mtbf_hours', 14, 4)->nullable();
            $table->decimal('mttr_hours', 14, 4)->nullable();
            $table->decimal('failure_rate', 18, 10)->nullable();
            $table->decimal('reliability', 12, 10)->nullable();
            $table->decimal('availability', 12, 10)->nullable();
            $table->string('calculation_status', 30)->default('insufficient_data');
            $table->string('formula_version', 30)->default('kai-rams-v1.0.0');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reliability_summaries');
    }
};
