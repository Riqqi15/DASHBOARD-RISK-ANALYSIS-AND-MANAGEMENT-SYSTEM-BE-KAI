<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('part_number', 100)->nullable();
            $table->string('sub')->nullable();
            $table->string('risk_event');
            $table->text('risk_cause');
            $table->text('impact')->nullable();
            $table->string('part_name')->nullable();
            $table->text('recommendation')->nullable();
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->unsignedTinyInteger('consequence')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE risk_registers ADD CONSTRAINT '
                .'chk_risk_registers_likelihood CHECK (likelihood IS NULL OR likelihood BETWEEN 1 AND 4)',
        );
        DB::statement(
            'ALTER TABLE risk_registers ADD CONSTRAINT '
                .'chk_risk_registers_consequence CHECK (consequence IS NULL OR consequence BETWEEN 1 AND 4)',
        );
        DB::statement(
            'ALTER TABLE risk_registers ADD CONSTRAINT '
                ."chk_risk_registers_status CHECK (status IN ('open', 'in_progress', 'closed'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_registers');
    }
};
