<?php

namespace Database\Factories;

use App\Models\AssetGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetGroup>
 */
class AssetGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
