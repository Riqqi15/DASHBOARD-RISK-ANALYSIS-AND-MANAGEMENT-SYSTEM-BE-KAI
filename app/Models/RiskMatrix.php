<?php

namespace App\Models;

use App\Services\RiskAssessmentCalculator;
use Database\Factories\RiskMatrixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id', 'source_key', 'workbook_hash', 'workbook_name', 'sheet_name', 'source_row',
    'likelihood', 'consequence', 'excel_values', 'excel_formulas', 'parity_status',
    'parity_differences', 'formula_version', 'assessed_at',
])]
class RiskMatrix extends Model
{
    /** @use HasFactory<RiskMatrixFactory> */
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

    public function getRatingAttribute(): int
    {
        return $this->likelihood * $this->consequence;
    }

    public function getLevelAttribute(): string
    {
        return app(RiskAssessmentCalculator::class)->level($this->likelihood, $this->consequence);
    }

    protected function casts(): array
    {
        return [
            'likelihood' => 'integer',
            'consequence' => 'integer',
            'excel_values' => 'array',
            'excel_formulas' => 'array',
            'parity_differences' => 'array',
            'assessed_at' => 'datetime',
        ];
    }
}
