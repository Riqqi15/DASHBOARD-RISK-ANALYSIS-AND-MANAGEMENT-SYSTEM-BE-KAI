<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Query the table directly so soft-deleted assets are included in the preflight.
        $uncategorizedAssetCount = DB::table('assets')->whereNull('asset_subsystem_id')->count();

        if ($uncategorizedAssetCount > 0) {
            throw new RuntimeException(
                sprintf(
                    'Cannot make assets.asset_subsystem_id required: %d asset(s), '
                        .'including soft-deleted assets, still have NULL category linkage.',
                    $uncategorizedAssetCount,
                ),
            );
        }

        DB::statement(
            <<<'SQL'
            ALTER TABLE assets
            MODIFY asset_subsystem_id BIGINT UNSIGNED NOT NULL
            SQL
            ,
        );
    }

    public function down(): void
    {
        DB::statement(
            <<<'SQL'
            ALTER TABLE assets
            MODIFY asset_subsystem_id BIGINT UNSIGNED NULL
            SQL
            ,
        );
    }
};
