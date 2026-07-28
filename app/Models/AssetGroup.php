<?php

namespace App\Models;

use Database\Factories\AssetGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'normalized_name', 'sort_order', 'is_active'])]
class AssetGroup extends Model
{
    /** @use HasFactory<AssetGroupFactory> */
    use HasFactory, SoftDeletes;

    public function systems(): HasMany
    {
        return $this->hasMany(AssetSystem::class)->orderBy('sort_order')->orderBy('name');
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
