<?php

namespace Database\Factories;

use App\Enums\UnitType;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitKerja>
 */
class UnitKerjaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TEST-####'),
            'name' => fake()->company(),
            'type' => fake()->randomElement(UnitType::cases()),
            'is_active' => true,
        ];
    }
}
