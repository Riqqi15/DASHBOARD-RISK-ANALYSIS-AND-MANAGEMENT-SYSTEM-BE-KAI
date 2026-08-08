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
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->after('unit_kerja_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rams_import_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uploaded_by_user_id');
        });
    }
};
