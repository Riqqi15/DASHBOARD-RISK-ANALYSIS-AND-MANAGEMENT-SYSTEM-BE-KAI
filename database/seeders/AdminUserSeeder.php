<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('rams.admin');

        if (! $admin['name'] || ! $admin['email'] || ! $admin['password']) {
            throw new RuntimeException('Set RAMS_ADMIN_NAME, RAMS_ADMIN_EMAIL, and RAMS_ADMIN_PASSWORD before seeding.');
        }

        User::query()->updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'role' => UserRole::Pusat,
                'unit_kerja_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
