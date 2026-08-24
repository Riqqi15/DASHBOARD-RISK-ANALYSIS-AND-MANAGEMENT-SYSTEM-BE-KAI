<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\RiskMatrix;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RamsModuleUnitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_asset_and_inventory_reference_data_follow_the_selected_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        [$groupOne, $subsystemOne] = $this->categoryPath($daopOne, 'KELOMPOK DAOP SATU');
        [$groupTwo, $subsystemTwo] = $this->categoryPath($daopTwo, 'KELOMPOK DAOP DUA');
        $assetOne = Asset::factory()->for($daopOne)->for($subsystemOne, 'assetSubsystem')->create();
        Asset::factory()->for($daopTwo)->for($subsystemTwo, 'assetSubsystem')->create();
        $partOne = SparePart::factory()->for($subsystemOne)->create(['code' => 'PART-DAOP-1']);
        SparePart::factory()->for($subsystemTwo)->create(['code' => 'PART-DAOP-2']);

        $this->actingAs($pusat)
            ->get('/master-asset?unit_kerja_id='.$daopOne->id)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('assets.data', 1)
                    ->where('assets.data.0.id', $assetOne->id)
                    ->has('assetCategories', 1)
                    ->where('assetCategories.0.id', $groupOne->id),
            );

        $this->get('/inventory?unit_kerja_id='.$daopOne->id)
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('spareParts', 1)
                    ->where('spareParts.0.id', $partOne->id)
                    ->has('categories', 1)
                    ->where('categories.0.id', $groupOne->id),
            );

        $this->assertSame($daopOne->id, session('rams.active_unit_id'));
    }

    public function test_risk_report_and_trouble_report_reads_follow_the_active_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $divre = UnitKerja::factory()->create(['code' => 'DIVRE-III']);
        [, $subsystemOne] = $this->categoryPath($daopOne, 'UNIT SATU');
        [, $subsystemDivre] = $this->categoryPath($divre, 'UNIT DIVRE');
        $assetOne = Asset::factory()->for($daopOne)->for($subsystemOne, 'assetSubsystem')->create();
        $assetDivre = Asset::factory()->for($divre)->for($subsystemDivre, 'assetSubsystem')->create();
        $matrixOne = RiskMatrix::factory()->for($assetOne)->create();
        RiskMatrix::factory()->for($assetDivre)->create();
        $registerOne = RiskRegister::factory()->for($assetOne)->create();
        RiskRegister::factory()->for($assetDivre)->create();
        $failureOne = FailureLog::factory()->for($assetOne)->for($pusat, 'creator')->create();
        FailureLog::factory()->for($assetDivre)->for($pusat, 'creator')->create();

        $this->actingAs($pusat)->get('/dashboard?area=DAOP-1')->assertOk();

        $this->get('/risk-matrix')->assertInertia(
            fn (Assert $page) => $page
                ->has('risks', 1)
                ->where('risks.0.id', $matrixOne->id),
        );
        $this->get('/risk-register')->assertInertia(
            fn (Assert $page) => $page
                ->has('assets', 1)
                ->where('assets.0.id', $assetOne->id)
                ->has('registers', 1)
                ->where('registers.0.id', $registerOne->id),
        );
        $this->get('/trouble-report?subsystem='.urlencode($subsystemOne->name))->assertInertia(
            fn (Assert $page) => $page
                ->has('assets', 1)
                ->where('assets.0.id', $assetOne->id)
                ->has('failure_logs', 1)
                ->where('failure_logs.0.id', $failureOne->id),
        );
        $this->get('/reports')->assertInertia(
            fn (Assert $page) => $page->where('selected_area', 'DAOP-1'),
        );
    }

    public function test_pusat_cannot_mutate_master_or_risk_data_outside_the_active_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        [, $subsystemOne] = $this->categoryPath($daopOne, 'UNIT SATU');
        [, $subsystemTwo] = $this->categoryPath($daopTwo, 'UNIT DUA');
        $assetTwo = Asset::factory()->for($daopTwo)->for($subsystemTwo, 'assetSubsystem')->create();

        $this->withSession(['rams.active_unit_id' => $daopOne->id])
            ->actingAs($pusat)
            ->delete('/master-asset/'.$assetTwo->id)
            ->assertNotFound();
        $this->assertDatabaseHas('assets', ['id' => $assetTwo->id]);

        $this->from('/master-asset/create')
            ->post('/master-asset', [
                'unit_kerja_id' => $daopOne->id,
                'asset_subsystem_id' => $subsystemTwo->id,
                'nama_aset' => 'Aset silang',
                'jumlah_unit' => 1,
                'tanggal_pemasangan' => '2020-01-01',
                'status' => AssetStatus::Aktif->value,
            ])
            ->assertSessionHasErrors('asset_subsystem_id');
        $this->assertDatabaseMissing('assets', ['nama_aset' => 'Aset silang']);

        $this->from('/risk-register?area=DAOP-1')
            ->post('/risk-register', [
                'unit_kerja_id' => $daopTwo->id,
                'asset_id' => $assetTwo->id,
                'risk_event' => 'Risiko silang',
                'risk_cause' => 'Manipulasi unit',
                'likelihood' => 2,
                'consequence' => 2,
                'status' => 'open',
            ])
            ->assertSessionHasErrors('unit_kerja_id');
        $this->assertDatabaseMissing('risk_registers', ['risk_event' => 'Risiko silang']);

        $this->assertSame($subsystemOne->assetSystem->assetGroup->unit_kerja_id, $daopOne->id);
    }

    public function test_pusat_cannot_mutate_failure_or_stock_data_outside_the_active_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        [, $subsystemTwo] = $this->categoryPath($daopTwo, 'UNIT DUA');
        $assetTwo = Asset::factory()->for($daopTwo)->for($subsystemTwo, 'assetSubsystem')->create();
        $partTwo = SparePart::factory()->for($subsystemTwo)->create();
        InventoryStock::factory()->for($daopTwo)->for($partTwo)->create(['quantity' => 5]);
        $failure = FailureLog::factory()->for($assetTwo)->for($pusat, 'creator')->create();
        $movement = StockMovement::factory()
            ->for($daopTwo)
            ->for($partTwo)
            ->for($pusat, 'actor')
            ->create();

        $this->withSession(['rams.active_unit_id' => $daopOne->id])->actingAs($pusat);

        $this->post('/trouble-report', [
            'asset_id' => $assetTwo->id,
            'location' => 'DAOP dua',
            'failure_event' => 'Gangguan silang',
            'cause' => 'Manipulasi aset',
            'action_taken' => 'Tidak boleh tersimpan',
            'started_at' => '2026-08-20 08:00:00',
            'resolved_at' => '2026-08-20 09:00:00',
            'spare_part_replaced' => false,
            'vandalism' => false,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertDatabaseMissing('failure_logs', ['failure_event' => 'Gangguan silang']);

        $this->delete('/trouble-report/'.$failure->id)->assertNotFound();
        $this->assertDatabaseHas('failure_logs', ['id' => $failure->id]);

        $this->post('/inventory/movements', [
            'unit_kerja_id' => $daopTwo->id,
            'spare_part_id' => $partTwo->id,
            'type' => StockMovementType::In->value,
            'direction' => StockDirection::In->value,
            'quantity' => 1,
            'movement_date' => '2026-08-20',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('unit_kerja_id');

        $this->post('/inventory/movements/'.$movement->id.'/corrections', [
            'direction' => StockDirection::Out->value,
            'quantity' => 1,
            'movement_date' => '2026-08-20',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    /** @return array{AssetGroup, AssetSubsystem} */
    private function categoryPath(UnitKerja $unit, string $groupName): array
    {
        $group = AssetGroup::factory()->create([
            'unit_kerja_id' => $unit->id,
            'name' => $groupName,
        ]);
        $system = AssetSystem::factory()->create([
            'asset_group_id' => $group->id,
            'name' => $groupName.' SYSTEM',
        ]);
        $subsystem = AssetSubsystem::factory()->create([
            'asset_system_id' => $system->id,
            'name' => $groupName.' SUBSYSTEM',
        ]);

        return [$group, $subsystem];
    }
}
