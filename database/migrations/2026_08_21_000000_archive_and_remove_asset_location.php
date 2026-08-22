<?php

use App\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assets', 'lokasi')) {
            return;
        }

        DB::table('assets')
            ->whereNotNull('lokasi')
            ->whereRaw("TRIM(lokasi) <> ''")
            ->orderBy('id')
            ->chunkById(500, function ($assets): void {
                $createdAt = now();
                $rows = $assets->map(fn ($asset): array => [
                    'actor_id' => null,
                    'action' => 'asset.location_archived',
                    'auditable_type' => Asset::class,
                    'auditable_id' => $asset->id,
                    'unit_kerja_id' => $asset->unit_kerja_id,
                    'old_values' => json_encode(['lokasi' => $asset->lokasi], JSON_THROW_ON_ERROR),
                    'new_values' => json_encode([], JSON_THROW_ON_ERROR),
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => $createdAt,
                ])->all();

                DB::table('audit_logs')->insert($rows);
            });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('lokasi');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('assets', 'lokasi')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table): void {
            $table->string('lokasi')->nullable()->after('subsystem');
        });
    }
};
