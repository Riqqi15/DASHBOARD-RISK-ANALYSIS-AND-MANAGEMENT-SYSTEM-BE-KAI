<?php

namespace App\Models;

use App\Services\RiskAssessmentCalculator;
use Database\Factories\RiskMatrixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asset_id', 'likelihood', 'consequence', 'assessed_at'])]
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
            'assessed_at' => 'datetime',
        ];
    }
}
