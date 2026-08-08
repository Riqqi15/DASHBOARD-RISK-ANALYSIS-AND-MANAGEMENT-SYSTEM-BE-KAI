<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rams_import_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rams_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('table_name', 80);
            $table->unsignedBigInteger('row_id');
            $table->string('operation', 20);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->char('after_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['rams_import_batch_id', 'table_name', 'row_id'], 'rams_import_change_row_unique');
            $table->index(['table_name', 'row_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rams_import_changes');
    }
};
