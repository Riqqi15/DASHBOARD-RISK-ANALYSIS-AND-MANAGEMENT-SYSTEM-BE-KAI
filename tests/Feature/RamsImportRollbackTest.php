<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\InventoryStock;
use App\Models\RamsImportBatch;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\RamsImportChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RamsImportRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_batch_can_be_rolled_back_atomically_without_touching_users_or_stock_ledger(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $pusat = User::factory()->pusat()->create();
        $unitUser = User::factory()->unit($unit)->create();
        $subsystem = AssetSubsystem::factory()->create();
        $asset = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Nama sebelum']);
        $part = SparePart::factory()->for($subsystem, 'assetSubsystem')->create();
        InventoryStock::factory()
            ->for($unit)
            ->for($part)
            ->create(['quantity' => 5]);
        StockMovement::factory()
            ->for($unit)
            ->for($part)
            ->for($unitUser, 'actor')
            ->create(['quantity' => 5]);
        $batch = $this->batch($unit, $pusat);
        $recorder = app(RamsImportChangeRecorder::class);
        $before = $recorder->snapshot();

        $asset->update(['nama_aset' => 'Nama hasil import']);
        $risk = RiskRegister::factory()
            ->for($asset)
            ->create(['risk_event' => 'Risiko import']);
        $recorder->record($batch, $before, $recorder->snapshot());
        $usersBefore = User::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        $stocksBefore = InventoryStock::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        $movementsBefore = StockMovement::query()->orderBy('id')->get()->map->getRawOriginal()->all();

        $this->actingAs($pusat)
            ->post("/trouble-report/import/batch/{$batch->id}/rollback")
            ->assertRedirect('/trouble-report/import')
            ->assertSessionHas('success');

        $this->assertSame('Nama sebelum', $asset->fresh()->nama_aset);
        $this->assertDatabaseMissing('risk_registers', ['id' => $risk->id]);
        $this->assertSame('rolled_back', $batch->fresh()->status);
        $this->assertSame($pusat->id, $batch->fresh()->rolled_back_by_user_id);
        $this->assertSame($usersBefore, User::query()->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($stocksBefore, InventoryStock::query()->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($movementsBefore, StockMovement::query()->orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_rollback_is_blocked_after_manual_change_or_a_later_import(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $pusat = User::factory()->pusat()->create();
        $asset = Asset::factory()
            ->for($unit)
            ->create(['nama_aset' => 'Sebelum']);
        $batch = $this->batch($unit, $pusat);
        $recorder = app(RamsImportChangeRecorder::class);
        $before = $recorder->snapshot();
        $asset->update(['nama_aset' => 'Hasil import']);
        $recorder->record($batch, $before, $recorder->snapshot());
        $asset->update(['nama_aset' => 'Edit manual']);

        $this->actingAs($pusat)
            ->post("/trouble-report/import/batch/{$batch->id}/rollback")
            ->assertRedirect('/trouble-report/import')
            ->assertSessionHas('error');
        $this->assertSame('Edit manual', $asset->fresh()->nama_aset);
        $this->assertSame('succeeded', $batch->fresh()->status);

        $asset->update(['nama_aset' => 'Hasil import']);
        $later = $this->batch($unit, $pusat, 'later.xlsx');
        $this->actingAs($pusat)
            ->post("/trouble-report/import/batch/{$batch->id}/rollback")
            ->assertSessionHas('error');
        $this->assertSame('succeeded', $later->fresh()->status);
        $this->assertSame('Hasil import', $asset->fresh()->nama_aset);
    }

    public function test_unit_user_cannot_rollback_an_import(): void
    {
        $unit = UnitKerja::factory()->create();
        $batch = $this->batch($unit, User::factory()->pusat()->create());
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)
            ->post("/trouble-report/import/batch/{$batch->id}/rollback")
            ->assertForbidden();
    }

    private function batch(UnitKerja $unit, User $actor, string $workbook = 'RAMS Daop 1.xlsx'): RamsImportBatch
    {
        return RamsImportBatch::query()->create([
            'unit_kerja_id' => $unit->id,
            'uploaded_by_user_id' => $actor->id,
            'fingerprint' => hash('sha256', $workbook.microtime(true).random_int(1, PHP_INT_MAX)),
            'import_version' => 'test-v1',
            'workbook_name' => $workbook,
            'file_size' => 100,
            'status' => 'succeeded',
            'progress_stage' => 'Import selesai',
            'progress_percent' => 100,
            'dry_run' => false,
            'summary' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
