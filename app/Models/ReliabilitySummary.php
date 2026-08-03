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
    'excel_snapshot_id',
    'period',
    'baseline_date',
    'calculation_date',
    'unit_count',
    'operating_minutes',
    'downtime_minutes',
    'operating_hours',
    'downtime_value',
    'uptime_hours',
    'failure_count',
    'mttf_hours',
    'mtbf_hours',
    'mttr_hours',
    'failure_rate',
    'reliability',
    'availability',
    'spare_part_replacement_count',
    'vandalism_count',
    'calculation_profile',
    'parity_status',
    'parity_differences',
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

    public function excelSnapshot(): BelongsTo
    {
        return $this->belongsTo(ReliabilityExcelSnapshot::class, 'excel_snapshot_id');
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
            'baseline_date' => 'immutable_date',
            'calculation_date' => 'immutable_date',
            'unit_count' => 'integer',
            'operating_minutes' => 'integer',
            'downtime_minutes' => 'integer',
            'operating_hours' => 'decimal:6',
            'downtime_value' => 'decimal:6',
            'uptime_hours' => 'decimal:6',
            'failure_count' => 'integer',
            'mttf_hours' => 'decimal:4',
            'mtbf_hours' => 'decimal:4',
            'mttr_hours' => 'decimal:4',
            'failure_rate' => 'decimal:10',
            'reliability' => 'decimal:10',
            'availability' => 'decimal:10',
            'spare_part_replacement_count' => 'integer',
            'vandalism_count' => 'integer',
            'calculation_profile' => 'array',
            'parity_differences' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
