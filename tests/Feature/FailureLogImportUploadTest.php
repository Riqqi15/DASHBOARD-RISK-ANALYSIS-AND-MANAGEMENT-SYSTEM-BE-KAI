<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\RamsImportBatch;
use App\Models\RamsImportIssue;
use App\Models\ReliabilitySummary;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\FailureLogImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class FailureLogImportUploadTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_import_page_requires_authentication(): void
    {
        $this->get('/trouble-report/import')->assertRedirect('/login');
        $this->post('/trouble-report/import')->assertRedirect('/login');
    }

    public function test_unit_user_sees_only_its_assigned_import_target(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)
            ->get('/trouble-report/import')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('input-data/TroubleReportImport')
                    ->where('can_choose_unit', false)
                    ->where('selected_unit_id', $unit->id)
                    ->where('units', []),
            );
    }

    public function test_pusat_user_can_choose_only_active_units(): void
    {
        $active = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => false]);
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)
            ->get('/trouble-report/import')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('input-data/TroubleReportImport')
                    ->where('can_choose_unit', true)
                    ->where('selected_unit_id', null)
                    ->has('units', 1)
                    ->where('units.0.id', $active->id),
            );
    }

    public function test_upload_validates_extension_size_and_pusat_unit_selection(): void
    {
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)
            ->post('/trouble-report/import', [
                'workbook' => UploadedFile::fake()->create('report.csv', 10, 'text/csv'),
            ])
            ->assertSessionHasErrors(['workbook', 'unit_kerja_id']);

        $unit = UnitKerja::factory()->create(['is_active' => true]);
        $this->actingAs($user)
            ->post('/trouble-report/import', [
                'unit_kerja_id' => $unit->id,
                'workbook' => UploadedFile::fake()->create(
                    'report.xlsx',
                    50 * 1024 + 1,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ])
            ->assertSessionHasErrors('workbook');
    }

    public function test_unit_upload_imports_to_its_own_unit_and_audits_row_issues(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-4', 'is_active' => true]);
        $group = AssetGroup::factory()->create([
            'unit_kerja_id' => $unit->id,
            'name' => '1. Peralatan Dalam Sinyal Elektrik',
        ]);
        $system = AssetSystem::factory()
            ->for($group)
            ->create(['name' => 'Interlocking Elektrik']);
        $subsystem = AssetSubsystem::factory()
            ->for($system)
            ->create(['name' => 'Interlocking Elektrik']);
        Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['jumlah_unit' => 2]);
        $user = User::factory()->unit($unit)->create();
        User::factory()
            ->pusat()
            ->create(['username' => 'pusat-audit']);
        $usersBefore = User::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        $workbook = $this->uploadedWorkbook();

        $response = $this->actingAs($user)->post('/trouble-report/import', [
            'unit_kerja_id' => $otherUnit->id,
            'workbook' => $workbook,
        ]);

        $response
            ->assertRedirect('/trouble-report/import')
            ->assertSessionHas('success')
            ->assertSessionHas('import_result');
        $result = $response->getSession()->get('import_result');
        $this->assertSame('succeeded', $result['status']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['risk_registers_created']);
        $this->assertSame(1, $result['spare_parts_created']);
        $this->assertSame(1, $result['snapshots']);
        $this->assertSame(1, $result['parity']['calculated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertCount(3, $result['issues']);
        $this->assertSame($unit->id, RamsImportBatch::query()->sole()->unit_kerja_id);
        $this->assertSame('succeeded', RamsImportBatch::query()->sole()->status);
        $this->assertSame(11, RamsImportIssue::query()->whereNotNull('source_row')->sole()->source_row);
        $this->assertDatabaseCount('failure_logs', 1);
        $this->assertSame('Gangguan interlocking', RiskRegister::query()->sole()->risk_event);
        $this->assertSame('Modul relay', SparePart::query()->sole()->detail_equipment);
        $this->assertDatabaseCount('reliability_excel_snapshots', 1);
        $this->assertSame('mismatch', ReliabilitySummary::query()->sole()->parity_status);
        $this->assertSame(
            $usersBefore,
            User::query()->orderBy('id')->get()->map->getRawOriginal()->all(),
            'Import workbook tidak boleh membuat atau mengubah akun pengguna.',
        );
    }

    public function test_identical_workbook_reimport_keeps_batch_history_and_skips_active_duplicates(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $group = AssetGroup::factory()->create([
            'unit_kerja_id' => $unit->id,
            'name' => '1. Peralatan Dalam Sinyal Elektrik',
        ]);
        $system = AssetSystem::factory()
            ->for($group)
            ->create(['name' => 'Interlocking Elektrik']);
        $subsystem = AssetSubsystem::factory()
            ->for($system)
            ->create(['name' => 'Interlocking Elektrik']);
        Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['jumlah_unit' => 2]);
        $user = User::factory()->unit($unit)->create();
        $workbook = $this->uploadedWorkbook();
        $service = app(FailureLogImportService::class);

        $service->import($workbook, $unit, false, $user);
        $second = $service->import($workbook, $unit, false, $user);

        $this->assertSame('succeeded', $second['status']);
        $this->assertSame(0, $second['data_updated']);
        $this->assertSame(3, $second['data_unchanged']);
        $this->assertSame(3, $second['duplicates_skipped']);
        $this->assertSame(['Interlocking Elektrik!10', 'LxC!2', 'Reorder Stock!2'], $second['duplicate_locations']);
        $this->assertDatabaseCount('rams_import_batches', 2);
        $this->assertDatabaseCount('failure_logs', 1);
        $this->assertDatabaseCount('risk_registers', 1);
        $this->assertDatabaseCount('spare_parts', 1);
    }

    public function test_web_import_does_not_create_taxonomy_from_reorder_only_paths(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $group = AssetGroup::factory()->create([
            'unit_kerja_id' => $unit->id,
            'name' => '1. Peralatan Dalam Sinyal Elektrik',
        ]);
        $system = AssetSystem::factory()
            ->for($group)
            ->create(['name' => 'Interlocking Elektrik']);
        $subsystem = AssetSubsystem::factory()
            ->for($system)
            ->create(['name' => 'Interlocking Elektrik']);
        Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create(['jumlah_unit' => 2]);
        $user = User::factory()->unit($unit)->create();

        $result = app(FailureLogImportService::class)->import(
            $this->uploadedWorkbook(includeUnmatchedReorderPath: true),
            $unit,
            false,
            $user,
        );

        $this->assertSame('succeeded', $result['status']);
        $this->assertSame(1, $result['spare_parts_created']);
        $this->assertSame(1, $result['spare_parts_skipped']);
        $this->assertDatabaseCount('spare_parts', 1);
        $this->assertFalse(AssetSystem::query()->where('name', 'Panel Pelayanan')->exists());
        $this->assertTrue(
            collect($result['issues'])->contains(
                fn (array $issue): bool => ($issue['sheet_name'] ?? null) === 'Reorder Stock' &&
                    ($issue['source_row'] ?? null) === 3 &&
                    str_contains($issue['message'], 'tidak ditemukan pada master Predictive Data Asset'),
            ),
        );
    }

    private function uploadedWorkbook(bool $includeUnmatchedReorderPath = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rams-upload-').'.xlsx';
        $this->temporaryFiles[] = $path;
        $spreadsheet = new Spreadsheet;
        $dashboard = $spreadsheet->getActiveSheet();
        $dashboard->setTitle('Dashboard');
        $dashboard->setCellValue('W4', 43831);
        $dashboard->setCellValue('R4', 2406);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Interlocking Elektrik');

        foreach (
            [
                'B3' => 'Subsystem',
                'C3' => 'Jumlah Unit',
                'D3' => 'Total Operating Hour',
                'E3' => 'Total Uptime',
                'F3' => 'Total Downtime',
                'G3' => 'Jumlah Failure',
                'H3' => 'MTTF',
                'I3' => 'MTBF',
                'J3' => 'Failure Rate',
                'K3' => 'Reliability',
                'L3' => 'Availability',
                'M3' => 'Jumlah penggantian sparepart',
                'N3' => 'Jumlah Tindak Vandalisme',
            ] as $cell => $value
        ) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->setCellValue('B4', 'Interlocking Elektrik');
        $sheet->setCellValue('C4', 2);
        $sheet->setCellValue('D4', 115488);
        $sheet->setCellValue('E4', 115350);
        $sheet->setCellValue('F4', 138);
        $sheet->setCellValue('G4', 3);
        $sheet->setCellValue('H4', 4626.622222222213);
        $sheet->setCellValue('I4', 38450);
        $sheet->setCellValue('J4', 0.000026007802340702212);
        $sheet->setCellValue('K4', 0.9999739925358593);
        $sheet->setCellValue('L4', 0.9988050706566916);
        $sheet->setCellValue('M4', 0);
        $sheet->setCellValue('N4', 0);
        $sheet->setCellValueExplicit(
            'F5',
            '=SUM(Interlocking_Elektrik_failure[Konversi ke Menit])',
            DataType::TYPE_STRING,
        );
        $sheet->setCellValueExplicit(
            'M5',
            '=COUNTA(Interlocking_Elektrik_failure[Penggantian Sparepart])',
            DataType::TYPE_STRING,
        );

        foreach (
            [
                'C9' => 'Lokasi',
                'D9' => 'Resor',
                'E9' => 'QC',
                'F9' => 'Failure Event',
                'G9' => 'Penyebab',
                'H9' => 'Tindakan',
                'I9' => 'Penggantian Sparepart',
                'J9' => 'Tindak Vandalisme',
                'K9' => 'Tanggal Kejadian',
                'L9' => 'Tanggal Penanganan',
                'M9' => 'Mulai',
                'N9' => 'Selesai',
            ] as $cell => $value
        ) {
            $sheet->setCellValue($cell, $value);
        }

        foreach ([10, 11] as $row) {
            $sheet->setCellValue("C{$row}", 'Jakk');
            $sheet->setCellValue("D{$row}", '1.10 JAKK');
            $sheet->setCellValue("E{$row}", '1.C MRI');
            $sheet->setCellValue("F{$row}", "Gangguan {$row}");
            $sheet->setCellValue("G{$row}", 'Modul rusak');
            $sheet->setCellValue("H{$row}", 'Diganti');
            $sheet->setCellValue("I{$row}", 'N');
            $sheet->setCellValue("J{$row}", 'N');
            $sheet->setCellValue("L{$row}", '09/03/2020');
            $sheet->setCellValue("M{$row}", '13:15');
            $sheet->setCellValue("N{$row}", '14:50');
        }
        $sheet->setCellValue('K10', '09/03/2020');
        $sheet->setCellValueExplicit('K11', '#DIV/0!', DataType::TYPE_ERROR);

        $riskRegister = $spreadsheet->createSheet();
        $riskRegister->setTitle('LxC');
        $riskRegister->fromArray(
            [
                'No',
                'System',
                'Subsystem',
                'Risk Event',
                'Risk Cause',
                'Impact',
                'Part Name',
                'Likelihood',
                'Consequence',
            ],
            null,
            'A1',
        );
        $riskRegister->fromArray(
            [
                1,
                'Sinyal Elektrik',
                'Interlocking Elektrik',
                'Gangguan interlocking',
                'Modul gagal',
                'Perjalanan terganggu',
                'Modul relay',
                2,
                3,
            ],
            null,
            'A2',
        );

        $reorder = $spreadsheet->createSheet();
        $reorder->setTitle('Reorder Stock');
        $reorder->fromArray(
            [
                'System',
                'Sub-System',
                'Equipment',
                'Detail Equipment',
                'Max yearly Failure',
                'Average Yearly Failure',
                'Max Lead Time (Month)',
                'Average Lead Time (Month)',
                'Safety Stock',
                'Lead Time Demand',
                'Reorder Point',
                'Severity Equipment',
            ],
            null,
            'A1',
        );
        $reorder->fromArray(
            [
                'Peralatan Dalam Sinyal Elektrik',
                'Interlocking Elektrik',
                'Interlocking Elektrik',
                'Modul relay',
                4,
                2,
                3,
                2,
                8,
                4,
                12,
                'High',
            ],
            null,
            'A2',
        );
        if ($includeUnmatchedReorderPath) {
            $reorder->fromArray(
                [
                    'Peralatan Dalam Sinyal Elektrik',
                    'Panel Pelayanan',
                    'Panel Pelayanan LCP',
                    'Modul panel',
                    1,
                    1,
                    1,
                    1,
                    0,
                    1,
                    1,
                    'Medium',
                ],
                null,
                'A3',
            );
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'Risk Analysis And Management System RAMS Daop 1.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
