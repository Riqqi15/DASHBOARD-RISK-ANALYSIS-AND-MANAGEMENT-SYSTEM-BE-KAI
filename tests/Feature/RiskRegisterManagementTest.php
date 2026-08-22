<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RiskRegisterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_user_only_sees_risks_and_assets_from_its_unit(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $other = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $asset = Asset::factory()
            ->for($unit)
            ->create(['nama_aset' => 'Aset Sendiri']);
        $otherAsset = Asset::factory()
            ->for($other)
            ->create(['nama_aset' => 'Aset Lain']);
        $risk = RiskRegister::factory()->for($asset)->create();
        RiskRegister::factory()->for($otherAsset)->create();
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)
            ->get('/risk-register')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('risk-register/Index')
                    ->has('registers', 1)
                    ->where('registers.0.id', $risk->id)
                    ->has('assets', 1)
                    ->where('assets.0.id', $asset->id),
            );
    }

    public function test_unit_user_can_create_update_and_delete_risk_for_its_asset(): void
    {
        $unit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($unit)->create();
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)
            ->post(
                '/risk-register',
                $this->payload($asset, [
                    'risk_event' => 'Relay gagal bekerja',
                ]),
            )
            ->assertRedirect('/risk-register');

        $risk = RiskRegister::query()->sole();
        $this->assertSame('Relay gagal bekerja', $risk->risk_event);
        $this->assertSame(6, $risk->rating);

        $this->actingAs($user)
            ->put(
                "/risk-register/{$risk->id}",
                $this->payload($asset, [
                    'status' => 'in_progress',
                    'recommendation' => 'Ganti relay pada inspeksi berikutnya',
                ]),
            )
            ->assertRedirect();

        $this->assertSame('in_progress', $risk->refresh()->status->value);
        $this->actingAs($user)
            ->delete("/risk-register/{$risk->id}")
            ->assertRedirect();
        $this->assertDatabaseCount('risk_registers', 0);
    }

    public function test_unit_user_cannot_mutate_risk_from_another_unit(): void
    {
        $unit = UnitKerja::factory()->create();
        $other = UnitKerja::factory()->create();
        $ownAsset = Asset::factory()->for($unit)->create();
        $otherAsset = Asset::factory()->for($other)->create();
        $otherRisk = RiskRegister::factory()->for($otherAsset)->create();
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)->post('/risk-register', $this->payload($otherAsset))->assertForbidden();
        $this->actingAs($user)
            ->put("/risk-register/{$otherRisk->id}", $this->payload($ownAsset))
            ->assertForbidden();
        $this->actingAs($user)
            ->delete("/risk-register/{$otherRisk->id}")
            ->assertForbidden();
    }

    public function test_pusat_reads_and_mutates_only_the_selected_area(): void
    {
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $assetOne = Asset::factory()
            ->for($daopOne)
            ->create(['nama_aset' => 'Aset DAOP-1']);
        $assetTwo = Asset::factory()
            ->for($daopTwo)
            ->create(['nama_aset' => 'Aset DAOP-2']);
        RiskRegister::factory()
            ->for($assetOne)
            ->create(['risk_event' => 'Risiko DAOP-1']);
        RiskRegister::factory()
            ->for($assetTwo)
            ->create(['risk_event' => 'Risiko DAOP-2']);
        $pusat = User::factory()->pusat()->create();

        $this->actingAs($pusat)
            ->get('/risk-register?area=DAOP-1')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('selected_area', 'DAOP-1')
                    ->has('assets', 1)
                    ->where('assets.0.id', $assetOne->id)
                    ->has('registers', 1)
                    ->where('registers.0.risk_event', 'Risiko DAOP-1'),
            );

        $this->actingAs($pusat)
            ->post('/risk-register?area=DAOP-1', [...$this->payload($assetTwo), 'unit_kerja_id' => $daopOne->id])
            ->assertForbidden();

        $this->actingAs($pusat)
            ->post('/risk-register?area=DAOP-1', [
                ...$this->payload($assetOne, ['risk_event' => 'Risiko manual']),
                'unit_kerja_id' => $daopOne->id,
            ])
            ->assertRedirect('/risk-register?area=DAOP-1');
    }

    public function test_pusat_without_active_unit_sees_no_risk_data(): void
    {
        $pusat = User::factory()->pusat()->create();

        $this->actingAs($pusat)
            ->get('/risk-register')
            ->assertInertia(
                fn (Assert $page) => $page->where('selected_area', null)->has('assets', 0)->has('registers', 0),
            );
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Asset $asset, array $overrides = []): array
    {
        return [
            'asset_id' => $asset->id,
            'part_number' => 'RLY-001',
            'sub' => 'Interlocking Elektrik',
            'risk_event' => 'Gangguan relay',
            'risk_cause' => 'Kontak relay aus',
            'impact' => 'Rute tidak dapat terbentuk',
            'part_name' => 'Relay module',
            'recommendation' => 'Inspeksi berkala',
            'likelihood' => 2,
            'consequence' => 3,
            'status' => 'open',
            ...$overrides,
        ];
    }
}
