<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_is_idempotent_and_uses_username_credentials(): void
    {
        config()->set('rams.admin', [
            'name' => 'Admin Pusat',
            'username' => 'admin.pusat',
            'email' => null,
            'password' => 'admin1234',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('username', 'admin.pusat')->sole();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('pusat', $admin->role->value);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('admin1234', $admin->password));
    }
}
