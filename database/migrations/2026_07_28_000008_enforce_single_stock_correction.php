<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'stock_movements_one_correction_per_source';

    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique('reverses_movement_id', self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }
};
