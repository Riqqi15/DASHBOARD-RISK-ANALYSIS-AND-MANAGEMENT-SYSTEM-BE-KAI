<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Models\UnitKerja;
use Illuminate\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'DAOP-1', 'name' => 'Daerah Operasi 1', 'type' => UnitType::Daop],
            ['code' => 'DAOP-2', 'name' => 'Daerah Operasi 2', 'type' => UnitType::Daop],
            ['code' => 'DAOP-3', 'name' => 'Daerah Operasi 3', 'type' => UnitType::Daop],
            ['code' => 'DAOP-4', 'name' => 'Daerah Operasi 4', 'type' => UnitType::Daop],
            ['code' => 'DAOP-5', 'name' => 'Daerah Operasi 5', 'type' => UnitType::Daop],
            ['code' => 'DAOP-6', 'name' => 'Daerah Operasi 6', 'type' => UnitType::Daop],
            ['code' => 'DAOP-7', 'name' => 'Daerah Operasi 7', 'type' => UnitType::Daop],
            ['code' => 'DAOP-8', 'name' => 'Daerah Operasi 8', 'type' => UnitType::Daop],
            ['code' => 'DAOP-9', 'name' => 'Daerah Operasi 9', 'type' => UnitType::Daop],
            ['code' => 'DIVRE-I', 'name' => 'Divisi Regional I', 'type' => UnitType::Divre],
            ['code' => 'DIVRE-II', 'name' => 'Divisi Regional II', 'type' => UnitType::Divre],
            ['code' => 'DIVRE-III', 'name' => 'Divisi Regional III', 'type' => UnitType::Divre],
            ['code' => 'DIVRE-IV', 'name' => 'Divisi Regional IV', 'type' => UnitType::Divre],
        ];

        foreach ($units as $unit) {
            UnitKerja::query()->updateOrCreate(
                ['code' => $unit['code']],
                ['name' => $unit['name'], 'type' => $unit['type'], 'is_active' => true],
            );
        }
    }
}
