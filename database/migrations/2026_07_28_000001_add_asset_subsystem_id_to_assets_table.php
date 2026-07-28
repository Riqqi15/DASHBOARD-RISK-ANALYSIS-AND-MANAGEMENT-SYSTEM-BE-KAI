<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('asset_subsystem_id')
                ->nullable()
                ->after('unit_kerja_id')
                ->constrained('asset_subsystems')
                ->restrictOnDelete();
            $table->index(['unit_kerja_id', 'asset_subsystem_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex(['unit_kerja_id', 'asset_subsystem_id']);
            $table->dropForeign(['asset_subsystem_id']);
            $table->dropColumn('asset_subsystem_id');
        });
    }
};
