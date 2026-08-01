<?php

namespace Database\Factories;

use App\Models\AssetSubsystem;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SparePart>
 */
class SparePartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_subsystem_id' => AssetSubsystem::factory(),
            'code' => fake()->unique()->bothify('SP-??-####'),
            'source_key' => hash('sha256', fake()->unique()->uuid()),
            'equipment' => fake()->words(2, true),
            'detail_equipment' => fake()->words(3, true),
            'max_yearly_failure' => fake()->randomFloat(2, 0, 20),
            'average_yearly_failure' => fake()->randomFloat(2, 0, 10),
            'max_lead_time_months' => fake()->randomFloat(2, 0, 12),
            'average_lead_time_months' => fake()->randomFloat(2, 0, 6),
            'safety_stock' => 5,
            'lead_time_demand' => 10,
            'reorder_point' => 15,
            'reorder_calculation_status' => 'calculated',
            'reorder_formula_version' => 'kai-reorder-v1.0.0',
            'reorder_calculated_at' => now(),
            'severity' => fake()->randomElement(['Low', 'Medium', 'High']),
            'unit_of_measure' => 'unit',
            'is_active' => true,
        ];
    }
}
