<?php

namespace App\Models;

use Database\Factories\AssetSystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[
    Fillable([
        'asset_group_id',
        'name',
        'normalized_name',
        'sort_order',
        'dashboard_color',
        'dashboard_color_source',
        'is_active',
    ]),
]
class AssetSystem extends Model
{
    /** @use HasFactory<AssetSystemFactory> */
    use HasFactory, SoftDeletes;

    public function assetGroup(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class);
    }

    public function subsystems(): HasMany
    {
        return $this->hasMany(AssetSubsystem::class)->orderBy('sort_order')->orderBy('name');
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
