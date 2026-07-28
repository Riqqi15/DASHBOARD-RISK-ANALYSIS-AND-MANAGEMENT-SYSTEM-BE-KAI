<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'unit_kerja_id',
    'asset_subsystem_id',
    'nama_aset',
    'aset_prasarana_sintel',
    'system',
    'subsystem',
    'lokasi',
    'jumlah_unit',
    'tanggal_pemasangan',
    'status',
    'source_key',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function assetSubsystem(): BelongsTo
    {
        return $this->belongsTo(AssetSubsystem::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isUnit(),
            fn (Builder $visible): Builder => $visible->where('unit_kerja_id', $user->unit_kerja_id),
        );
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when(
            $search,
            fn (Builder $assets, string $term): Builder => $assets->where(
                fn (Builder $fields): Builder => $fields
                    ->where('nama_aset', 'like', "%{$term}%")
                    ->orWhere('lokasi', 'like', "%{$term}%")
                    ->orWhereHas('assetSubsystem', fn (Builder $subsystem): Builder => $subsystem->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('assetSubsystem.assetSystem', fn (Builder $system): Builder => $system->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('assetSubsystem.assetSystem.assetGroup', fn (Builder $group): Builder => $group->where('name', 'like', "%{$term}%"))
                    ->orWhere(fn (Builder $legacy): Builder => $legacy
                        ->whereNull('asset_subsystem_id')
                        ->where(fn (Builder $snapshots): Builder => $snapshots
                            ->where('aset_prasarana_sintel', 'like', "%{$term}%")
                            ->orWhere('system', 'like', "%{$term}%")
                            ->orWhere('subsystem', 'like', "%{$term}%"))),
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'jumlah_unit' => 'integer',
            'tanggal_pemasangan' => 'date',
            'status' => AssetStatus::class,
        ];
    }
}
