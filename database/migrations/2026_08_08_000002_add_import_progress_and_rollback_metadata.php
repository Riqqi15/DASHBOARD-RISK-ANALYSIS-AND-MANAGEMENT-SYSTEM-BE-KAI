<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rams_import_batches', function (Blueprint $table): void {
            $table->string('stored_path')->nullable()->after('file_size');
            $table->string('progress_stage', 80)->default('queued')->after('status');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('progress_stage');
            $table->timestamp('queued_at')->nullable()->after('dry_run');
            $table->foreignId('rolled_back_by_user_id')->nullable()->after('finished_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable()->after('rolled_back_by_user_id');
            $table->text('rollback_error')->nullable()->after('rolled_back_at');
        });
    }

    public function down(): void
    {
        Schema::table('rams_import_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rolled_back_by_user_id');
            $table->dropColumn([
                'stored_path', 'progress_stage', 'progress_percent', 'queued_at',
                'rolled_back_at', 'rollback_error',
            ]);
        });
    }
};
