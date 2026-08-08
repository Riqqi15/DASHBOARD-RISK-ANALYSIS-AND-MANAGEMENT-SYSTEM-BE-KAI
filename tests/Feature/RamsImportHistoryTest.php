<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RamsImportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_user_only_sees_and_downloads_its_own_import_history(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $user = User::factory()->unit($unit)->create();
        $ownBatch = $this->batch($unit, 'own.xlsx');
        $otherBatch = $this->batch($otherUnit, 'other.xlsx');
        $otherBatch->issues()->create(['severity' => 'warning', 'message' => 'Rahasia unit lain']);

        $this->actingAs($user)->get('/trouble-report/import')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 1)
                ->where('history.0.id', $ownBatch->id)
                ->where('history.0.workbook_name', 'own.xlsx')
                ->where('history.0.progress_stage', 'Import selesai')
                ->where('history.0.progress_percent', 100));

        $this->actingAs($user)
            ->get("/trouble-report/import/batch/{$otherBatch->id}/issues/csv")
            ->assertNotFound();
    }

    public function test_pusat_user_sees_history_for_all_units(): void
    {
        $first = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $second = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $this->batch($first, 'first.xlsx');
        $this->batch($second, 'second.xlsx');
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)->get('/trouble-report/import')
            ->assertInertia(fn (Assert $page) => $page->has('history', 2));
    }

    public function test_batch_status_exposes_progress_summary_and_issues_with_unit_scope(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $user = User::factory()->unit($unit)->create();
        $batch = $this->batch($unit, 'own.xlsx');
        $batch->update([
            'status' => 'processing',
            'progress_stage' => 'Memproses Risk Register',
            'progress_percent' => 50,
            'summary' => ['risk_registers_created' => 4],
        ]);
        $batch->issues()->create([
            'sheet_name' => 'LxC',
            'source_row' => 11,
            'severity' => 'warning',
            'message' => 'Baris dilewati',
        ]);
        $other = $this->batch($otherUnit, 'other.xlsx');

        $this->actingAs($user)->getJson("/trouble-report/import/batch/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.progress_percent', 50)
            ->assertJsonPath('data.progress_stage', 'Memproses Risk Register')
            ->assertJsonPath('data.summary.risk_registers_created', 4)
            ->assertJsonPath('data.issues.0.source_row', 11);

        $this->actingAs($user)->getJson("/trouble-report/import/batch/{$other->id}")
            ->assertNotFound();
    }

    private function batch(UnitKerja $unit, string $workbook): RamsImportBatch
    {
        return RamsImportBatch::query()->create([
            'unit_kerja_id' => $unit->id,
            'fingerprint' => hash('sha256', $unit->id.'|'.$workbook),
            'import_version' => 'test-v1',
            'workbook_name' => $workbook,
            'file_size' => 1024,
            'status' => 'succeeded',
            'dry_run' => false,
            'summary' => ['created' => 1],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
