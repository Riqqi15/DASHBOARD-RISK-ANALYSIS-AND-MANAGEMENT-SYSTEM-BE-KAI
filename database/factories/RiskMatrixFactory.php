<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\RiskMatrix;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiskMatrix> */
class RiskMatrixFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'likelihood' => fake()->numberBetween(1, 4),
            'consequence' => fake()->numberBetween(1, 4),
            'assessed_at' => now(),
        ];
    }
}
