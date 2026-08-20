<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\RamsOperationalDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RamsDashboardBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_marks_only_meaningfully_changed_families_from_latest_successful_regional_import(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $daop = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => true]);

        $pdseAsset = $this->assetInGroup($daop, '1. PERALATAN DALAM SINYAL ELEKTRIK');
        $plseAsset = $this->assetInGroup($daop, '2. PERALATAN LUAR SINYAL ELEKTRIK');
        $otherAsset = $this->assetInGroup($otherUnit, '3. PERALATAN DALAM SINYAL MEKANIK');

        $batch = $this->successfulBatch($daop, '2026-08-20 10:00:00');
        $batch->changes()->create([
            'table_name' => 'failure_logs',
            'row_id' => 91,
            'operation' => 'updated',
            'before_values' => ['id' => 91, 'asset_id' => $pdseAsset->id, 'cause' => 'Lama'],
            'after_values' => ['id' => 91, 'asset_id' => $pdseAsset->id, 'cause' => 'Baru'],
        ]);
        $batch->changes()->create([
            'table_name' => 'reliability_summaries',
            'row_id' => 92,
            'operation' => 'updated',
            'before_values' => ['id' => 92, 'asset_id' => $plseAsset->id, 'updated_at' => '2026-08-20 09:00:00'],
            'after_values' => ['id' => 92, 'asset_id' => $plseAsset->id, 'updated_at' => '2026-08-20 10:00:00'],
        ]);

        $dryRun = $this->successfulBatch($daop, '2026-08-20 11:00:00', true);
        $dryRun->changes()->create([
            'table_name' => 'failure_logs',
            'row_id' => 93,
            'operation' => 'created',
            'before_values' => null,
            'after_values' => ['id' => 93, 'asset_id' => $plseAsset->id, 'cause' => 'Dry run'],
        ]);

        $otherBatch = $this->successfulBatch($otherUnit, '2026-08-20 12:00:00');
        $otherBatch->changes()->create([
            'table_name' => 'failure_logs',
            'row_id' => 94,
            'operation' => 'created',
            'before_values' => null,
            'after_values' => ['id' => 94, 'asset_id' => $otherAsset->id, 'cause' => 'Wilayah lain'],
        ]);

        $this->actingAs($pusat)
            ->get('/dashboard?area=DAOP1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.latestImport.date', '2026-08-20')
                ->where('summary.latestImport.groupCodes', ['PDSE']));
    }

    public function test_dashboard_exposes_empty_asset_categories_without_assets_in_selected_area(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $daop = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        AssetGroup::query()->create([
            'unit_kerja_id' => $daop->id,
            'name' => '1234',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($pusat)
            ->get('/dashboard?area=DAOP1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->has('asset_categories', 1)
                ->where('asset_categories.0.name', '1234')
                ->where('asset_categories.0.systems', [])
                ->has('assets', 0));
    }

    public function test_dashboard_exposes_a_dynamic_family_card_for_each_regional_root_category(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $daopFour = UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => true]);

        AssetGroup::factory()->create([
            'unit_kerja_id' => $daopOne->id,
            'name' => '6. DAYA SATU',
            'sort_order' => 6,
            'dashboard_color' => '#123ABC',
        ]);
        AssetGroup::factory()->create([
            'unit_kerja_id' => $daopOne->id,
            'name' => 'DAYA SATU',
            'sort_order' => 7,
        ]);
        AssetGroup::factory()->create([
            'unit_kerja_id' => $daopFour->id,
            'name' => 'WILAYAH LAIN',
            'sort_order' => 1,
        ]);

        $this->actingAs($pusat)
            ->get('/dashboard?area=DAOP1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('summary.reliabilityGroups', 1)
                ->where('summary.reliabilityGroups.0.code', 'DS')
                ->where('summary.reliabilityGroups.0.name', 'DAYA SATU')
                ->where('summary.reliabilityGroups.0.color', '#123ABC')
                ->where('summary.reliabilityGroups.0.asset_count', 0)
                ->where('summary.reliabilityGroups.0.reliability', null)
                ->where('summary.reliabilityGroups.0.availability', null));
    }

    public function test_dashboard_category_tree_is_scoped_to_selected_area(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $daop = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $divre = UnitKerja::factory()->create(['code' => 'DIVRE-IV', 'is_active' => true]);

        $daopGroup = AssetGroup::factory()->create(['name' => 'SINTEL DAOP', 'dashboard_color' => '#FF0000']);
        $daopSystem = AssetSystem::factory()->create([
            'asset_group_id' => $daopGroup->id,
            'name' => 'SYSTEM DAOP',
            'dashboard_color' => '#FFC000',
        ]);
        $daopSubsystem = AssetSubsystem::factory()->create([
            'asset_system_id' => $daopSystem->id,
            'name' => 'SUBSYSTEM DAOP',
            'dashboard_color' => '#FFFF00',
        ]);
        Asset::factory()->create([
            'unit_kerja_id' => $daop->id,
            'asset_subsystem_id' => $daopSubsystem->id,
        ]);

        $divreGroup = AssetGroup::factory()->create(['name' => 'SINTEL DIVRE']);
        $divreSystem = AssetSystem::factory()->create([
            'asset_group_id' => $divreGroup->id,
            'name' => 'SYSTEM DIVRE',
        ]);
        $divreSubsystem = AssetSubsystem::factory()->create([
            'asset_system_id' => $divreSystem->id,
            'name' => 'SUBSYSTEM DIVRE',
        ]);
        Asset::factory()->create([
            'unit_kerja_id' => $divre->id,
            'asset_subsystem_id' => $divreSubsystem->id,
        ]);

        $this->actingAs($pusat)
            ->get('/dashboard?area=DAOP1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_area', 'DAOP-1')
                ->where('asset_categories.0.name', 'SINTEL DAOP')
                ->where('asset_categories.0.dashboard_color', '#FF0000')
                ->where('asset_categories.0.systems.0.name', 'SYSTEM DAOP')
                ->where('asset_categories.0.systems.0.dashboard_color', '#FFC000')
                ->where('asset_categories.0.systems.0.subsystems.0.name', 'SUBSYSTEM DAOP')
                ->where('asset_categories.0.systems.0.subsystems.0.dashboard_color', '#FFFF00')
                ->where('asset_categories', fn ($categories) => $categories->pluck('name')->doesntContain('SINTEL DIVRE')));

        $this->actingAs($pusat)
            ->get('/dashboard?area=DIVRE4')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected_area', 'DIVRE-IV')
                ->where('asset_categories.0.name', 'SINTEL DIVRE')
                ->where('asset_categories', fn ($categories) => $categories->pluck('name')->doesntContain('SINTEL DAOP')));
    }

    public function test_pusat_dashboard_defaults_to_first_active_unit_instead_of_national_scope(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        UnitKerja::factory()->create(['code' => 'DIVRE-IV', 'is_active' => true]);

        $this->actingAs($pusat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->where('selected_area', 'DAOP-1'));
    }

    public function test_dashboard_pages_read_seeded_database_with_area_authorization(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $this->seed(RamsOperationalDataSeeder::class);

        $this->actingAs($pusat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->where('selected_area', 'DAOP-1')
                ->where('summary.totalAset', 17)
                ->has('assets', 17));

        $this->actingAs($pusat)
            ->get('/risk-matrix?area=DIVRE4')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/RiskMatrix')
                ->where('selected_area', 'DIVRE-IV')
                ->has('risks', 5));

        $daopId = UnitKerja::query()->where('code', 'DAOP-1')->value('id');
        $this->actingAs($pusat)
            ->get('/inventory?unit_kerja_id='.$daopId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/inventory/Inventory')
                ->where('filters.unit_kerja_id', (string) $daopId)
                ->where('stocks.total', 41)
                ->has('stocks.data', 20));

        $this->actingAs($pusat)->get('/dashboard?area=UNKNOWN')->assertNotFound();
    }

    public function test_regional_user_is_limited_to_its_own_unit(): void
    {
        $pusat = User::factory()->pusat()->create(['is_active' => true]);
        $this->seed(RamsOperationalDataSeeder::class);
        $unit = UnitKerja::query()->where('code', 'DAOP-1')->sole();
        $regional = User::factory()->unit($unit)->create(['is_active' => true]);

        $this->actingAs($regional)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Dashboard')
                ->where('summary.totalAset', 17)
                ->has('assets', 17)
                ->where('selected_area', 'DAOP-1'));

        $this->actingAs($regional)
            ->get('/dashboard?area=DIVRE4')
            ->assertSessionHasErrors('area');

        $this->assertTrue($pusat->exists);
    }

    private function assetInGroup(UnitKerja $unit, string $groupName): Asset
    {
        $group = AssetGroup::factory()->create(['name' => $groupName]);
        $system = AssetSystem::factory()->create(['asset_group_id' => $group->id]);
        $subsystem = AssetSubsystem::factory()->create(['asset_system_id' => $system->id]);

        return Asset::factory()->create([
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $subsystem->id,
        ]);
    }

    private function successfulBatch(UnitKerja $unit, string $finishedAt, bool $dryRun = false): RamsImportBatch
    {
        return RamsImportBatch::query()->create([
            'unit_kerja_id' => $unit->id,
            'fingerprint' => hash('sha256', $unit->id.'|'.$finishedAt.'|'.($dryRun ? 'dry' : 'live')),
            'import_version' => 'test-v1',
            'workbook_name' => 'RAMS '.$unit->code.'.xlsm',
            'file_size' => 1024,
            'status' => 'succeeded',
            'progress_stage' => 'Import selesai',
            'progress_percent' => 100,
            'dry_run' => $dryRun,
            'started_at' => $finishedAt,
            'finished_at' => $finishedAt,
        ]);
    }
}
