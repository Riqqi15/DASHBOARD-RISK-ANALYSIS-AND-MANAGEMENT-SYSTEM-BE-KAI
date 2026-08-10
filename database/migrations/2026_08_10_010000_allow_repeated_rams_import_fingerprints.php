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
            $table->dropUnique('rams_import_batches_fingerprint_unique');
            $table->index('fingerprint', 'rams_import_batches_fingerprint_index');
        });
    }

    public function down(): void
    {
        Schema::table('rams_import_batches', function (Blueprint $table): void {
            $table->dropIndex('rams_import_batches_fingerprint_index');
        });
    }
};
