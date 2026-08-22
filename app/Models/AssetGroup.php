<?php

namespace App\Models;

use Database\Factories\AssetGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[
    Fillable([
        'unit_kerja_id',
        'name',
        'normalized_name',
        'sort_order',
        'dashboard_color',
        'dashboard_color_source',
        'is_active',
    ]),
]
class AssetGroup extends Model
{
    /** @use HasFactory<AssetGroupFactory> */
    use HasFactory, SoftDeletes;

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function systems(): HasMany
    {
        return $this->hasMany(AssetSystem::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForUnit(Builder $query, UnitKerja|int $unit): Builder
    {
        $unitId = $unit instanceof UnitKerja ? $unit->id : $unit;

        return $query->where(
            fn (Builder $scope): Builder => $scope
                ->where('unit_kerja_id', $unitId)
                ->orWhere(
                    fn (Builder $legacy): Builder => $legacy
                        ->whereNull('unit_kerja_id')
                        ->whereHas(
                            'systems.subsystems.assets',
                            fn (Builder $assets): Builder => $assets->where('unit_kerja_id', $unitId),
                        ),
                ),
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $name = preg_replace('/^\s+|\s+$/u', '', $category->name) ?? trim($category->name);
            $category->name = preg_replace("/\s+/u", ' ', $name) ?? $name;
            $category->normalized_name = mb_strtolower($category->name);
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
