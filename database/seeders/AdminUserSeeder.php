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

        if (! $admin['name'] || ! $admin['username'] || ! $admin['password']) {
            throw new RuntimeException('Set RAMS_ADMIN_NAME, RAMS_ADMIN_USERNAME, and RAMS_ADMIN_PASSWORD before seeding.');
        }

        User::query()->updateOrCreate(
            ['username' => $admin['username']],
            [
                'name' => $admin['name'],
                'email' => $admin['email'] ?: null,
                'password' => $admin['password'],
                'role' => UserRole::Pusat,
                'unit_kerja_id' => null,
                'is_active' => true,
                'email_verified_at' => $admin['email'] ? now() : null,
            ],
        );
    }
}
