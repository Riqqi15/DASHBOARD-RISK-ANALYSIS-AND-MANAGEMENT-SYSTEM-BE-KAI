<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_subsystem_openings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('unit_kerja_id');
            $table->unsignedBigInteger('asset_subsystem_id');
            $table->string('source_key', 64);
            $table->unsignedInteger('sparepart_in')->default(0);
            $table->unsignedInteger('sparepart_out')->default(0);
            $table->timestamps();

            $table->foreign('unit_kerja_id', 'uso_unit_fk')
                ->references('id')->on('unit_kerjas')->restrictOnDelete();
            $table->foreign('asset_subsystem_id', 'uso_subsystem_fk')
                ->references('id')->on('asset_subsystems')->restrictOnDelete();
            $table->unique('source_key', 'uso_source_key_unique');
            $table->unique(['unit_kerja_id', 'asset_subsystem_id'], 'uso_unit_subsystem_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_subsystem_openings');
    }
};
