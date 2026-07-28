<?php

namespace Database\Factories;

use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetSubsystem>
 */
class AssetSubsystemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_system_id' => AssetSystem::factory(),
            'name' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
