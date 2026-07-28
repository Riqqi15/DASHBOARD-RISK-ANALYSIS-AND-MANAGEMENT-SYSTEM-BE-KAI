<?php

namespace Database\Factories;

use App\Models\AssetSubsystem;
use App\Models\UnitKerja;
use App\Models\UnitSubsystemOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitSubsystemOpening>
 */
class UnitSubsystemOpeningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_kerja_id' => UnitKerja::factory(),
            'asset_subsystem_id' => AssetSubsystem::factory(),
            'source_key' => fn (array $attributes): string => hash('sha256', implode('|', [
                $attributes['unit_kerja_id'],
                $attributes['asset_subsystem_id'],
                'opening',
            ])),
            'sparepart_in' => fake()->numberBetween(0, 100),
            'sparepart_out' => fake()->numberBetween(0, 100),
        ];
    }
}
