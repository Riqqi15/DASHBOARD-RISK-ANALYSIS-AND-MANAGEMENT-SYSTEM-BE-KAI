<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssetCategoryLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'normalized_name', 'position', 'is_active'])]
class AssetCategoryLevel extends Model
{
    /** @use HasFactory<AssetCategoryLevelFactory> */
    use HasFactory, SoftDeletes;

    public function nodes(): HasMany
    {
        return $this->hasMany(AssetCategoryNode::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $level): void {
            $name = preg_replace('/^\s+|\s+$/u', '', $level->name) ?? trim($level->name);
            $level->name = preg_replace('/\s+/u', ' ', $name) ?? $name;
            $level->normalized_name = mb_strtolower($level->name);
        });
    }

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_active' => 'boolean'];
    }
}
