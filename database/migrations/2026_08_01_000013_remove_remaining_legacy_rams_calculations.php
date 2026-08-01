<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $legacyAssetSourceKeys = [
        'b66bf26817b0de73c1b595ef0e2d315b7f7272fffb25f93f0f7f27eb13f65c68',
        'b033414794b76660aef61c18157dbaff08fd4199e00decb9e46afa0cdba63be2',
    ];

    /** @var list<string> */
    private array $legacyReliabilityAssetSourceKeys = [
        '25c6066c197615969825285e56a8626686341fda41f40c3287bec2fc0cd8a5e5',
        'b66bf26817b0de73c1b595ef0e2d315b7f7272fffb25f93f0f7f27eb13f65c68',
        'ff84bf139639f7fc5f0c6abf05892877126864d6a9bd179e03a5ed7bd0b689d2',
        '052c87d5ac1e94b82e4829a9b32d9143bbb828a35c0c6678dcdfa59908d74f20',
        'bc0d9d89485fb1a45587e41d699851dcb37f87a444ee7fde72c11fbaacb26d57',
    ];

    public function up(): void
    {
        DB::table('risk_matrices')
            ->whereIn('asset_id', DB::table('assets')->select('id')->whereIn('source_key', $this->legacyAssetSourceKeys))
            ->delete();

        DB::table('reliability_summaries')
            ->where('period', '2026-07-01')
            ->whereIn('asset_id', DB::table('assets')->select('id')->whereIn('source_key', $this->legacyReliabilityAssetSourceKeys))
            ->delete();
    }

    public function down(): void
    {
        // Removed rows were derived demo calculations and must not be restored.
    }
};
