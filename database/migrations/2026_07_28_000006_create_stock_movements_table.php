<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('direction', 8);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->date('movement_date');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reverses_movement_id')
                ->nullable()
                ->constrained('stock_movements')
                ->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['unit_kerja_id', 'movement_date']);
            $table->index(['spare_part_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
