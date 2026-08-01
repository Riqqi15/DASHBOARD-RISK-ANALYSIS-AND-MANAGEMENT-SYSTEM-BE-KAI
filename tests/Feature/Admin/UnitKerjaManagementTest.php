<?php

namespace Tests\Feature\Admin;

use App\Enums\UnitType;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnitKerjaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_can_create_and_update_a_unit_with_audit_records(): void
    {
        $pusat = User::factory()->pusat()->create();

        $this->actingAs($pusat)->post('/admin/units', [
            'code' => 'daop-x',
            'name' => 'Daerah Operasi X',
            'type' => 'daop',
            'is_active' => true,
        ])->assertRedirect('/admin/units');

        $unit = UnitKerja::query()->where('code', 'DAOP-X')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'unit.created',
            'auditable_id' => $unit->id,
        ]);

        $this->actingAs($pusat)->put("/admin/units/{$unit->id}", [
            'code' => 'DAOP-X',
            'name' => 'Daerah Operasi Sepuluh',
            'type' => 'daop',
            'is_active' => false,
        ])->assertRedirect('/admin/units');

        $this->assertDatabaseHas('unit_kerjas', [
            'id' => $unit->id,
            'name' => 'Daerah Operasi Sepuluh',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'unit.updated',
            'auditable_id' => $unit->id,
        ]);
    }

    public function test_unit_account_cannot_access_unit_management(): void
    {
        $user = User::factory()->unit()->create();

        $this->actingAs($user)->get('/admin/units')->assertForbidden();
        $this->actingAs($user)->post('/admin/units', [])->assertForbidden();
    }

    public function test_unit_code_must_be_unique_and_type_must_be_supported(): void
    {
        $pusat = User::factory()->pusat()->create();
        UnitKerja::factory()->create(['code' => 'DAOP-1']);

        $this->actingAs($pusat)->post('/admin/units', [
            'code' => 'daop-1',
            'name' => 'Duplicate',
            'type' => 'unknown',
            'is_active' => true,
        ])->assertSessionHasErrors(['code', 'type']);
    }

    public function test_index_supports_allow_listed_filters_and_pagination(): void
    {
        $pusat = User::factory()->pusat()->create();
        UnitKerja::factory()->create([
            'code' => 'DAOP-SEARCH',
            'name' => 'Unit Dicari',
            'type' => UnitType::Daop,
            'is_active' => true,
        ]);
        UnitKerja::factory()->create([
            'code' => 'DIVRE-HIDDEN',
            'name' => 'Unit Lain',
            'type' => UnitType::Divre,
            'is_active' => false,
        ]);

        $this->actingAs($pusat)->get('/admin/units?search=dicari&type=daop&status=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Units/Index')
                ->where('filters.search', 'dicari')
                ->where('filters.type', 'daop')
                ->where('filters.status', '1')
                ->has('units.data', 1)
                ->where('units.data.0.code', 'DAOP-SEARCH'));
    }

    public function test_index_includes_only_regional_accounts_for_each_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-ACCOUNT']);
        $regional = User::factory()->unit($unit)->create([
            'name' => 'Operator Wilayah',
            'username' => 'operator.wilayah',
        ]);
        $this->actingAs($pusat)->get('/admin/units?search=operator.wilayah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Units/Index')
                ->has('units.data', 1)
                ->where('units.data.0.id', $unit->id)
                ->has('units.data.0.accounts', 1)
                ->where('units.data.0.accounts.0.id', $regional->id)
                ->where('units.data.0.accounts.0.username', 'operator.wilayah'));
    }
}
