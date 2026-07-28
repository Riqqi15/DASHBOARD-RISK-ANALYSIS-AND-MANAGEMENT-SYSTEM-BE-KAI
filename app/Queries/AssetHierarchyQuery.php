<?php

namespace App\Queries;

use App\Models\AssetSubsystem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AssetHierarchyQuery
{
    /**
     * @param  list<int>|null  $subsystemIds
     * @return Collection<int, AssetSubsystem>
     */
    public function forUser(User $user, ?int $unitId = null, ?array $subsystemIds = null): Collection
    {
        $effectiveUnitId = $user->isUnit() ? $user->unit_kerja_id : $unitId;

        return AssetSubsystem::query()
            ->select('asset_subsystems.*')
            ->join('asset_systems', 'asset_systems.id', '=', 'asset_subsystems.asset_system_id')
            ->join('asset_groups', 'asset_groups.id', '=', 'asset_systems.asset_group_id')
            ->when(
                $subsystemIds !== null,
                fn (Builder $query): Builder => $query->whereIn('asset_subsystems.id', $subsystemIds),
            )
            ->with('assetSystem.assetGroup')
            ->withSum(
                ['assets as total' => fn (Builder $assets): Builder => $assets->when(
                    $effectiveUnitId !== null,
                    fn (Builder $visible): Builder => $visible->where('unit_kerja_id', $effectiveUnitId),
                )],
                'jumlah_unit',
            )
            ->withSum(
                ['openings as sparepart_in' => fn (Builder $openings): Builder => $openings->when(
                    $effectiveUnitId !== null,
                    fn (Builder $visible): Builder => $visible->where('unit_kerja_id', $effectiveUnitId),
                )],
                'sparepart_in',
            )
            ->withSum(
                ['openings as sparepart_out' => fn (Builder $openings): Builder => $openings->when(
                    $effectiveUnitId !== null,
                    fn (Builder $visible): Builder => $visible->where('unit_kerja_id', $effectiveUnitId),
                )],
                'sparepart_out',
            )
            ->orderBy('asset_groups.sort_order')
            ->orderBy('asset_groups.name')
            ->orderBy('asset_systems.sort_order')
            ->orderBy('asset_systems.name')
            ->orderBy('asset_subsystems.sort_order')
            ->orderBy('asset_subsystems.name')
            ->get()
            ->each(function (AssetSubsystem $subsystem): void {
                $subsystem->setAttribute('total', (int) ($subsystem->getAttribute('total') ?? 0));
                $subsystem->setAttribute('sparepart_in', (int) ($subsystem->getAttribute('sparepart_in') ?? 0));
                $subsystem->setAttribute('sparepart_out', (int) ($subsystem->getAttribute('sparepart_out') ?? 0));
            });
    }
}
