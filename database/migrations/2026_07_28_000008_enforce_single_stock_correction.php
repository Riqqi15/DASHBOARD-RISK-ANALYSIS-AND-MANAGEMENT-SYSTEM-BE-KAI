<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'stock_movements_one_correction_per_source';

    private const FALLBACK_INDEX = 'stock_movements_reverses_movement_lookup';

    public function up(): void
    {
        if (! Schema::hasIndex('stock_movements', self::INDEX)) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->unique('reverses_movement_id', self::INDEX);
            });
        }

        if (Schema::hasIndex('stock_movements', self::FALLBACK_INDEX)) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropIndex(self::FALLBACK_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('stock_movements', self::FALLBACK_INDEX)) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->index('reverses_movement_id', self::FALLBACK_INDEX);
            });
        }

        if (Schema::hasIndex('stock_movements', self::INDEX)) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
