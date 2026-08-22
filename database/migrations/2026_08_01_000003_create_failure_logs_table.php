<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('spare_part_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('source_key', 64)->nullable()->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('location');
            $table->string('resort')->nullable();
            $table->string('qc')->nullable();
            $table->string('failure_event');
            $table->text('cause');
            $table->text('action_taken');
            $table->dateTime('started_at');
            $table->dateTime('resolved_at');
            $table->unsignedInteger('downtime_minutes');
            $table->boolean('spare_part_replaced')->default(false);
            $table->unsignedInteger('spare_part_quantity')->nullable();
            $table->boolean('vandalism')->default(false);
            $table->timestamps();

            $table->index(['asset_id', 'started_at']);
        });

        DB::statement(
            'ALTER TABLE failure_logs ADD CONSTRAINT chk_failure_logs_time CHECK (resolved_at >= started_at)',
        );
        DB::statement(
            'ALTER TABLE failure_logs ADD CONSTRAINT '
                .'chk_failure_logs_part_quantity CHECK (spare_part_quantity IS NULL OR spare_part_quantity > 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('failure_logs');
    }
};
