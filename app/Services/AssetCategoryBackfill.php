<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class AssetCategoryBackfill
{
    public function __construct(private readonly AssetCategoryResolver $resolver) {}

    /** @return array{linked: int, skipped: int} */
    public function run(): array
    {
        $linked = 0;
        $skipped = 0;

        Asset::query()
            ->whereNull('asset_subsystem_id')
            ->orderBy('id')
            ->chunkById(100, function ($assets) use (&$linked, &$skipped): void {
                foreach ($assets as $asset) {
                    $group = (string) $asset->aset_prasarana_sintel;
                    $system = (string) $asset->system;
                    $subsystem = (string) $asset->subsystem;

                    if (
                        $this->resolver->normalize($group) === ''
                        || $this->resolver->normalize($system) === ''
                        || $this->resolver->normalize($subsystem) === ''
                    ) {
                        $skipped++;

                        continue;
                    }

                    $wasLinked = DB::transaction(function () use ($asset, $group, $system, $subsystem): bool {
                        $lockedAsset = Asset::query()
                            ->whereKey($asset->id)
                            ->whereNull('asset_subsystem_id')
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedAsset) {
                            return false;
                        }

                        $categories = $this->resolver->resolve(
                            $group,
                            $system,
                            $subsystem,
                            'legacy-database',
                            'assets',
                            $lockedAsset->id,
                        );

                        $lockedAsset->asset_subsystem_id = $categories['subsystem']->id;
                        $lockedAsset->save();

                        return true;
                    });

                    if ($wasLinked) {
                        $linked++;
                    }
                }
            });

        return compact('linked', 'skipped');
    }
}
