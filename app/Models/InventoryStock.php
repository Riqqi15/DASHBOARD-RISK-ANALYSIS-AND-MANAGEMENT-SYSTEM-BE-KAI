<?php

namespace App\Models;

use Database\Factories\InventoryStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['unit_kerja_id', 'spare_part_id', 'quantity'])]
class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory;

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isUnit(),
            fn (Builder $visible): Builder => $visible->where(
                $visible->getModel()->qualifyColumn('unit_kerja_id'),
                $user->unit_kerja_id,
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
