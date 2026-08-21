<?php

namespace Tests\Feature\Admin;

use App\Enums\UnitType;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\ReliabilitySummary;
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
            'operating_start_date' => '',
            'baseline_change_reason' => '',
            'baseline_change_confirmed' => false,
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

    public function test_baseline_change_requires_confirmation_reason_audit_and_recalculation(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create([
            'code' => 'DAOP-BASELINE',
            'operating_start_date' => null,
        ]);
        $asset = Asset::factory()->for($unit)->create();
        ReliabilityExcelSnapshot::query()->create([
            'asset_id' => $asset->id,
            'workbook_hash' => str_repeat('b', 64),
            'workbook_name' => 'RAMS.xlsm',
            'sheet_name' => 'Interlocking Elektrik',
            'source_row' => 4,
            'baseline_date' => '2020-01-01',
            'calculation_date' => '2021-01-01',
            'summary_values' => [],
            'formula_profile' => ['downtime_mode' => 'minutes', 'interval_baseline_date' => '2020-01-01'],
            'imported_at' => now(),
        ]);
        $payload = [
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type->value,
            'is_active' => true,
            'operating_start_date' => '2019-01-01',
        ];

        $this->actingAs($pusat)->put("/admin/units/{$unit->id}", $payload)
            ->assertSessionHasErrors(['baseline_change_reason', 'baseline_change_confirmed']);

        $this->actingAs($pusat)->put("/admin/units/{$unit->id}", [
            ...$payload,
            'baseline_change_reason' => 'Koreksi sesuai hasil validasi KAI',
            'baseline_change_confirmed' => true,
        ])->assertRedirect('/admin/units');

        $this->assertSame('2019-01-01', $unit->fresh()->operating_start_date?->toDateString());
        $audit = AuditLog::query()->where('action', 'unit.baseline_updated')->sole();
        $this->assertSame('Koreksi sesuai hasil validasi KAI', $audit->new_values['reason']);
        $this->assertSame('2019-01-01', ReliabilitySummary::query()->sole()->baseline_date?->toDateString());
    }

    public function test_edit_exposes_latest_imported_baseline_without_showing_it_on_dashboard(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($unit)->create();
        ReliabilityExcelSnapshot::query()->create([
            'asset_id' => $asset->id,
            'workbook_hash' => str_repeat('c', 64),
            'workbook_name' => 'RAMS.xlsm',
            'sheet_name' => 'Interlocking Elektrik',
            'source_row' => 4,
            'baseline_date' => '2020-01-01',
            'calculation_date' => '2021-01-01',
            'summary_values' => [],
            'imported_at' => now(),
        ]);

        $this->actingAs($pusat)->get("/admin/units/{$unit->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Units/Edit')
                ->where('importedBaselineDate', '2020-01-01'));
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
                ->where('units.data.0.code', 'DAOP-SEARCH')
                ->where('units.links.0.label', 'Sebelumnya')
                ->where('units.links.2.label', 'Berikutnya'));
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
