<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class RegionalAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('rams.demo_accounts.enabled')) {
            return;
        }

        $password = config('rams.demo_accounts.daop_password');

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Set RAMS_DAOP_PASSWORD before seeding demo DAOP accounts.');
        }

        UnitKerja::query()
            ->where('type', UnitType::Daop->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->each(function (UnitKerja $unit) use ($password): void {
                $number = Str::after($unit->code, 'DAOP-');

                User::query()->updateOrCreate(
                    ['username' => 'daop'.Str::lower($number)],
                    [
                        'name' => "Operator Daop {$number}",
                        'email' => null,
                        'password' => $password,
                        'role' => UserRole::Unit,
                        'unit_kerja_id' => $unit->id,
                        'is_active' => true,
                    ],
                );
            });
    }
}
