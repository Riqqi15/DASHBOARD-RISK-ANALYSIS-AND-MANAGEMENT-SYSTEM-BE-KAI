<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssetCategoryLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetCategoryLevel> */
class AssetCategoryLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'position' => fake()->unique()->numberBetween(10, 10000),
            'is_active' => true,
        ];
    }
}
