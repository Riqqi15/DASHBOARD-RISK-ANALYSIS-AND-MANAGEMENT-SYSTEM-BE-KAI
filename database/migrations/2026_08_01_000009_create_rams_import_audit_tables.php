<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rams_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
            $table->char('fingerprint', 64)->unique();
            $table->string('import_version', 30);
            $table->string('workbook_name');
            $table->unsignedBigInteger('file_size');
            $table->string('status', 30);
            $table->boolean('dry_run')->default(false);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['unit_kerja_id', 'status']);
        });

        Schema::create('rams_import_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rams_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_column')->nullable();
            $table->string('severity', 20)->default('error');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['rams_import_batch_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rams_import_issues');
        Schema::dropIfExists('rams_import_batches');
    }
};
