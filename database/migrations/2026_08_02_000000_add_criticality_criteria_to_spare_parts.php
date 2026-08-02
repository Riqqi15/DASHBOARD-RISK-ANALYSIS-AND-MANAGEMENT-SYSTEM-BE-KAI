<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table): void {
            if (! Schema::hasColumn('spare_parts', 'function_criterion')) {
                $table->unsignedTinyInteger('function_criterion')->nullable()->after('reorder_point');
            }

            if (! Schema::hasColumn('spare_parts', 'production_impact')) {
                $table->unsignedTinyInteger('production_impact')->nullable()->after('function_criterion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table): void {
            if (Schema::hasColumn('spare_parts', 'production_impact')) {
                $table->dropColumn('production_impact');
            }

            if (Schema::hasColumn('spare_parts', 'function_criterion')) {
                $table->dropColumn('function_criterion');
            }
        });
    }
};
