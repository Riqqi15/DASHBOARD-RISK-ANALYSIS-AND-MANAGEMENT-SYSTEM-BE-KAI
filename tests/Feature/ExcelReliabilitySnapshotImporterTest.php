<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\UnitKerja;
use App\Services\ExcelReliabilitySnapshotImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ExcelReliabilitySnapshotImporterTest extends TestCase
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

    public function test_it_stores_summary_values_formulas_errors_and_profile_per_sheet(): void
    {
        [$unit, $asset] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook();

        $result = app(ExcelReliabilitySnapshotImporter::class)->import($path, $unit, 'RAMS Daop 1.xlsm');

        $snapshot = ReliabilityExcelSnapshot::query()->sole();
        $this->assertSame(1, $result['snapshots']);
        $this->assertSame($asset->id, $snapshot->asset_id);
        $this->assertSame('Interlocking Elektrik', $snapshot->sheet_name);
        $this->assertSame('2020-01-01', $snapshot->baseline_date->toDateString());
        $this->assertSame('2026-08-06', $snapshot->calculation_date->toDateString());
        $this->assertEqualsWithDelta(115632, $snapshot->summary_values['operating_hours'], 0.0001);
        $this->assertNull($snapshot->summary_values['mttf_hours']);
        $this->assertSame('#DIV/0!', $snapshot->summary_errors['mttf_hours']);
        $this->assertSame('=Dashboard!R4*24*[@[Jumlah Unit]]', $snapshot->summary_formulas['operating_hours']);
        $this->assertSame('minutes', $snapshot->formula_profile['downtime_mode']);
        $this->assertSame('2017-01-01', $snapshot->formula_profile['interval_baseline_date']);
        $this->assertSame('counta_all_minus_1', $snapshot->formula_profile['failure_count_mode']);
        $this->assertSame('counta', $snapshot->formula_profile['spare_part_count_mode']);
        $this->assertArrayHasKey('failure_interval_row_count', $snapshot->formula_profile);
        $this->assertNull($snapshot->formula_profile['failure_interval_row_count']);
    }

    /** @return array{UnitKerja, Asset} */
    private function assetContext(string $subsystemName): array
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $subsystem = AssetSubsystem::factory()->create(['name' => $subsystemName]);
        $asset = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'subsystem' => mb_strtoupper($subsystemName),
            'lokasi' => 'Daop 1',
            'jumlah_unit' => 2,
        ]);

        return [$unit, $asset];
    }

    private function workbook(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rams-snapshot-').'.xlsx';
        $this->temporaryFiles[] = $path;
        $spreadsheet = new Spreadsheet;
        $dashboard = $spreadsheet->getActiveSheet();
        $dashboard->setTitle('Dashboard');
        $dashboard->setCellValue('W4', 43831);
        $dashboard->setCellValue('R4', '=DATE(2026,8,6)-W4');

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Interlocking Elektrik');
        $sheet->setCellValue('P8', 42736);
        foreach ([
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
        ] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->setCellValue('B4', 'Interlocking Elektrik');
        $sheet->setCellValue('C4', 2);
        $sheet->setCellValue('D4', '=Dashboard!R4*24*C4');
        $sheet->setCellValue('E4', '=D4-F4');
        $sheet->setCellValue('F4', 138);
        $sheet->setCellValue('G4', 3);
        $sheet->setCellValueExplicit('H4', '#DIV/0!', DataType::TYPE_ERROR);
        $sheet->setCellValue('I4', 38450);
        $sheet->setCellValue('J4', 0.000026007802340702212);
        $sheet->setCellValue('K4', 0.9999739925358593);
        $sheet->setCellValue('L4', 0.9988050706566916);
        $sheet->setCellValue('M4', 0);
        $sheet->setCellValue('N4', 0);
        $sheet->setCellValueExplicit('D5', '=Dashboard!R4*24*[@[Jumlah Unit]]', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F5', '=SUM(Interlocking_Elektrik_failure[Konversi ke Menit])', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('G5', '=COUNTA(Interlocking_Elektrik_failure[[#All],[Failure Event]])-1', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('M5', '=COUNTA(Interlocking_Elektrik_failure[Penggantian Sparepart])', DataType::TYPE_STRING);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
