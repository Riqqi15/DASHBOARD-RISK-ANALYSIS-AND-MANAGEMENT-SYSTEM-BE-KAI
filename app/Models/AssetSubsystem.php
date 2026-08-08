<?php

namespace App\Models;

use Database\Factories\AssetSubsystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['asset_system_id', 'name', 'normalized_name', 'sort_order', 'dashboard_color', 'dashboard_color_source', 'is_active'])]
class AssetSubsystem extends Model
{
    /** @use HasFactory<AssetSubsystemFactory> */
    use HasFactory, SoftDeletes;

    public function assetSystem(): BelongsTo
    {
        return $this->belongsTo(AssetSystem::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function openings(): HasMany
    {
        return $this->unitSubsystemOpenings();
    }

    public function unitSubsystemOpenings(): HasMany
    {
        return $this->hasMany(UnitSubsystemOpening::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $name = preg_replace('/^\s+|\s+$/u', '', $category->name) ?? trim($category->name);
            $category->name = preg_replace('/\s+/u', ' ', $name) ?? $name;
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
