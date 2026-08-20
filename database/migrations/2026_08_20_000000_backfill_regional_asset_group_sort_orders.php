<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $unitIds = DB::table('asset_groups')
            ->whereNotNull('unit_kerja_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('unit_kerja_id');

        foreach ($unitIds as $unitId) {
            $groups = DB::table('asset_groups')
                ->where('unit_kerja_id', $unitId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'name', 'sort_order']);

            $highestPosition = $groups->reduce(function (int $highest, object $group): int {
                $namePosition = preg_match('/^\s*(\d+)\s*[.\-]/u', (string) $group->name, $matches)
                    ? (int) $matches[1]
                    : 0;

                return max($highest, (int) $group->sort_order, $namePosition);
            }, 0);

            foreach ($groups as $group) {
                $hasNumberedName = preg_match('/^\s*(\d+)\s*[.\-]/u', (string) $group->name, $matches) === 1;
                if ($hasNumberedName) {
                    DB::table('asset_groups')->where('id', $group->id)->update([
                        'sort_order' => (int) $matches[1],
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                if ((int) $group->sort_order > 0) {
                    continue;
                }

                $highestPosition++;
                DB::table('asset_groups')->where('id', $group->id)->update([
                    'sort_order' => $highestPosition,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Existing order values cannot be restored safely without discarding user changes.
    }
};
