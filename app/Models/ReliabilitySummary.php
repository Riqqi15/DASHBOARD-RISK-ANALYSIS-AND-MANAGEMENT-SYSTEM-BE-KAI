<?php

namespace App\Models;

use Database\Factories\ReliabilitySummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'period',
    'operating_minutes',
    'downtime_minutes',
    'failure_count',
    'mttf_hours',
    'mtbf_hours',
    'mttr_hours',
    'failure_rate',
    'reliability',
    'availability',
    'calculation_status',
    'formula_version',
    'calculated_at',
])]
class ReliabilitySummary extends Model
{
    /** @use HasFactory<ReliabilitySummaryFactory> */
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

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'operating_minutes' => 'integer',
            'downtime_minutes' => 'integer',
            'failure_count' => 'integer',
            'mttf_hours' => 'decimal:4',
            'mtbf_hours' => 'decimal:4',
            'mttr_hours' => 'decimal:4',
            'failure_rate' => 'decimal:10',
            'reliability' => 'decimal:10',
            'availability' => 'decimal:10',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
