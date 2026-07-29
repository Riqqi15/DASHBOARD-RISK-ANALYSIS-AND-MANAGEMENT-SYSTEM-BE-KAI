<?php

namespace Tests\Feature;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\UnitSubsystemOpening;
use App\Models\User;
use App\Queries\AssetHierarchyQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-29 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_regional_index_is_authorized_scoped_and_keeps_inactive_historical_names(): void
    {
        $ownUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $user = User::factory()->unit($ownUnit)->create();
        $part = SparePart::factory()->create(['code' => 'SP-HISTORY', 'detail_equipment' => 'Relay Lama']);
        $otherPart = SparePart::factory()->create(['code' => 'SP-OTHER']);
        InventoryStock::factory()->for($ownUnit)->for($part)->create(['quantity' => 4]);
        InventoryStock::factory()->for($otherUnit)->for($otherPart)->create(['quantity' => 99]);
        StockMovement::factory()->for($ownUnit)->for($part)->for($user, 'actor')->create();
        StockMovement::factory()->for($otherUnit)->for($otherPart)->create();
        $part->update(['is_active' => false]);

        $this->actingAs($user)->get('/inventory?unit_kerja_id='.$otherUnit->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/inventory/Inventory')
                ->has('stocks.data', 1)
                ->where('stocks.data.0.spare_part.code', 'SP-HISTORY')
                ->where('stocks.data.0.spare_part.is_active', false)
                ->has('movements.data', 1)
                ->where('movements.data.0.spare_part.detail_equipment', 'Relay Lama')
                ->where('movements.data.0.posted_at', '2026-07-29T10:00:00+07:00')
                ->where('movements.data.0.current_stock', 4)
                ->where('filters.unit_kerja_id', '')
                ->where('can.choose_unit', false)
                ->where('can.manage_master', false)
                ->where('can.record_movement', true)
                ->has('spareParts', 1)
                ->where('spareParts.0.code', 'SP-OTHER')
                ->has('categories')
                ->has('units', 0));
    }

    public function test_stock_and_history_keep_soft_deleted_category_breadcrumbs_at_every_level(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $expected = [];

        foreach (['group', 'system', 'subsystem'] as $level) {
            [$group, $system, $subsystem] = $this->categoryPath(
                "Deleted group {$level}",
                "Deleted system {$level}",
                "Deleted subsystem {$level}",
            );
            $part = SparePart::factory()->for($subsystem)->create(['code' => "HISTORY-{$level}"]);
            InventoryStock::factory()->for($unit)->for($part)->create();
            StockMovement::factory()->for($unit)->for($part)->for($user, 'actor')->create();
            match ($level) {
                'group' => $group->delete(),
                'system' => $system->delete(),
                'subsystem' => $subsystem->delete(),
            };
            $expected[$part->code] = [
                'group' => $group->name,
                'system' => $system->name,
                'subsystem' => $subsystem->name,
            ];
        }

        $this->actingAs($user)->get('/inventory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.data', fn ($rows): bool => $this->hasHistoricalBreadcrumbs($rows, $expected))
                ->where('movements.data', fn ($rows): bool => $this->hasHistoricalBreadcrumbs($rows, $expected)));
    }

    public function test_pusat_selected_unit_scopes_stocks_history_stats_and_all_units_are_available_without_selection(): void
    {
        $pusat = User::factory()->pusat()->create();
        $firstUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $secondUnit = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $part = SparePart::factory()->create(['reorder_point' => 10, 'safety_stock' => 2]);
        InventoryStock::factory()->for($firstUnit)->for($part)->create(['quantity' => 8]);
        InventoryStock::factory()->for($secondUnit)->for($part)->create(['quantity' => 20]);
        StockMovement::factory()->for($firstUnit)->for($part)->for($pusat, 'actor')->create(['movement_date' => '2026-07-01']);
        StockMovement::factory()->for($secondUnit)->for($part)->for($pusat, 'actor')->create(['movement_date' => '2026-07-02']);

        $this->actingAs($pusat)->get('/inventory?unit_kerja_id='.$firstUnit->id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('stocks.data', 1)
                ->where('stocks.data.0.unit.id', $firstUnit->id)
                ->has('movements.data', 1)
                ->where('movements.data.0.unit.id', $firstUnit->id)
                ->where('stats.total_parts', 1)
                ->where('stats.total_quantity', 8)
                ->where('stats.below_reorder', 1)
                ->where('stats.movements_this_month', 1)
                ->where('filters.unit_kerja_id', (string) $firstUnit->id)
                ->where('can.choose_unit', true)
                ->where('can.manage_master', true)
                ->where('can.record_movement', true)
                ->has('units', 2)
                ->has('spareParts', 1));

        $this->actingAs($pusat)->get('/inventory')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stocks.data', 2)
                ->has('movements.data', 2)
                ->where('stats.total_parts', 1)
                ->where('stats.total_quantity', 28)
                ->where('stats.below_reorder', 1)
                ->where('stats.movements_this_month', 2));
    }

    public function test_history_marks_only_uncorrected_original_movements_as_correctable(): void
    {
        $user = User::factory()->pusat()->create();
        $activeUnit = UnitKerja::factory()->create();
        $activePart = SparePart::factory()->create();
        $corrected = StockMovement::factory()->for($activeUnit)->for($activePart)->for($user, 'actor')->create();
        $available = StockMovement::factory()->for($activeUnit)->for($activePart)->for($user, 'actor')->create();
        $correction = StockMovement::factory()->for($activeUnit)->for($activePart)->for($user, 'actor')->create([
            'type' => StockMovementType::Correction,
            'reverses_movement_id' => $corrected->id,
        ]);

        $inactivePart = SparePart::factory()->create();
        $inactivePartMovement = StockMovement::factory()->for($activeUnit)->for($inactivePart)->for($user, 'actor')->create();
        $inactivePart->update(['is_active' => false]);

        $deletedPart = SparePart::factory()->create();
        $deletedPartMovement = StockMovement::factory()->for($activeUnit)->for($deletedPart)->for($user, 'actor')->create();
        $deletedPart->delete();

        $inactiveUnit = UnitKerja::factory()->create();
        $inactiveUnitMovement = StockMovement::factory()->for($inactiveUnit)->for($activePart)->for($user, 'actor')->create();
        $inactiveUnit->update(['is_active' => false]);

        $deletedUnit = UnitKerja::factory()->create();
        $deletedUnitMovement = StockMovement::factory()->for($deletedUnit)->for($activePart)->for($user, 'actor')->create();
        $deletedUnit->delete();

        $this->actingAs($user)->get('/inventory?tab=history')
            ->assertInertia(fn (Assert $page) => $page
                ->where('movements.data', function ($rows) use ($corrected, $available, $correction, $inactivePartMovement, $deletedPartMovement, $inactiveUnitMovement, $deletedUnitMovement): bool {
                    $eligibility = collect($rows)->mapWithKeys(
                        fn (array $row): array => [$row['id'] => $row['is_correctable'] ?? null],
                    );

                    return $eligibility->get($corrected->id) === false
                        && $eligibility->get($available->id) === true
                        && $eligibility->get($correction->id) === false
                        && collect([
                            $inactivePartMovement,
                            $deletedPartMovement,
                            $inactiveUnitMovement,
                            $deletedUnitMovement,
                        ])->every(fn (StockMovement $movement): bool => $eligibility->get($movement->id) === false);
                }));
    }

    public function test_stock_status_boundaries_drive_rows_filters_and_below_reorder_stat(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $cases = [
            ['EMPTY', 0, 5, 10, 'empty'],
            ['CRITICAL-LOW', 1, 5, 10, 'critical'],
            ['CRITICAL-EDGE', 5, 5, 10, 'critical'],
            ['REORDER', 6, 5, 10, 'below_reorder'],
            ['REORDER-EDGE', 10, 5, 10, 'below_reorder'],
            ['AVAILABLE', 11, 5, 10, 'available'],
            ['NULL-SAFETY', 1, null, 10, 'below_reorder'],
            ['NULL-REORDER', 2, 1, null, 'available'],
        ];

        foreach ($cases as [$code, $quantity, $safety, $reorder, $status]) {
            $part = SparePart::factory()->create([
                'code' => $code,
                'safety_stock' => $safety,
                'reorder_point' => $reorder,
            ]);
            InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => $quantity]);
        }

        $this->actingAs($user)->get('/inventory')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.data', function ($rows) use ($cases): bool {
                    $statuses = collect($rows)->mapWithKeys(fn (array $row): array => [
                        $row['spare_part']['code'] => $row['status'],
                    ]);

                    return collect($cases)->every(
                        fn (array $case): bool => $statuses->get($case[0]) === $case[4],
                    );
                })
                ->where('stats.below_reorder', 6));

        foreach (['empty' => 1, 'critical' => 2, 'below_reorder' => 3, 'available' => 2] as $status => $count) {
            $this->actingAs($user)->get('/inventory?stock_status='.$status)
                ->assertInertia(fn (Assert $page) => $page
                    ->has('stocks.data', $count)
                    ->where('stocks.data', fn ($rows): bool => collect($rows)->every(
                        fn (array $row): bool => $row['status'] === $status,
                    )));
        }
    }

    public function test_search_and_category_filters_match_part_and_hierarchy_names(): void
    {
        $unit = UnitKerja::factory()->create(['name' => 'Daop Jakarta']);
        $user = User::factory()->unit($unit)->create();
        [$signalGroup, , $trackSubsystem] = $this->categoryPath('Persinyalan', 'Sinyal Elektrik', 'Track Circuit');
        [, , $telecomSubsystem] = $this->categoryPath('Telekomunikasi', 'Radio', 'Radio Lokomotif');
        $relay = SparePart::factory()->for($trackSubsystem)->create([
            'code' => 'SP-RELAY',
            'equipment' => 'Track Equipment',
            'detail_equipment' => 'Relay 24 Volt',
        ]);
        $radio = SparePart::factory()->for($telecomSubsystem)->create(['detail_equipment' => 'Antenna Radio']);
        InventoryStock::factory()->for($unit)->for($relay)->create();
        InventoryStock::factory()->for($unit)->for($radio)->create();

        foreach ([
            'search=SP-RELAY',
            'search=Relay+24',
            'search=Track+Circuit',
            'search=Persinyalan',
            'asset_group_id='.$signalGroup->id,
            'asset_subsystem_id='.$trackSubsystem->id,
        ] as $query) {
            $this->actingAs($user)->get('/inventory?'.$query)
                ->assertInertia(fn (Assert $page) => $page
                    ->has('stocks.data', 1)
                    ->where('stocks.data.0.spare_part.code', 'SP-RELAY'));
        }

        $this->actingAs($user)->get('/inventory?search=Daop+Jakarta')
            ->assertInertia(fn (Assert $page) => $page->has('stocks.data', 2));
    }

    public function test_invalid_filters_are_normalized_without_errors(): void
    {
        $pusat = User::factory()->pusat()->create();
        $inactiveUnit = UnitKerja::factory()->create(['is_active' => false]);

        $this->actingAs($pusat)->get('/inventory?'.http_build_query([
            'search' => ['not', 'scalar'],
            'asset_group_id' => ['bad'],
            'asset_subsystem_id' => [999999],
            'stock_status' => ['urgent'],
            'unit_kerja_id' => $inactiveUnit->id,
            'tab' => ['forecast'],
            'page' => [-5],
            'movement_page' => ['bad'],
            'master_page' => ['bad'],
            'movement_type' => ['transfer'],
            'date_from' => 'not-a-date',
            'date_to' => '2026-99-99',
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters', [
                'search' => '',
                'asset_group_id' => '',
                'asset_subsystem_id' => '',
                'stock_status' => 'all',
                'unit_kerja_id' => '',
                'tab' => 'stock',
                'movement_type' => '',
                'date_from' => '',
                'date_to' => '',
                'master_page' => '1',
            ]));
    }

    public function test_master_client_page_is_normalized_and_round_trips_without_changing_inventory_scope(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        $ownPart = SparePart::factory()->create();
        $otherPart = SparePart::factory()->create();
        InventoryStock::factory()->for($unit)->for($ownPart)->create();
        InventoryStock::factory()->for($otherUnit)->for($otherPart)->create();

        $this->actingAs($user)->get('/inventory?tab=master&master_page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.master_page', '2')
                ->has('stocks.data', 1)
                ->where('stocks.data.0.unit_kerja_id', $unit->id));
    }

    public function test_stock_and_movement_pagination_are_independent_and_preserve_filters(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();
        foreach (range(1, 21) as $index) {
            $part = SparePart::factory()->create(['detail_equipment' => "Matched part {$index}"]);
            InventoryStock::factory()->for($unit)->for($part)->create();
            StockMovement::factory()->for($unit)->for($part)->for($user, 'actor')->create([
                'notes' => 'matched history',
            ]);
        }

        $this->actingAs($user)->get('/inventory?search=matched&page=2&movement_page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.current_page', 2)
                ->has('stocks.data', 1)
                ->where('movements.current_page', 2)
                ->has('movements.data', 1)
                ->where('stocks.prev_page_url', fn (string $url): bool => str_contains($url, 'search=matched'))
                ->where('movements.prev_page_url', fn (string $url): bool => str_contains($url, 'movement_page=1')));
    }

    public function test_oversized_pagination_values_are_clamped_safely(): void
    {
        $user = User::factory()->unit()->create();
        $huge = str_repeat('9', 300);

        $this->actingAs($user)->get('/inventory?'.http_build_query([
            'page' => $huge,
            'movement_page' => $huge,
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.current_page', 1_000_000)
                ->where('movements.current_page', 1_000_000));
    }

    public function test_history_filters_by_type_date_and_selected_unit(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $pusat = User::factory()->pusat()->create();
        $part = SparePart::factory()->create();
        $matching = StockMovement::factory()->for($unit)->for($part)->for($pusat, 'actor')->create([
            'type' => StockMovementType::Out,
            'direction' => StockDirection::Out,
            'movement_date' => '2026-07-15',
        ]);
        StockMovement::factory()->for($unit)->for($part)->for($pusat, 'actor')->create([
            'type' => StockMovementType::In,
            'movement_date' => '2026-07-15',
        ]);
        StockMovement::factory()->for($unit)->for($part)->for($pusat, 'actor')->create([
            'type' => StockMovementType::Out,
            'direction' => StockDirection::Out,
            'movement_date' => '2026-06-30',
        ]);
        StockMovement::factory()->for($otherUnit)->for($part)->for($pusat, 'actor')->create([
            'type' => StockMovementType::Out,
            'direction' => StockDirection::Out,
            'movement_date' => '2026-07-15',
        ]);

        $this->actingAs($pusat)->get('/inventory?'.http_build_query([
            'unit_kerja_id' => $unit->id,
            'movement_type' => 'out',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'tab' => 'history',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $matching->id)
            ->where('filters.tab', 'history')
            ->where('filters.movement_type', 'out'));
    }

    public function test_history_date_filters_use_sargable_date_column_comparisons(): void
    {
        $user = User::factory()->unit()->create();
        $queries = collect();
        DB::listen(fn (QueryExecuted $query) => $queries->push(strtolower($query->sql)));

        $this->actingAs($user)->get('/inventory?date_from=2026-07-01&date_to=2026-07-31')->assertOk();

        $movementSql = $queries
            ->filter(fn (string $sql): bool => str_contains($sql, 'stock_movements'))
            ->implode(' ');
        $this->assertStringNotContainsString('date(`stock_movements`.`movement_date`)', $movementSql);
        $this->assertStringContainsString('`stock_movements`.`movement_date` >= ?', $movementSql);
        $this->assertStringContainsString('`stock_movements`.`movement_date` <= ?', $movementSql);
    }

    public function test_access_policy_reorder_redirect_and_props_contain_no_dummy_metrics(): void
    {
        $user = User::factory()->unit()->create();

        $this->get('/inventory')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unit()->inactive()->create())
            ->get('/inventory')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/reorder-stock')
            ->assertRedirect('/inventory?tab=master');
        $this->actingAs($user)->get('/inventory')
            ->assertInertia(fn (Assert $page) => $page
                ->has('stats.total_parts')
                ->has('stats.total_quantity')
                ->has('stats.below_reorder')
                ->has('stats.movements_this_month')
                ->missing('stats.predictions')
                ->missing('stats.purchase_orders')
                ->missing('stats.shipments'));
    }

    public function test_master_asset_hierarchy_adds_ledger_to_baseline_without_cross_unit_leakage(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($ownUnit)->create();
        [, , $subsystem] = $this->categoryPath('Signal', 'Interlocking', 'Track Circuit');
        Asset::factory()->for($ownUnit)->for($subsystem, 'assetSubsystem')->create();
        $part = SparePart::factory()->for($subsystem)->create();
        UnitSubsystemOpening::factory()->for($ownUnit)->for($subsystem, 'assetSubsystem')->create([
            'sparepart_in' => 10,
            'sparepart_out' => 4,
        ]);
        StockMovement::factory()->for($ownUnit)->for($part)->for($user, 'actor')->create([
            'direction' => StockDirection::In,
            'quantity' => 3,
        ]);
        StockMovement::factory()->for($ownUnit)->for($part)->for($user, 'actor')->create([
            'type' => StockMovementType::Correction,
            'direction' => StockDirection::Out,
            'quantity' => 2,
        ]);
        StockMovement::factory()->for($otherUnit)->for($part)->create([
            'direction' => StockDirection::In,
            'quantity' => 100,
        ]);

        $this->actingAs($user)->get('/master-asset')
            ->assertInertia(fn (Assert $page) => $page
                ->where('hierarchy.0.sparepart_in', 13)
                ->where('hierarchy.0.sparepart_out', 6));

        $pusat = User::factory()->pusat()->create();
        $this->actingAs($pusat)->get('/master-asset?unit_kerja_id='.$ownUnit->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('hierarchy.0.sparepart_in', 13)
                ->where('hierarchy.0.sparepart_out', 6));
        $this->actingAs($pusat)->get('/master-asset')
            ->assertInertia(fn (Assert $page) => $page
                ->where('hierarchy.0.sparepart_in', 113)
                ->where('hierarchy.0.sparepart_out', 6));
    }

    public function test_invalid_pusat_hierarchy_unit_selection_is_normalized_to_all_units(): void
    {
        $pusat = User::factory()->pusat()->create();
        $firstUnit = UnitKerja::factory()->create();
        $secondUnit = UnitKerja::factory()->create();
        $inactiveUnit = UnitKerja::factory()->create(['is_active' => false]);
        [, , $subsystem] = $this->categoryPath('Signal all', 'System all', 'Subsystem all');
        Asset::factory()->for($firstUnit)->for($subsystem, 'assetSubsystem')->create(['jumlah_unit' => 4]);
        Asset::factory()->for($secondUnit)->for($subsystem, 'assetSubsystem')->create(['jumlah_unit' => 6]);
        UnitSubsystemOpening::factory()->for($firstUnit)->for($subsystem, 'assetSubsystem')->create([
            'sparepart_in' => 2,
            'sparepart_out' => 1,
        ]);
        UnitSubsystemOpening::factory()->for($secondUnit)->for($subsystem, 'assetSubsystem')->create([
            'sparepart_in' => 3,
            'sparepart_out' => 2,
        ]);

        foreach ([$inactiveUnit->id, 999_999] as $invalidUnitId) {
            $row = app(AssetHierarchyQuery::class)->forUser($pusat, $invalidUnitId, [$subsystem->id])->sole();
            $this->assertSame(10, $row->total);
            $this->assertSame(5, $row->sparepart_in);
            $this->assertSame(3, $row->sparepart_out);

            $this->actingAs($pusat)->get('/master-asset?unit_kerja_id='.$invalidUnitId)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.unit_kerja_id', '')
                    ->has('assets.data', 2)
                    ->where('hierarchy.0.total', 10)
                    ->where('hierarchy.0.sparepart_in', 5)
                    ->where('hierarchy.0.sparepart_out', 3));
        }
    }

    /** @return array{AssetGroup, AssetSystem, AssetSubsystem} */
    private function categoryPath(string $groupName, string $systemName, string $subsystemName): array
    {
        $group = AssetGroup::factory()->create(['name' => $groupName]);
        $system = AssetSystem::factory()->for($group)->create(['name' => $systemName]);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => $subsystemName]);

        return [$group, $system, $subsystem];
    }

    /** @param array<string, array<string, string>> $expected */
    private function hasHistoricalBreadcrumbs($rows, array $expected): bool
    {
        $byCode = collect($rows)->keyBy('spare_part.code');

        return collect($expected)->every(function (array $names, string $code) use ($byCode): bool {
            $category = $byCode->get($code)['spare_part']['category'] ?? null;

            return $category !== null
                && $category['group']['name'] === $names['group']
                && $category['system']['name'] === $names['system']
                && $category['subsystem']['name'] === $names['subsystem'];
        });
    }
}
