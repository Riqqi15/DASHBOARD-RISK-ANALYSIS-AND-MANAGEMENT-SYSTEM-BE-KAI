<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssetCategoryLevel;
use App\Models\AssetCategoryNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetCategoryNode> */
class AssetCategoryNodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_category_level_id' => AssetCategoryLevel::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
