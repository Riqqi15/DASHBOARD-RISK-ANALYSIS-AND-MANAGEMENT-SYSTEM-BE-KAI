<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Asset::factory()->for($ownUnit)->create([
            'nama_aset' => 'Track Circuit Gambir',
            'jumlah_unit' => 12,
            'tanggal_pemasangan' => '2012-01-01',
            'status' => AssetStatus::Aktif,
        ]);
        Asset::factory()->for($ownUnit)->create(['nama_aset' => 'Axle Counter']);
        Asset::factory()->for($otherUnit)->create(['nama_aset' => 'Track Circuit Cirebon']);

        $this->actingAs($user)->get('/master-asset?search=Gambir&status=aktif')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/assets/MasterAsset')
                ->has('assets.data', 1)
                ->where('assets.data.0.nama_aset', 'Track Circuit Gambir')
                ->where('assets.data.0.tanggal_pemasangan', '2012-01-01')
                ->where('stats.total_assets', 1)
                ->where('stats.total_units', 12)
                ->where('filters.search', 'Gambir')
                ->where('filters.status', 'aktif')
                ->where('can.choose_unit', false));
    }

    public function test_pusat_can_filter_assets_by_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        Asset::factory()->for($unit)->create();
        Asset::factory()->create();

        $this->actingAs($pusat)->get("/master-asset?unit_kerja_id={$unit->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 1)
                ->where('can.choose_unit', true)
                ->has('units'));
    }

    public function test_unit_creates_an_asset_only_for_its_own_unit(): void
    {
        $unit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)->post('/master-asset', [
            'unit_kerja_id' => $otherUnit->id,
            ...$this->assetPayload(),
        ])->assertSessionHasErrors('unit_kerja_id');

        $this->actingAs($user)->post('/master-asset', $this->assetPayload())
            ->assertRedirect('/master-asset');

        $this->assertDatabaseHas('assets', [
            'unit_kerja_id' => $unit->id,
            'nama_aset' => 'Track Circuit Gambir',
            'lokasi' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.created']);
    }

    public function test_cross_unit_mutations_return_not_found(): void
    {
        $ownerUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($ownerUnit)->create();
        $outsider = User::factory()->unit($otherUnit)->create();

        $this->actingAs($outsider)->get("/master-asset/{$asset->id}/edit")->assertNotFound();
        $this->actingAs($outsider)->put("/master-asset/{$asset->id}", [])->assertNotFound();
        $this->actingAs($outsider)->delete("/master-asset/{$asset->id}")->assertNotFound();
    }

    public function test_pusat_updates_and_soft_deletes_an_asset_with_audit_logs(): void
    {
        $pusat = User::factory()->pusat()->create();
        $asset = Asset::factory()->create();
        $payload = [
            'unit_kerja_id' => $asset->unit_kerja_id,
            ...$this->assetPayload([
                'nama_aset' => 'Nama Aset Diperbarui',
                'lokasi' => 'Stasiun Gambir',
                'jumlah_unit' => 20,
                'tanggal_pemasangan' => '2018-01-01',
                'status' => 'dalam_perbaikan',
            ]),
        ];

        $this->actingAs($pusat)->put("/master-asset/{$asset->id}", $payload)
            ->assertRedirect('/master-asset');
        $this->actingAs($pusat)->delete("/master-asset/{$asset->id}")
            ->assertRedirect('/master-asset');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.deleted']);
    }

    private function assetPayload(array $overrides = []): array
    {
        return [
            'nama_aset' => '  Track   Circuit Gambir  ',
            'aset_prasarana_sintel' => 'Peralatan Luar Sinyal Elektrik',
            'system' => 'Peraga Sinyal Elektrik',
            'subsystem' => 'Track Circuit',
            'lokasi' => '',
            'jumlah_unit' => 12,
            'tanggal_pemasangan' => '2019-06-10',
            'status' => 'aktif',
            ...$overrides,
        ];
    }
}
