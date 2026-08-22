<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\InventoryStock;
use App\Models\PredictiveAssetSnapshot;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InventoryExcelReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_reports_matches_differences_missing_and_ambiguous_rows_without_writes(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $user = User::factory()->unit($unit)->create();
        $subsystem = AssetSubsystem::factory()->create(['name' => 'Catu Daya Sinyal']);
        $missingSubsystem = AssetSubsystem::factory()->create(['name' => 'Perlintasan']);
        $matched = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Relay 24 VDC']);
        $missingLedger = Asset::factory()
            ->for($unit)
            ->for($missingSubsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Rectifier']);
        $ambiguous = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Modul umum']);
        $this->snapshot($matched, 8, 1);
        $this->snapshot($missingLedger, 3, 2);
        $this->snapshot($ambiguous, 1, 3);
        $relay = SparePart::factory()
            ->for($subsystem, 'assetSubsystem')
            ->create(['detail_equipment' => 'Relay 24 VDC']);
        $orphan = SparePart::factory()
            ->for($subsystem, 'assetSubsystem')
            ->create(['detail_equipment' => 'Kabel cadangan']);
        $other = SparePart::factory()
            ->for($subsystem, 'assetSubsystem')
            ->create(['detail_equipment' => 'Fuse cadangan']);
        InventoryStock::factory()
            ->for($unit)
            ->for($relay)
            ->create(['quantity' => 6]);
        InventoryStock::factory()
            ->for($unit)
            ->for($orphan)
            ->create(['quantity' => 4]);
        InventoryStock::factory()
            ->for($unit)
            ->for($other)
            ->create(['quantity' => 2]);
        $stocksBefore = InventoryStock::query()->orderBy('id')->get()->map->getRawOriginal()->all();

        $result = app(InventoryReconciliationService::class)->reconcile($user, []);
        $statuses = collect($result['rows'])->pluck('status');

        $this->assertContains('difference', $statuses);
        $this->assertContains('missing_ledger', $statuses);
        $this->assertContains('ambiguous', $statuses);
        $this->assertContains('missing_excel', $statuses);
        $difference = collect($result['rows'])->firstWhere('asset_id', $matched->id);
        $this->assertSame(8, $difference['excel_stock']);
        $this->assertSame(6, $difference['ledger_stock']);
        $this->assertSame(2, $difference['difference']);
        $this->assertSame($stocksBefore, InventoryStock::query()->orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_reconciliation_is_scoped_to_the_users_unit(): void
    {
        $own = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $other = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $subsystem = AssetSubsystem::factory()->create();
        $ownAsset = Asset::factory()
            ->for($own)
            ->for($subsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Own']);
        $otherAsset = Asset::factory()
            ->for($other)
            ->for($subsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Other']);
        $this->snapshot($ownAsset, 1, 1);
        $this->snapshot($otherAsset, 1, 2);

        $rows = app(InventoryReconciliationService::class)->reconcile(User::factory()->unit($own)->create(), [])[
            'rows'
        ];

        $this->assertSame([$ownAsset->id], collect($rows)->pluck('asset_id')->filter()->values()->all());
    }

    private function snapshot(Asset $asset, int $currentStock, int $row): PredictiveAssetSnapshot
    {
        return PredictiveAssetSnapshot::query()->create([
            'asset_id' => $asset->id,
            'source_key' => hash('sha256', "asset-{$asset->id}"),
            'workbook_hash' => str_repeat('a', 64),
            'workbook_name' => 'RAMS.xlsx',
            'source_row' => $row,
            'function_criterion' => 1,
            'production_impact' => 1,
            'lead_time_months' => 1,
            'price_category' => 'Low',
            'current_stock' => $currentStock,
            'total_assets' => 1,
            'criticality' => 'Low',
            'lead_time_category' => 'Short',
            'inventory_policy' => 'One Piece in Stock',
            'needed_stock' => 1,
            'proposal_quantity' => 0,
            'safety_stock_usage' => 0,
            'safety_stock_mca' => 0,
            'safety_stock_failure' => 0,
            'final_safety_stock' => 0,
            'calculation_status' => 'calculated',
            'formula_version' => 'test-v1',
            'calculated_at' => now(),
        ]);
    }
}
