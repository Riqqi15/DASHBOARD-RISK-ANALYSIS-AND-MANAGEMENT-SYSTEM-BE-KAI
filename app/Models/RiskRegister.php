<?php

namespace App\Models;

use App\Enums\RiskRegisterStatus;
use Database\Factories\RiskRegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'source_key',
    'workbook_hash',
    'workbook_name',
    'sheet_name',
    'source_row',
    'part_number',
    'sub',
    'risk_event',
    'risk_cause',
    'impact',
    'part_name',
    'recommendation',
    'likelihood',
    'consequence',
    'status',
])]
class RiskRegister extends Model
{
    /** @use HasFactory<RiskRegisterFactory> */
    use HasFactory;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isUnit(),
            fn (Builder $visible): Builder => $visible->whereHas(
                'asset',
                fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $user->unit_kerja_id),
            ),
        );
    }

    public function getRatingAttribute(): ?int
    {
        return $this->likelihood && $this->consequence
            ? $this->likelihood * $this->consequence
            : null;
    }

    protected function casts(): array
    {
        return [
            'likelihood' => 'integer',
            'consequence' => 'integer',
            'status' => RiskRegisterStatus::class,
        ];
    }
}
