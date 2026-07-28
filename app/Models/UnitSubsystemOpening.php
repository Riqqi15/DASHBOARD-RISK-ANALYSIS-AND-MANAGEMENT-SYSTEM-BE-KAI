<?php

namespace App\Models;

use Database\Factories\UnitSubsystemOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'unit_kerja_id',
    'asset_subsystem_id',
    'source_key',
    'sparepart_in',
    'sparepart_out',
])]
class UnitSubsystemOpening extends Model
{
    /** @use HasFactory<UnitSubsystemOpeningFactory> */
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'sparepart_in' => 'integer',
            'sparepart_out' => 'integer',
        ];
    }
}
