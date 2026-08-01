<?php

namespace Database\Factories;

use App\Enums\RiskRegisterStatus;
use App\Models\Asset;
use App\Models\RiskRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiskRegister> */
class RiskRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'part_number' => fake()->optional()->bothify('PART-####'),
            'sub' => fake()->optional()->word(),
            'risk_event' => fake()->sentence(4),
            'risk_cause' => fake()->sentence(),
            'impact' => fake()->optional()->sentence(),
            'part_name' => fake()->optional()->words(3, true),
            'recommendation' => fake()->optional()->sentence(),
            'likelihood' => fake()->numberBetween(1, 4),
            'consequence' => fake()->numberBetween(1, 4),
            'status' => fake()->randomElement(RiskRegisterStatus::cases()),
        ];
    }
}
