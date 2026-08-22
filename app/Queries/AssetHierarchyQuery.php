<?php

namespace App\Queries;

use App\Models\AssetSubsystem;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetHierarchyQuery
{
    /**
     * @param  list<int>|null  $subsystemIds
     * @return Collection<int, AssetSubsystem>
     */
    public function forUser(User $user, ?int $unitId = null, ?array $subsystemIds = null): Collection
    {
        $effectiveUnitId = $user->isUnit()
            ? $user->unit_kerja_id
            : UnitKerja::query()->where('is_active', true)->whereKey($unitId)->value('id');

        if ($effectiveUnitId === null) {
            return collect();
        }

        $ledger = fn (string $direction) => DB::table('stock_movements')
            ->join('spare_parts', 'spare_parts.id', '=', 'stock_movements.spare_part_id')
            ->whereColumn('spare_parts.asset_subsystem_id', 'asset_subsystems.id')
            ->where('stock_movements.direction', $direction)
            ->where('stock_movements.unit_kerja_id', $effectiveUnitId)
            ->selectRaw('COALESCE(SUM(stock_movements.quantity), 0)');

        return AssetSubsystem::query()
            ->select('asset_subsystems.*')
            ->selectSub($ledger('in'), 'ledger_in')
            ->selectSub($ledger('out'), 'ledger_out')
            ->join('asset_systems', 'asset_systems.id', '=', 'asset_subsystems.asset_system_id')
            ->join('asset_groups', 'asset_groups.id', '=', 'asset_systems.asset_group_id')
            ->when(
                $subsystemIds !== null,
                fn (Builder $query): Builder => $query->whereIn('asset_subsystems.id', $subsystemIds),
            )
            ->with('assetSystem.assetGroup')
            ->withSum(
                [
                    'assets as total' => fn (Builder $assets): Builder => $assets->where(
                        'unit_kerja_id',
                        $effectiveUnitId,
                    ),
                ],
                'jumlah_unit',
            )
            ->withSum(
                [
                    'openings as sparepart_in' => fn (Builder $openings): Builder => $openings->where(
                        'unit_kerja_id',
                        $effectiveUnitId,
                    ),
                ],
                'sparepart_in',
            )
            ->withSum(
                [
                    'openings as sparepart_out' => fn (Builder $openings): Builder => $openings->where(
                        'unit_kerja_id',
                        $effectiveUnitId,
                    ),
                ],
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
                $subsystem->setAttribute(
                    'sparepart_in',
                    (int) ($subsystem->getAttribute('sparepart_in') ?? 0) +
                        (int) ($subsystem->getAttribute('ledger_in') ?? 0),
                );
                $subsystem->setAttribute(
                    'sparepart_out',
                    (int) ($subsystem->getAttribute('sparepart_out') ?? 0) +
                        (int) ($subsystem->getAttribute('ledger_out') ?? 0),
                );
                $subsystem->offsetUnset('ledger_in');
                $subsystem->offsetUnset('ledger_out');
            });
    }
}
