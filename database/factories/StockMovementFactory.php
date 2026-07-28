<?php

namespace Database\Factories;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 20);

        return [
            'unit_kerja_id' => UnitKerja::factory(),
            'spare_part_id' => SparePart::factory(),
            'actor_id' => User::factory()->unit(),
            'type' => StockMovementType::In,
            'direction' => StockDirection::In,
            'quantity' => $quantity,
            'stock_before' => 0,
            'stock_after' => $quantity,
            'movement_date' => today(),
            'reference_number' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional()->sentence(),
            'reverses_movement_id' => null,
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }
}
