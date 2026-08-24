<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\FailureLog;
use App\Models\UnitKerja;
use App\Services\FailureLogWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class FailureLogWorkbookImporterTest extends TestCase
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

    public function test_it_imports_detail_rows_without_recalculating_reliability(): void
    {
        [$unit, $asset] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook([
            $this->validRow(),
            $this->validRow(['location' => 'Jakk - Gmr', 'failure_event' => 'Gangguan kedua']),
        ]);

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['sheets']);
        $this->assertDatabaseCount('failure_logs', 2);
        $this->assertDatabaseCount('reliability_summaries', 0);
        $this->assertSame($asset->id, FailureLog::query()->firstOrFail()->asset_id);
        $this->assertSame(95, FailureLog::query()->firstOrFail()->downtime_minutes);
        $this->assertSame('Interlocking Elektrik', FailureLog::query()->firstOrFail()->sheet_name);
        $this->assertSame(10, FailureLog::query()->firstOrFail()->source_row);
        $this->assertSame('N', FailureLog::query()->firstOrFail()->spare_part_marker);
    }

    public function test_it_records_bad_rows_and_unmapped_sheets_without_stopping_other_rows(): void
    {
        [$unit] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook(
            [$this->validRow(), $this->validRow(['event_date' => '#VALUE!']), $this->validRow(['failure_event' => ''])],
            includeUnknownSheet: true,
        );

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['skipped']);
        $this->assertCount(3, $result['issues']);
        $this->assertSame(
            ['Interlocking Elektrik', 'Interlocking Elektrik', 'Subsystem Tidak Terdaftar'],
            array_column($result['issues'], 'sheet_name'),
        );
        $this->assertDatabaseCount('failure_logs', 1);
    }

    public function test_reimport_updates_the_same_source_row_instead_of_creating_a_duplicate(): void
    {
        [$unit] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook([$this->validRow()]);
        $importer = app(FailureLogWorkbookImporter::class);

        $first = $importer->import($path, $unit);
        $second = $importer->import($path, $unit);
        $this->rewriteWorkbook($path, [$this->validRow(['action' => 'Mengganti modul dan menguji ulang'])]);
        $third = $importer->import($path, $unit);

        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(1, $second['duplicates_skipped']);
        $this->assertSame(['Interlocking Elektrik!10'], $second['duplicate_locations']);
        $this->assertSame(1, $third['updated']);
        $this->assertDatabaseCount('failure_logs', 1);
        $this->assertSame('Mengganti modul dan menguji ulang', FailureLog::query()->sole()->action_taken);
    }

    public function test_it_adopts_an_identical_manual_row_instead_of_creating_a_duplicate(): void
    {
        [$unit, $asset] = $this->assetContext('Interlocking Elektrik');
        $row = $this->validRow();
        FailureLog::factory()
            ->for($asset)
            ->create([
                'source_key' => null,
                'location' => $row['location'],
                'resort' => $row['resort'],
                'qc' => $row['qc'],
                'failure_event' => $row['failure_event'],
                'cause' => $row['cause'],
                'action_taken' => $row['action'],
                'started_at' => '2026-08-03 00:00:00',
                'resolved_at' => '2026-08-03 00:00:00',
            ]);
        $path = $this->workbook([$row]);

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $failure = FailureLog::query()->sole();
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertNotNull($failure->source_key);
        $this->assertSame('Interlocking Elektrik', $failure->sheet_name);
        $this->assertSame('2020-03-09 13:15:00', $failure->started_at->format('Y-m-d H:i:s'));
    }

    public function test_blank_text_cells_are_imported_as_dashes_instead_of_dropping_the_row(): void
    {
        [$unit] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook([
            $this->validRow([
                'location' => '',
                'resort' => '',
                'qc' => '',
                'cause' => '',
                'action' => '',
                'spare_part' => '',
                'vandalism' => '',
            ]),
        ]);

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $failure = FailureLog::query()->sole();
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame('-', $failure->location);
        $this->assertSame('-', $failure->resort);
        $this->assertSame('-', $failure->qc);
        $this->assertSame('-', $failure->cause);
        $this->assertSame('-', $failure->action_taken);
        $this->assertSame('-', $failure->spare_part_marker);
        $this->assertSame('-', $failure->vandalism_marker);
    }

    public function test_two_rows_with_the_same_operational_identity_are_both_rejected_as_conflicts(): void
    {
        [$unit] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook([
            $this->validRow(),
            $this->validRow(['action' => 'Tindakan berbeda pada identitas kejadian yang sama']),
        ]);

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['duplicates_skipped']);
        $this->assertSame(['Interlocking Elektrik!10', 'Interlocking Elektrik!11'], $result['duplicate_locations']);
        $this->assertDatabaseCount('failure_logs', 0);
        $this->assertCount(
            2,
            array_filter(
                $result['issues'],
                fn (array $issue): bool => $issue['severity'] === 'error' &&
                    str_contains($issue['message'], 'identitas operasional yang sama'),
            ),
        );
    }

    public function test_it_accepts_combined_datetime_columns_when_split_columns_are_absent(): void
    {
        [$unit] = $this->assetContext('Interlocking Elektrik');
        $path = $this->combinedDateTimeWorkbook();

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(1, $result['created']);
        $this->assertSame('2020-03-09 13:15:00', FailureLog::query()->sole()->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(95, FailureLog::query()->sole()->downtime_minutes);
    }

    public function test_it_uses_excel_time_formula_when_legacy_handled_date_is_before_event_date(): void
    {
        [$unit] = $this->assetContext('Interlocking Elektrik');
        $path = $this->workbook([$this->validRow(['handled_date' => '09/03/2017'])]);

        $result = app(FailureLogWorkbookImporter::class)->import($path, $unit);

        $failure = FailureLog::query()->sole();
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(95, $failure->downtime_minutes);
        $this->assertSame('2020-03-09 14:50:00', $failure->resolved_at->format('Y-m-d H:i:s'));
        $this->assertCount(1, $result['issues']);
        $this->assertStringContainsString('tanggal kejadian', mb_strtolower($result['issues'][0]['message']));
    }

    /** @return array{UnitKerja, Asset} */
    private function assetContext(string $subsystemName): array
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $subsystem = AssetSubsystem::factory()->create(['name' => $subsystemName]);
        $asset = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'subsystem' => mb_strtoupper($subsystemName),
                'jumlah_unit' => 2,
            ]);

        return [$unit, $asset];
    }

    /** @param list<array<string, string>> $rows */
    private function workbook(array $rows, bool $includeUnknownSheet = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rams-failure-').'.xlsx';
        $this->temporaryFiles[] = $path;
        $this->rewriteWorkbook($path, $rows, $includeUnknownSheet);

        return $path;
    }

    /** @param list<array<string, string>> $rows */
    private function rewriteWorkbook(string $path, array $rows, bool $includeUnknownSheet = false): void
    {
        $spreadsheet = new Spreadsheet;
        $dashboard = $spreadsheet->getActiveSheet();
        $dashboard->setTitle('Dashboard');
        $dashboard->setCellValue('B2', 'Tabel ringkasan yang harus dilewati');
        $this->addDetailSheet($spreadsheet, 'Interlocking Elektrik', $rows);

        if ($includeUnknownSheet) {
            $this->addDetailSheet($spreadsheet, 'Subsystem Tidak Terdaftar', [
                $this->validRow(['failure_event' => 'Gangguan sheet tanpa aset']),
            ]);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function combinedDateTimeWorkbook(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rams-failure-combined-').'.xlsx';
        $this->temporaryFiles[] = $path;
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Interlocking Elektrik');
        $sheet->setCellValue('B4', 'Interlocking Elektrik');

        foreach (
            [
                'C9' => 'Lokasi',
                'D9' => 'Failure Event',
                'E9' => 'Penyebab',
                'F9' => 'Tindakan',
                'G9' => 'Tanggal Jam Kejadian',
                'H9' => 'Tanggal Jam Penanganan',
            ] as $cell => $header
        ) {
            $sheet->setCellValue($cell, $header);
        }
        $sheet->setCellValue('C10', 'Jakk');
        $sheet->setCellValue('D10', 'Gangguan dengan tanggal gabungan');
        $sheet->setCellValue('E10', 'Modul rusak');
        $sheet->setCellValue('F10', 'Diganti');
        $sheet->setCellValue('G10', '2020-03-09 13:15:00');
        $sheet->setCellValue('H10', '2020-03-09 14:50:00');

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /** @param list<array<string, string>> $rows */
    private function addDetailSheet(Spreadsheet $spreadsheet, string $name, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($name);
        $sheet->setCellValue('B4', $name);
        $headers = [
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
        ];

        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        foreach ($rows as $offset => $row) {
            $excelRow = 10 + $offset;
            $sheet->setCellValue("C{$excelRow}", $row['location']);
            $sheet->setCellValue("D{$excelRow}", $row['resort']);
            $sheet->setCellValue("E{$excelRow}", $row['qc']);
            $sheet->setCellValue("F{$excelRow}", $row['failure_event']);
            $sheet->setCellValue("G{$excelRow}", $row['cause']);
            $sheet->setCellValue("H{$excelRow}", $row['action']);
            $sheet->setCellValue("I{$excelRow}", $row['spare_part']);
            $sheet->setCellValue("J{$excelRow}", $row['vandalism']);
            if ($row['event_date'] === '#VALUE!') {
                $sheet->setCellValueExplicit("K{$excelRow}", '#VALUE!', DataType::TYPE_ERROR);
            } else {
                $sheet->setCellValue("K{$excelRow}", $row['event_date']);
            }
            $sheet->setCellValue("L{$excelRow}", $row['handled_date']);
            $sheet->setCellValue("M{$excelRow}", $row['start_time']);
            $sheet->setCellValue("N{$excelRow}", $row['end_time']);
        }
    }

    /** @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function validRow(array $overrides = []): array
    {
        return [
            ...[
                'location' => 'Jakk',
                'resort' => '1.10 JAKK',
                'qc' => '1.C MRI',
                'failure_event' => 'Sinyal masuk mengalami gangguan',
                'cause' => 'Signal module interface rusak',
                'action' => 'Diganti',
                'spare_part' => 'N',
                'vandalism' => 'N',
                'event_date' => '09/03/2020',
                'handled_date' => '09/03/2020',
                'start_time' => '13:15',
                'end_time' => '14:50',
            ],
            ...$overrides,
        ];
    }
}
