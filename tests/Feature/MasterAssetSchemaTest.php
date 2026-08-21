<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterAssetSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_store_core_data_and_support_soft_delete(): void
    {
        $unit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($unit)->create([
            'nama_aset' => 'Track Circuit Gambir',
            'status' => AssetStatus::DalamPerbaikan,
            'tanggal_pemasangan' => '2019-06-10',
        ]);

        $this->assertTrue(Schema::hasColumns('assets', [
            'unit_kerja_id',
            'asset_subsystem_id',
            'nama_aset',
            'aset_prasarana_sintel',
            'system',
            'subsystem',
            'jumlah_unit',
            'tanggal_pemasangan',
            'status',
            'source_key',
            'deleted_at',
        ]));
        $this->assertFalse(Schema::hasColumn('assets', 'lokasi'));
        $this->assertSame(AssetStatus::DalamPerbaikan, $asset->status);
        $this->assertSame('2019-06-10', $asset->tanggal_pemasangan->toDateString());

        $asset->delete();

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }
}
