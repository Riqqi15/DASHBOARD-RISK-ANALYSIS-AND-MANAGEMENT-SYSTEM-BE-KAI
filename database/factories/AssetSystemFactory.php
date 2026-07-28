<?php

namespace Database\Factories;

use App\Models\AssetGroup;
use App\Models\AssetSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetSystem>
 */
class AssetSystemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_group_id' => AssetGroup::factory(),
            'name' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
