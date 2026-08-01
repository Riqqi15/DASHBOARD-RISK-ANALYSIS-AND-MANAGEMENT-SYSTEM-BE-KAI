<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rams_import_batches', 'import_version')) {
            Schema::table('rams_import_batches', function (Blueprint $table): void {
                $table->string('import_version', 30)->default('kai-rams-import-v1.0.0')->after('fingerprint');
            });
        }
    }

    public function down(): void
    {
        Schema::table('rams_import_batches', function (Blueprint $table): void {
            $table->dropColumn('import_version');
        });
    }
};
