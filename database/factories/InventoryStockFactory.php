<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_kerja_id' => UnitKerja::factory(),
            'spare_part_id' => SparePart::factory(),
            'quantity' => fake()->numberBetween(0, 100),
        ];
    }
}
