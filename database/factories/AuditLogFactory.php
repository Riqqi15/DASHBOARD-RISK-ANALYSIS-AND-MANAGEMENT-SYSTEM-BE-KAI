<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->pusat(),
            'action' => 'account.updated',
            'auditable_type' => User::class,
            'auditable_id' => User::factory()->unit(),
            'unit_kerja_id' => UnitKerja::factory(),
            'old_values' => ['name' => 'Nama Lama', 'email' => fake()->safeEmail()],
            'new_values' => ['name' => 'Nama Baru', 'email' => fake()->safeEmail()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
