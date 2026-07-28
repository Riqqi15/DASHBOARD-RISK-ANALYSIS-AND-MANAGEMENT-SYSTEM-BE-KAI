<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
            $table->string('nama_aset');
            $table->string('aset_prasarana_sintel');
            $table->string('system');
            $table->string('subsystem');
            $table->string('lokasi')->nullable();
            $table->unsignedInteger('jumlah_unit')->default(0);
            $table->date('tanggal_pemasangan')->nullable();
            $table->string('status', 32)->default('aktif');
            $table->char('source_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_kerja_id', 'status']);
            $table->index(['unit_kerja_id', 'system', 'subsystem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
