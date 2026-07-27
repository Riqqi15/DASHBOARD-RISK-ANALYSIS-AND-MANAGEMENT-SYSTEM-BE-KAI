<?php

namespace Tests\Feature;

use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
