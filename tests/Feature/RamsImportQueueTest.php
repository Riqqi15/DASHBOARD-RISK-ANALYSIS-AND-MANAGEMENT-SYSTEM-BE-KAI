<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessRamsWorkbookImport;
use App\Models\RamsImportBatch;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RamsImportQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_upload_auto_detects_unit_stores_private_file_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('local');
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => true]);
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)->post('/trouble-report/import', [
            'workbook' => UploadedFile::fake()->create(
                'Risk Analysis And Management System RAMS Daop 4.xlsx',
                100,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])->assertRedirect('/trouble-report/import')->assertSessionHasNoErrors();

        $batch = RamsImportBatch::query()->sole();
        $this->assertSame($unit->id, $batch->unit_kerja_id);
        $this->assertSame($user->id, $batch->uploaded_by_user_id);
        $this->assertSame('queued', $batch->status);
        $this->assertSame(0, $batch->progress_percent);
        Storage::disk('local')->assertExists($batch->stored_path);
        Queue::assertPushed(ProcessRamsWorkbookImport::class, fn (ProcessRamsWorkbookImport $job): bool => $job->batchId === $batch->id);
    }

    public function test_recognized_filename_cannot_be_sent_to_a_different_unit(): void
    {
        Queue::fake();
        Storage::fake('local');
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $wrong = UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => true]);
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)->post('/trouble-report/import', [
            'unit_kerja_id' => $wrong->id,
            'workbook' => UploadedFile::fake()->create('RAMS Daop 1.xlsx', 10),
        ])->assertSessionHasErrors('unit_kerja_id');

        $this->assertDatabaseCount('rams_import_batches', 0);
        Queue::assertNothingPushed();
    }

    public function test_reuploading_the_same_workbook_creates_a_new_history_batch(): void
    {
        Queue::fake();
        Storage::fake('local');
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $user = User::factory()->pusat()->create();

        foreach ([1, 2] as $attempt) {
            $this->actingAs($user)->post('/trouble-report/import', [
                'workbook' => UploadedFile::fake()->createWithContent(
                    'Risk Analysis And Management System RAMS Daop 1.xlsx',
                    'identical workbook bytes',
                ),
            ])->assertRedirect('/trouble-report/import')->assertSessionHasNoErrors();
        }

        $batches = RamsImportBatch::query()->orderBy('id')->get();
        $this->assertCount(2, $batches);
        $this->assertSame($batches[0]->fingerprint, $batches[1]->fingerprint);
        $this->assertNotSame($batches[0]->stored_path, $batches[1]->stored_path);
        Queue::assertPushed(ProcessRamsWorkbookImport::class, 2);
    }

    public function test_unit_user_cannot_import_a_workbook_detected_for_another_unit(): void
    {
        Queue::fake();
        Storage::fake('local');
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => true]);
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)->post('/trouble-report/import', [
            'workbook' => UploadedFile::fake()->create('RAMS Daop 4.xlsx', 10),
        ])->assertSessionHasErrors('workbook');

        $this->assertDatabaseCount('rams_import_batches', 0);
        Queue::assertNothingPushed();
    }
}
