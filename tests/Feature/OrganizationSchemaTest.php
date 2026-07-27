<?php

namespace Tests\Feature;

use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_units_and_user_roles_are_persisted_with_enum_casts(): void
    {
        $unit = UnitKerja::factory()->create(['type' => UnitType::Daop]);
        $user = User::factory()->unit($unit)->create();

        $this->assertSame(UnitType::Daop, $unit->fresh()->type);
        $this->assertSame(UserRole::Unit, $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->fresh()->unitKerja->is($unit));
    }

    public function test_unit_seeder_creates_all_thirteen_regional_units(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $this->assertDatabaseCount('unit_kerjas', 13);
        $this->assertDatabaseHas('unit_kerjas', ['code' => 'DAOP-1']);
        $this->assertDatabaseHas('unit_kerjas', ['code' => 'DIVRE-IV']);
    }

    public function test_database_rejects_an_active_regional_user_without_a_unit(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Akun Tanpa Unit',
            'email' => 'unscoped@example.test',
            'password' => 'irrelevant-test-hash',
            'role' => UserRole::Unit->value,
            'unit_kerja_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
