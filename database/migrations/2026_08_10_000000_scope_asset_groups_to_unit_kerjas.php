<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_groups', function (Blueprint $table): void {
            $table->foreignId('unit_kerja_id')
                ->nullable()
                ->after('id')
                ->constrained('unit_kerjas')
                ->nullOnDelete();

            $table->dropUnique('asset_groups_normalized_name_unique');
            $table->unique(['unit_kerja_id', 'normalized_name'], 'asset_groups_unit_normalized_unique');
        });

        Schema::table('asset_category_source_aliases', function (Blueprint $table): void {
            $table->foreignId('unit_kerja_id')
                ->nullable()
                ->after('id')
                ->constrained('unit_kerjas')
                ->nullOnDelete();

            $table->dropUnique('asset_category_alias_source_unique');
            $table->unique(
                ['category_type', 'unit_kerja_id', 'normalized_source_path'],
                'asset_category_alias_unit_source_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('asset_category_source_aliases', function (Blueprint $table): void {
            $table->dropUnique('asset_category_alias_unit_source_unique');
            $table->unique(
                ['category_type', 'normalized_source_path'],
                'asset_category_alias_source_unique',
            );
            $table->dropConstrainedForeignId('unit_kerja_id');
        });

        Schema::table('asset_groups', function (Blueprint $table): void {
            $table->dropUnique('asset_groups_unit_normalized_unique');
            $table->unique('normalized_name', 'asset_groups_normalized_name_unique');
            $table->dropConstrainedForeignId('unit_kerja_id');
        });
    }
};
