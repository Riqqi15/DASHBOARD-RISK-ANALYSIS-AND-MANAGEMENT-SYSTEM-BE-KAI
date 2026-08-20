<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssetCategoryNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'asset_category_level_id', 'parent_id', 'name', 'normalized_name', 'sort_order',
    'dashboard_color', 'dashboard_color_source', 'is_active', 'legacy_type', 'legacy_id',
])]
class AssetCategoryNode extends Model
{
    /** @use HasFactory<AssetCategoryNodeFactory> */
    use HasFactory, SoftDeletes;

    public function level(): BelongsTo
    {
        return $this->belongsTo(AssetCategoryLevel::class, 'asset_category_level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $node): void {
            $name = preg_replace('/^\s+|\s+$/u', '', $node->name) ?? trim($node->name);
            $node->name = preg_replace('/\s+/u', ' ', $name) ?? $name;
            $node->normalized_name = mb_strtolower($node->name);
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'legacy_id' => 'integer',
        ];
    }
}
