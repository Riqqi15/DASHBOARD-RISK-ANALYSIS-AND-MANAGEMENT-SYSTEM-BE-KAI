<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MasterAssetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_sees_all_assets_while_unit_sees_only_its_own(): void
    {
        $firstUnit = UnitKerja::factory()->create();
        $secondUnit = UnitKerja::factory()->create();
        $firstAsset = Asset::factory()->for($firstUnit)->create();
        $secondAsset = Asset::factory()->for($secondUnit)->create();
        $pusat = User::factory()->pusat()->create();
        $regional = User::factory()->unit($firstUnit)->create();

        $this->assertEqualsCanonicalizing(
            [$firstAsset->id, $secondAsset->id],
            Asset::query()->visibleTo($pusat)->pluck('id')->all(),
        );
        $this->assertSame(
            [$firstAsset->id],
            Asset::query()->visibleTo($regional)->pluck('id')->all(),
        );
    }

    public function test_policy_rejects_an_asset_from_another_unit(): void
    {
        $ownerUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($ownerUnit)->create();
        $owner = User::factory()->unit($ownerUnit)->create();
        $outsider = User::factory()->unit($otherUnit)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $asset));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $asset));
        $this->assertTrue(Gate::forUser(User::factory()->pusat()->create())->allows('delete', $asset));
    }
}
