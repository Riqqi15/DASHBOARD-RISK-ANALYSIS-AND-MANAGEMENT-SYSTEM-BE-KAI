<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_matrices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('consequence');
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE risk_matrices ADD CONSTRAINT chk_risk_matrices_likelihood CHECK (likelihood BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE risk_matrices ADD CONSTRAINT chk_risk_matrices_consequence CHECK (consequence BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_matrices');
    }
};
