<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\AuditLog;
use App\Models\UnitKerja;
use App\Models\UnitSubsystemOpening;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_paginated_filtered_and_scoped_to_the_user(): void
    {
        $ownUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $user = User::factory()->unit($ownUnit)->create();
        Asset::factory()
            ->for($ownUnit)
            ->create([
                'nama_aset' => 'Track Circuit Gambir',
                'jumlah_unit' => 12,
                'tanggal_pemasangan' => '2012-01-01',
                'status' => AssetStatus::Aktif,
            ]);
        Asset::factory()
            ->for($ownUnit)
            ->create(['nama_aset' => 'Axle Counter']);
        Asset::factory()
            ->for($otherUnit)
            ->create(['nama_aset' => 'Track Circuit Cirebon']);

        $this->actingAs($user)
            ->get('/master-asset?search=Gambir&status=aktif')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('master-data/assets/MasterAsset')
                    ->has('assets.data', 1)
                    ->where('assets.data.0.nama_aset', 'Track Circuit Gambir')
                    ->where('assets.data.0.tanggal_pemasangan', '2012-01-01')
                    ->where('stats.total_assets', 1)
                    ->where('stats.total_units', 12)
                    ->where('filters.search', 'Gambir')
                    ->where('filters.status', 'aktif')
                    ->where('filters.unit_kerja_id', (string) $ownUnit->id)
                    ->where('can.choose_unit', false),
            );
    }

    public function test_pusat_without_or_with_invalid_unit_defaults_to_first_active_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $first = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $second = UnitKerja::factory()->create(['code' => 'DAOP-2', 'is_active' => true]);
        Asset::factory()
            ->for($first)
            ->create(['jumlah_unit' => 2]);
        Asset::factory()
            ->for($second)
            ->create(['jumlah_unit' => 9]);

        foreach (['/master-asset', '/master-asset?unit_kerja_id=999999'] as $url) {
            $this->actingAs($pusat)
                ->get($url)
                ->assertInertia(
                    fn (Assert $page) => $page
                        ->where('filters.unit_kerja_id', (string) $first->id)
                        ->where('stats.total_assets', 1)
                        ->where('stats.total_units', 2)
                        ->has('assets.data', 1),
                );
        }
    }

    public function test_pusat_without_active_unit_receives_empty_operational_data(): void
    {
        $pusat = User::factory()->pusat()->create();

        $this->actingAs($pusat)
            ->get('/master-asset')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('filters.unit_kerja_id', '')
                    ->where('stats.total_assets', 0)
                    ->where('stats.total_units', 0)
                    ->has('assets.data', 0)
                    ->has('hierarchy', 0),
            );
    }

    public function test_pusat_can_filter_assets_by_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        Asset::factory()->for($unit)->create();
        Asset::factory()->create();

        $this->actingAs($pusat)
            ->get("/master-asset?unit_kerja_id={$unit->id}")
            ->assertInertia(
                fn (Assert $page) => $page->has('assets.data', 1)->where('can.choose_unit', true)->has('units'),
            );
    }

    public function test_index_exposes_unit_scoped_hierarchy_totals_and_openings(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($ownUnit)->create();
        [$group, $system, $subsystem] = $this->categoryPath();
        Asset::factory()
            ->for($ownUnit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['jumlah_unit' => 81]);
        Asset::factory()
            ->for($otherUnit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['jumlah_unit' => 19]);
        UnitSubsystemOpening::factory()
            ->for($ownUnit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'sparepart_in' => 7,
                'sparepart_out' => 2,
            ]);
        UnitSubsystemOpening::factory()
            ->for($otherUnit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'sparepart_in' => 5,
                'sparepart_out' => 4,
            ]);

        $this->actingAs($user)
            ->get('/master-asset')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('hierarchy', 1)
                    ->where('hierarchy.0.id', $subsystem->id)
                    ->where('hierarchy.0.name', $subsystem->name)
                    ->where('hierarchy.0.asset_system.id', $system->id)
                    ->where('hierarchy.0.asset_system.asset_group.id', $group->id)
                    ->where('hierarchy.0.total', 81)
                    ->where('hierarchy.0.sparepart_in', 7)
                    ->where('hierarchy.0.sparepart_out', 2),
            );
    }

    public function test_index_exposes_empty_asset_categories_without_master_assets(): void
    {
        $pusat = User::factory()->pusat()->create();
        AssetGroup::factory()->create(['name' => '1234']);

        $this->actingAs($pusat)
            ->get('/master-asset')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('assets.data', 0)
                    ->has('hierarchy', 0)
                    ->has('assetCategories', 1)
                    ->where('assetCategories.0.name', '1234')
                    ->where('assetCategories.0.systems', []),
            );
    }

    public function test_index_hierarchy_uses_the_full_filtered_result_before_pagination(): void
    {
        $selectedUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $pusat = User::factory()->pusat()->create();
        [, , $firstSubsystem] = $this->categoryPath(
            group: ['name' => 'Group A', 'sort_order' => 1],
            system: ['name' => 'System A', 'sort_order' => 1],
            subsystem: ['name' => 'Subsystem A', 'sort_order' => 1],
        );
        [, , $secondSubsystem] = $this->categoryPath(
            group: ['name' => 'Group B', 'sort_order' => 2],
            system: ['name' => 'System B', 'sort_order' => 2],
            subsystem: ['name' => 'Subsystem B', 'sort_order' => 2],
        );
        [, , $filteredOutSubsystem] = $this->categoryPath(group: ['name' => 'Group C', 'sort_order' => 3]);

        foreach (range(1, 15) as $index) {
            Asset::factory()
                ->for($selectedUnit)
                ->for($firstSubsystem, 'assetSubsystem')
                ->create([
                    'nama_aset' => sprintf('Aset A %02d', $index),
                    'system' => 'A',
                    'subsystem' => 'A',
                    'jumlah_unit' => 1,
                    'status' => AssetStatus::Aktif,
                ]);
        }
        Asset::factory()
            ->for($selectedUnit)
            ->for($secondSubsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Aset Z',
                'system' => 'Z',
                'subsystem' => 'Z',
                'jumlah_unit' => 50,
                'status' => AssetStatus::Aktif,
            ]);
        Asset::factory()
            ->for($selectedUnit)
            ->for($filteredOutSubsystem, 'assetSubsystem')
            ->create([
                'status' => AssetStatus::Nonaktif,
                'jumlah_unit' => 70,
            ]);
        Asset::factory()
            ->for($otherUnit)
            ->for($firstSubsystem, 'assetSubsystem')
            ->create([
                'status' => AssetStatus::Aktif,
                'jumlah_unit' => 100,
            ]);
        $this->actingAs($pusat)
            ->get("/master-asset?unit_kerja_id={$selectedUnit->id}&status=aktif")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('assets.data', 15)
                    ->where(
                        'assets.data',
                        fn ($assets): bool => collect($assets)->every(
                            fn (array $asset): bool => $asset['asset_subsystem_id'] === $firstSubsystem->id,
                        ),
                    )
                    ->where('hierarchy', function ($rows) use (
                        $firstSubsystem,
                        $secondSubsystem,
                        $filteredOutSubsystem,
                    ): bool {
                        $byId = collect($rows)->keyBy('id');

                        return $byId->count() === 2 &&
                            $byId->get($firstSubsystem->id)['total'] === 15 &&
                            $byId->get($secondSubsystem->id)['total'] === 50 &&
                            ! $byId->has($filteredOutSubsystem->id);
                    })
                    ->where('legacySummary', null),
            );
    }

    public function test_index_hierarchy_total_matches_search_and_status_within_the_same_subsystem(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [, , $subsystem] = $this->categoryPath(
            group: ['name' => 'Peralatan Sinyal'],
            system: ['name' => 'Interlocking Elektrik'],
            subsystem: ['name' => 'Track Circuit'],
        );
        Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Needle Aktif',
                'jumlah_unit' => 5,
                'status' => AssetStatus::Aktif,
            ]);
        Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Tidak Cocok',
                'jumlah_unit' => 7,
                'status' => AssetStatus::Aktif,
            ]);
        Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Needle Nonaktif',
                'jumlah_unit' => 11,
                'status' => AssetStatus::Nonaktif,
            ]);
        UnitSubsystemOpening::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'sparepart_in' => 3,
                'sparepart_out' => 1,
            ]);

        $this->actingAs($user)
            ->get('/master-asset?search=Needle&status=aktif')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('assets.data', 1)
                    ->where('stats.total_units', 5)
                    ->has('hierarchy', 1)
                    ->where('hierarchy.0.id', $subsystem->id)
                    ->where('hierarchy.0.total', 5)
                    ->where('hierarchy.0.sparepart_in', 3)
                    ->where('hierarchy.0.sparepart_out', 1),
            );
    }

    public function test_unit_creates_an_asset_only_for_its_own_unit(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [, , $subsystem] = $this->categoryPath();

        $this->actingAs($user)
            ->post('/master-asset', [
                'unit_kerja_id' => $otherUnit->id,
                ...$this->categoryAssetPayload($subsystem),
            ])
            ->assertSessionHasErrors('unit_kerja_id');

        $this->actingAs($user)
            ->post('/master-asset', $this->categoryAssetPayload($subsystem))
            ->assertRedirect('/master-asset');

        $this->assertDatabaseHas('assets', [
            'unit_kerja_id' => $unit->id,
            'nama_aset' => 'Track Circuit Gambir',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.created']);
    }

    public function test_regional_user_creates_an_asset_from_a_subsystem_id_and_snapshots_the_category_path(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $group = AssetGroup::factory()->create(['name' => 'Peralatan Dalam Sinyal']);
        $system = AssetSystem::factory()
            ->for($group)
            ->create(['name' => 'Interlocking']);
        $subsystem = AssetSubsystem::factory()
            ->for($system)
            ->create(['name' => 'Electronic Interlocking']);

        $response = $this->actingAs($user)->post('/master-asset', $this->categoryAssetPayload($subsystem));

        $response->assertRedirect('/master-asset');

        $this->assertDatabaseHas('assets', [
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $subsystem->id,
            'nama_aset' => 'Track Circuit Gambir',
            'aset_prasarana_sintel' => 'Peralatan Dalam Sinyal',
            'system' => 'Interlocking',
            'subsystem' => 'Electronic Interlocking',
        ]);
    }

    public function test_pusat_creates_an_asset_for_an_active_unit_from_a_subsystem_id(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create(['is_active' => true]);
        [$group, $system, $subsystem] = $this->categoryPath();

        $this->actingAs($pusat)
            ->post('/master-asset', [
                'unit_kerja_id' => $unit->id,
                ...$this->categoryAssetPayload($subsystem),
            ])
            ->assertRedirect('/master-asset');

        $this->assertDatabaseHas('assets', [
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $subsystem->id,
            'nama_aset' => 'Track Circuit Gambir',
            'aset_prasarana_sintel' => $group->name,
            'system' => $system->name,
            'subsystem' => $subsystem->name,
        ]);
    }

    public function test_client_cannot_spoof_legacy_category_snapshots(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [, , $subsystem] = $this->categoryPath();

        $this->actingAs($user)
            ->from('/master-asset/create')
            ->post('/master-asset', [
                ...$this->categoryAssetPayload($subsystem),
                'aset_prasarana_sintel' => 'Spoofed Group',
                'system' => 'Spoofed System',
                'subsystem' => 'Spoofed Subsystem',
            ])
            ->assertRedirect('/master-asset/create')
            ->assertSessionHasErrors(['aset_prasarana_sintel', 'system', 'subsystem']);

        $this->assertDatabaseMissing('assets', ['nama_aset' => 'Track Circuit Gambir']);
    }

    public function test_current_inactive_path_can_be_kept_but_other_inactive_paths_are_rejected(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [$group, $system, $subsystem] = $this->categoryPath();
        $asset = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'aset_prasarana_sintel' => $group->name,
                'system' => $system->name,
                'subsystem' => $subsystem->name,
            ]);
        $group->update(['is_active' => false]);

        $this->actingAs($user)
            ->from('/master-asset/create')
            ->post('/master-asset', $this->categoryAssetPayload($subsystem))
            ->assertSessionHasErrors('asset_subsystem_id');

        $this->actingAs($user)
            ->put(
                "/master-asset/{$asset->id}",
                $this->categoryAssetPayload($subsystem, [
                    'nama_aset' => 'Aset Tetap Bisa Diedit',
                ]),
            )
            ->assertRedirect('/master-asset');
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'asset_subsystem_id' => $subsystem->id,
            'nama_aset' => 'Aset Tetap Bisa Diedit',
        ]);

        [, , $inactiveTarget] = $this->categoryPath(group: ['is_active' => false]);
        $this->actingAs($user)
            ->from("/master-asset/{$asset->id}/edit")
            ->put("/master-asset/{$asset->id}", $this->categoryAssetPayload($inactiveTarget))
            ->assertSessionHasErrors('asset_subsystem_id');

        [, , $deletedTarget] = $this->categoryPath();
        $deletedTarget->delete();
        $this->actingAs($user)
            ->from("/master-asset/{$asset->id}/edit")
            ->put("/master-asset/{$asset->id}", $this->categoryAssetPayload($deletedTarget))
            ->assertSessionHasErrors('asset_subsystem_id');

        $this->actingAs($user)
            ->get("/master-asset/{$asset->id}/edit")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('asset.category.group.id', $group->id)
                    ->where('asset.category.group.name', $group->name)
                    ->where('asset.category.group.is_active', false)
                    ->where('asset.category.system.id', $system->id)
                    ->where('asset.category.subsystem.id', $subsystem->id),
            );
    }

    public function test_category_renames_drive_payload_and_search_without_overwriting_snapshots(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [$group, $system, $subsystem] = $this->categoryPath();
        $asset = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Relation-backed Asset',
                'aset_prasarana_sintel' => 'Old Group',
                'system' => 'Old System',
                'subsystem' => 'Old Subsystem',
            ]);
        $group->update(['name' => 'Renamed Group']);
        $system->update(['name' => 'Renamed System']);
        $subsystem->update(['name' => 'Renamed Subsystem']);

        foreach (['Renamed Group', 'Renamed System', 'Renamed Subsystem'] as $term) {
            $this->actingAs($user)
                ->get('/master-asset?search='.urlencode($term))
                ->assertInertia(
                    fn (Assert $page) => $page
                        ->has('assets.data', 1)
                        ->where('assets.data.0.id', $asset->id)
                        ->where('assets.data.0.category.group.name', 'Renamed Group')
                        ->where('assets.data.0.category.system.name', 'Renamed System')
                        ->where('assets.data.0.category.subsystem.name', 'Renamed Subsystem'),
                );
        }

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'aset_prasarana_sintel' => 'Old Group',
            'system' => 'Old System',
            'subsystem' => 'Old Subsystem',
        ]);
    }

    public function test_form_categories_include_only_active_ordered_hierarchies(): void
    {
        $pusat = User::factory()->pusat()->create();
        $beta = AssetGroup::factory()->create(['name' => 'Beta Group', 'sort_order' => 2]);
        $alpha = AssetGroup::factory()->create(['name' => 'Alpha Group', 'sort_order' => 1]);
        $inactive = AssetGroup::factory()->create(['name' => 'Inactive Group', 'is_active' => false]);
        $deleted = AssetGroup::factory()->create(['name' => 'Deleted Group']);
        $deleted->delete();
        $betaSystem = AssetSystem::factory()
            ->for($beta)
            ->create(['name' => 'Beta System', 'sort_order' => 1]);
        AssetSystem::factory()
            ->for($beta)
            ->create(['name' => 'Inactive System', 'is_active' => false]);
        $alphaSystem = AssetSystem::factory()
            ->for($alpha)
            ->create(['name' => 'Alpha System', 'sort_order' => 1]);
        $alphaSubsystem = AssetSubsystem::factory()
            ->for($alphaSystem)
            ->create(['name' => 'Alpha Subsystem', 'sort_order' => 2]);
        $betaSubsystem = AssetSubsystem::factory()
            ->for($betaSystem)
            ->create(['name' => 'Beta Subsystem', 'sort_order' => 1]);
        AssetSubsystem::factory()
            ->for($alphaSystem)
            ->create(['name' => 'Inactive Subsystem', 'is_active' => false]);

        $this->actingAs($pusat)->get('/master-asset/create')->assertInertia(
            fn (Assert $page) => $page->where('categories', [
                [
                    'id' => $alpha->id,
                    'name' => 'Alpha Group',
                    'systems' => [
                        [
                            'id' => $alphaSystem->id,
                            'name' => 'Alpha System',
                            'subsystems' => [
                                [
                                    'id' => $alphaSubsystem->id,
                                    'name' => 'Alpha Subsystem',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => $beta->id,
                    'name' => 'Beta Group',
                    'systems' => [
                        [
                            'id' => $betaSystem->id,
                            'name' => 'Beta System',
                            'subsystems' => [
                                [
                                    'id' => $betaSubsystem->id,
                                    'name' => 'Beta Subsystem',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        );

        $this->assertFalse($inactive->is_active);
    }

    public function test_edit_categories_include_the_current_inactive_path_only(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [$group, $system, $subsystem] = $this->categoryPath(
            group: ['name' => 'Current Group', 'is_active' => false],
            system: ['name' => 'Current System'],
            subsystem: ['name' => 'Current Subsystem'],
        );
        [, , $unrelatedInactive] = $this->categoryPath(group: ['name' => 'Other Inactive Group', 'is_active' => false]);
        $asset = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create();

        $this->actingAs($user)
            ->get("/master-asset/{$asset->id}/edit")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories', 1)
                    ->where('categories.0.id', $group->id)
                    ->where('categories.0.is_active', false)
                    ->where('categories.0.systems.0.id', $system->id)
                    ->where('categories.0.systems.0.subsystems.0.id', $subsystem->id)
                    ->where('categories.0.systems.0.subsystems.0.is_active', true)
                    ->missing('categories.1')
                    ->where('asset.asset_subsystem_id', $subsystem->id),
            );

        $this->assertFalse($unrelatedInactive->assetSystem->assetGroup->is_active);
    }

    public function test_edit_categories_merge_inactive_nodes_at_each_levels_deterministic_sort_position(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $activeGroup = AssetGroup::factory()->create(['name' => 'Active Group', 'sort_order' => 20]);
        $activeSystem = AssetSystem::factory()
            ->for($activeGroup)
            ->create(['name' => 'Active System', 'sort_order' => 20]);
        $activeSubsystem = AssetSubsystem::factory()
            ->for($activeSystem)
            ->create(['name' => 'Active Subsystem', 'sort_order' => 20]);
        $inactiveGroup = AssetGroup::factory()->create([
            'name' => 'Inactive Group',
            'sort_order' => 10,
            'is_active' => false,
        ]);
        $inactiveGroupSystem = AssetSystem::factory()
            ->for($inactiveGroup)
            ->create(['sort_order' => 10]);
        $inactiveGroupSubsystem = AssetSubsystem::factory()
            ->for($inactiveGroupSystem)
            ->create(['sort_order' => 10]);
        $inactiveSystem = AssetSystem::factory()
            ->for($activeGroup)
            ->create([
                'name' => 'Inactive System',
                'sort_order' => 10,
                'is_active' => false,
            ]);
        $inactiveSystemSubsystem = AssetSubsystem::factory()
            ->for($inactiveSystem)
            ->create(['sort_order' => 10]);
        $inactiveSubsystem = AssetSubsystem::factory()
            ->for($activeSystem)
            ->create([
                'name' => 'Inactive Subsystem',
                'sort_order' => 10,
                'is_active' => false,
            ]);
        $groupAsset = Asset::factory()->for($unit)->for($inactiveGroupSubsystem, 'assetSubsystem')->create();
        $systemAsset = Asset::factory()->for($unit)->for($inactiveSystemSubsystem, 'assetSubsystem')->create();
        $subsystemAsset = Asset::factory()->for($unit)->for($inactiveSubsystem, 'assetSubsystem')->create();

        $this->actingAs($user)
            ->get("/master-asset/{$groupAsset->id}/edit")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'categories',
                        fn ($categories): bool => collect($categories)->pluck('id')->all() === [
                            $inactiveGroup->id,
                            $activeGroup->id,
                        ],
                    )
                    ->missing('categories.0._sort_order'),
            );

        $this->actingAs($user)
            ->get("/master-asset/{$systemAsset->id}/edit")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories', 1)
                    ->where('categories.0.id', $activeGroup->id)
                    ->where(
                        'categories.0.systems',
                        fn ($systems): bool => collect($systems)->pluck('id')->all() === [
                            $inactiveSystem->id,
                            $activeSystem->id,
                        ],
                    )
                    ->missing('categories.0.systems.0._sort_order'),
            );

        $this->actingAs($user)
            ->get("/master-asset/{$subsystemAsset->id}/edit")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('categories', 1)
                    ->where('categories.0.systems.0.id', $activeSystem->id)
                    ->where(
                        'categories.0.systems.0.subsystems',
                        fn ($subsystems): bool => collect($subsystems)->pluck('id')->all() === [
                            $inactiveSubsystem->id,
                            $activeSubsystem->id,
                        ],
                    )
                    ->missing('categories.0.systems.0.subsystems.0._sort_order'),
            );
    }

    public function test_unique_subsystems_stat_uses_linked_categories_with_filters_and_unit_scope(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [, , $firstSubsystem] = $this->categoryPath();
        [, , $secondSubsystem] = $this->categoryPath();
        Asset::factory()
            ->for($unit)
            ->for($firstSubsystem, 'assetSubsystem')
            ->count(2)
            ->create([
                'nama_aset' => fn () => 'Signal linked '.fake()->unique()->numberBetween(1, 1000),
            ]);
        Asset::factory()
            ->for($unit)
            ->for($secondSubsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Signal linked second category',
                'status' => AssetStatus::Aktif,
            ]);
        Asset::factory()
            ->for($unit)
            ->for($secondSubsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Signal linked inactive',
                'status' => AssetStatus::DalamPerbaikan,
            ]);
        Asset::factory()
            ->for($unit)
            ->for($firstSubsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Unmatched linked asset',
                'status' => AssetStatus::Aktif,
            ]);
        Asset::factory()
            ->for($otherUnit)
            ->for($firstSubsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => 'Signal from another unit',
                'status' => AssetStatus::Aktif,
            ]);

        $this->actingAs($user)
            ->get('/master-asset?search=Signal&status=aktif')
            ->assertInertia(
                fn (Assert $page) => $page->where('stats.total_assets', 3)->where('stats.unique_subsystems', 2),
            );
    }

    public function test_store_and_update_lock_and_revalidate_category_paths_in_parent_to_child_order(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [, , $firstSubsystem] = $this->categoryPath();
        [, , $secondSubsystem] = $this->categoryPath();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(mb_strtolower($query->sql), 'for update')) {
                $queries[] = mb_strtolower($query->sql);
            }
        });

        $this->actingAs($user)
            ->post('/master-asset', $this->categoryAssetPayload($firstSubsystem))
            ->assertRedirect('/master-asset');
        $asset = Asset::query()->where('asset_subsystem_id', $firstSubsystem->id)->firstOrFail();
        $this->actingAs($user)
            ->put("/master-asset/{$asset->id}", $this->categoryAssetPayload($secondSubsystem))
            ->assertRedirect('/master-asset');

        $categoryLocks = array_values(
            array_filter(
                $queries,
                fn (string $sql): bool => str_contains($sql, 'asset_group') ||
                    str_contains($sql, 'asset_system') ||
                    str_contains($sql, 'asset_subsystem'),
            ),
        );
        $this->assertCount(6, $categoryLocks);
        foreach (array_chunk($categoryLocks, 3) as $pathLocks) {
            $this->assertStringContainsString('from `asset_groups`', $pathLocks[0]);
            $this->assertStringContainsString('from `asset_systems`', $pathLocks[1]);
            $this->assertStringContainsString('from `asset_subsystems`', $pathLocks[2]);
        }

        $audit = AuditLog::query()->where('action', 'asset.updated')->latest('id')->firstOrFail();
        $this->assertSame($firstSubsystem->id, $audit->old_values['asset_subsystem_id']);
        $this->assertSame($secondSubsystem->id, $audit->new_values['asset_subsystem_id']);
    }

    public function test_store_revalidates_parent_activity_after_request_validation(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        [$group, , $subsystem] = $this->categoryPath();
        $parentFlipped = false;

        DB::listen(function (QueryExecuted $query) use (&$parentFlipped, $group): void {
            $sql = mb_strtolower($query->sql);
            if ($parentFlipped || ! str_contains($sql, 'asset_subsystems') || ! str_contains($sql, 'asset_groups')) {
                return;
            }

            $parentFlipped = true;
            DB::table('asset_groups')
                ->where('id', $group->id)
                ->update(['is_active' => false]);
        });

        $this->actingAs($user)
            ->from('/master-asset/create')
            ->post('/master-asset', $this->categoryAssetPayload($subsystem))
            ->assertRedirect('/master-asset/create')
            ->assertSessionHasErrors('asset_subsystem_id');

        $this->assertTrue($parentFlipped);
        $this->assertFalse($group->fresh()->is_active);
        $this->assertDatabaseMissing('assets', ['nama_aset' => 'Track Circuit Gambir']);
    }

    public function test_cross_unit_mutations_return_not_found(): void
    {
        $ownerUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($ownerUnit)->create();
        $outsider = User::factory()->unit($otherUnit)->create();

        $this->actingAs($outsider)
            ->get("/master-asset/{$asset->id}/edit")
            ->assertNotFound();
        $this->actingAs($outsider)
            ->put("/master-asset/{$asset->id}", [])
            ->assertNotFound();
        $this->actingAs($outsider)
            ->delete("/master-asset/{$asset->id}")
            ->assertNotFound();
    }

    public function test_pusat_updates_and_soft_deletes_an_asset_with_audit_logs(): void
    {
        $pusat = User::factory()->pusat()->create();
        $asset = Asset::factory()->create();
        $payload = [
            'unit_kerja_id' => $asset->unit_kerja_id,
            ...$this->categoryAssetPayload($asset->assetSubsystem, [
                'nama_aset' => 'Nama Aset Diperbarui',
                'jumlah_unit' => 20,
                'tanggal_pemasangan' => '2018-01-01',
                'status' => 'dalam_perbaikan',
            ]),
        ];

        $this->actingAs($pusat)
            ->put("/master-asset/{$asset->id}", $payload)
            ->assertRedirect('/master-asset');
        $this->actingAs($pusat)
            ->delete("/master-asset/{$asset->id}")
            ->assertRedirect('/master-asset');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.deleted']);
    }

    private function categoryAssetPayload(AssetSubsystem $subsystem, array $overrides = []): array
    {
        return [
            'asset_subsystem_id' => $subsystem->id,
            'nama_aset' => '  Track   Circuit Gambir  ',
            'jumlah_unit' => 12,
            'tanggal_pemasangan' => '2019-06-10',
            'status' => 'aktif',
            ...$overrides,
        ];
    }

    private function categoryPath(array $group = [], array $system = [], array $subsystem = []): array
    {
        $assetGroup = AssetGroup::factory()->create($group);
        $assetSystem = AssetSystem::factory()->for($assetGroup)->create($system);
        $assetSubsystem = AssetSubsystem::factory()->for($assetSystem)->create($subsystem);

        return [$assetGroup, $assetSystem, $assetSubsystem];
    }
}
