<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\AssetSubsystem;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_kerja_id' => UnitKerja::factory(),
            'asset_subsystem_id' => AssetSubsystem::factory(),
            'nama_aset' => fake()->words(3, true),
            'aset_prasarana_sintel' => 'PERALATAN LUAR SINYAL ELEKTRIK',
            'system' => 'PERAGA SINYAL ELEKTRIK',
            'subsystem' => fake()->randomElement(['TRACK CIRCUIT', 'AXLE COUNTER']),
            'lokasi' => fake()->optional()->city(),
            'jumlah_unit' => fake()->numberBetween(0, 100),
            'tanggal_pemasangan' => fake()->optional()->dateTimeBetween('-20 years'),
            'status' => AssetStatus::Aktif,
            'source_key' => null,
        ];
    }
}
