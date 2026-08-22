<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\FailureLog;
use App\Models\ReliabilitySummary;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ReliabilityWorkbookExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ReliabilityWorkbookExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_formula_driven_workbook_for_every_subsystem_in_the_selected_unit(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $user = User::factory()
            ->unit($unit)
            ->create([
                'name' => 'Operator Rahasia',
                'email' => 'operator-rahasia@example.test',
            ]);

        $interlocking = $this->asset($unit, 'Interlocking Elektrik', 2, '2017-01-01');
        $catuDaya = $this->asset($unit, 'Catu Daya Sintel', 3, '2017-01-01');
        $otherAsset = $this->asset($otherUnit, 'RAHASIA DAOP-4', 99, '2017-01-01');

        ReliabilitySummary::factory()
            ->for($interlocking)
            ->create([
                'baseline_date' => '2017-01-01',
                'calculation_date' => '2026-08-08',
                'calculation_profile' => [
                    'downtime_mode' => 'minutes',
                    'interval_baseline_date' => '2020-01-01',
                    'failure_count_mode' => 'counta_all_minus_1',
                    'spare_part_count_mode' => 'counta',
                    'vandalism_count_mode' => 'counta',
                ],
                'formula_version' => 'kai-rams-excel-parity-v1.1.0',
            ]);
        ReliabilitySummary::factory()
            ->for($catuDaya)
            ->create([
                'baseline_date' => '2017-01-01',
                'calculation_date' => '2026-08-08',
                'calculation_profile' => [
                    'downtime_mode' => 'hours',
                    'interval_baseline_date' => '2017-01-01',
                    'failure_count_mode' => 'counta',
                    'spare_part_count_mode' => 'countif_ya',
                    'vandalism_count_mode' => 'countif_ya',
                    'failure_interval_row_count' => 4,
                ],
            ]);
        ReliabilitySummary::factory()->for($otherAsset)->create();

        $this->failure($interlocking, $user, 8, '2026-01-10 08:00:00', '2026-01-10 10:00:00', true, false);
        $this->failure($interlocking, $user, 9, '2026-02-12 23:30:00', '2026-02-13 01:00:00', false, true);
        $this->failure($otherAsset, $user, 8, '2026-01-01 08:00:00', '2026-01-01 09:00:00', false, false);

        $workbook = app(ReliabilityWorkbookExportService::class)->workbook($user, $unit);
        $sheetNames = array_map(static fn ($sheet): string => $sheet->getTitle(), $workbook->getAllSheets());

        $this->assertSame('Ringkasan Reliability', $sheetNames[0]);
        $this->assertContains('Interlocking Elektrik', $sheetNames);
        $this->assertContains('Catu Daya Sintel', $sheetNames);
        $this->assertNotContains('RAHASIA DAOP-4', $sheetNames);

        $summary = $workbook->getSheetByName('Ringkasan Reliability');
        $electric = $workbook->getSheetByName('Interlocking Elektrik');
        $power = $workbook->getSheetByName('Catu Daya Sintel');

        $this->assertNotNull($summary);
        $this->assertNotNull($electric);
        $this->assertNotNull($power);
        $this->assertSame("='Interlocking Elektrik'!B4", $summary->getCell('A5')->getValue());
        $this->assertSame("='Interlocking Elektrik'!K4", $summary->getCell('J5')->getValue());

        $this->assertSame('=(TODAY()-$T$8)*24*C4', $electric->getCell('D4')->getValue());
        $this->assertSame('=TODAY()', $electric->getCell('Q8')->getValue());
        $this->assertSame(42736.0, $electric->getCell('T8')->getValue());
        $this->assertSame('=D4-F4', $electric->getCell('E4')->getValue());
        $this->assertSame('=SUM(R10:R11)', $electric->getCell('F4')->getValue());
        $this->assertSame('=COUNTA(E10:E11)', $electric->getCell('G4')->getValue());
        $this->assertSame('=IFERROR(AVERAGE(S10:S11),"Data belum cukup")', $electric->getCell('H4')->getValue());
        $this->assertSame('=IFERROR(E4/G4,0)', $electric->getCell('I4')->getValue());
        $this->assertSame('=IFERROR(1/I4,0)', $electric->getCell('J4')->getValue());
        $this->assertSame('=EXP(-J4)', $electric->getCell('K4')->getValue());
        $this->assertSame('=IFERROR(E4/D4,"Data belum cukup")', $electric->getCell('L4')->getValue());
        $this->assertSame('General', $electric->getStyle('D4')->getNumberFormat()->getFormatCode());
        $this->assertSame('General', $electric->getStyle('H4')->getNumberFormat()->getFormatCode());
        $this->assertSame('0.0000000000', $electric->getStyle('J4')->getNumberFormat()->getFormatCode());
        $this->assertSame('0.0000%', $electric->getStyle('K4')->getNumberFormat()->getFormatCode());
        $this->assertSame('0.0000%', $summary->getStyle('J5')->getNumberFormat()->getFormatCode());
        $this->assertSame('0.0000000000', $summary->getStyle('I5')->getNumberFormat()->getFormatCode());
        $this->assertSame('=COUNTA(H10:H11)', $electric->getCell('M4')->getValue());
        $this->assertSame('=COUNTA(I10:I11)', $electric->getCell('N4')->getValue());
        $this->assertSame('Ya', $electric->getCell('H10')->getValue());
        $this->assertNull($electric->getCell('H11')->getValue());
        $this->assertNull($electric->getCell('I10')->getValue());
        $this->assertSame('Ya', $electric->getCell('I11')->getValue());
        $this->assertSame(1, $electric->getCell('M4')->getCalculatedValue());
        $this->assertSame(1, $electric->getCell('N4')->getCalculatedValue());
        $this->assertSame('=IFERROR(IF(O10="",0,(O10-$P$8)*24),"")', $electric->getCell('S10')->getValue());
        $this->assertSame('=IFERROR(IF(O11="",0,(O11-O10)*24),"")', $electric->getCell('S11')->getValue());

        $this->assertSame('=SUM(Q10:Q13)*24', $power->getCell('F4')->getValue());
        $this->assertSame('=IFERROR(AVERAGE(S10:S13),"Data belum cukup")', $power->getCell('H4')->getValue());
        $this->assertSame('=COUNTIF(H10:H13,"Ya")', $power->getCell('M4')->getValue());
        $this->assertSame('=COUNTIF(I10:I13,"Ya")', $power->getCell('N4')->getValue());
        $this->assertSame('=IFERROR(IF(O13="",0,(O13-O12)*24),"")', $power->getCell('S13')->getValue());
        $this->assertNull($power->getCell('E10')->getValue());

        $contents = collect($workbook->getAllSheets())
            ->flatMap(static fn ($sheet): array => $sheet->toArray())
            ->flatten()
            ->filter(static fn (mixed $value): bool => is_scalar($value))
            ->implode(' ');

        $this->assertStringNotContainsString('RAHASIA DAOP-4', $contents);
        $this->assertStringNotContainsString('Operator Rahasia', $contents);
        $this->assertStringNotContainsString('operator-rahasia@example.test', $contents);

        $workbook->disconnectWorksheets();
    }

    public function test_workbook_survives_xlsx_round_trip_without_broken_formula_references(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $user = User::factory()->unit($unit)->create();
        $asset = $this->asset(
            $unit,
            'Pengontrol/Petunjuk [Wesel]: Mekanik? Dengan Nama Sangat Panjang',
            1,
            '2017-01-01',
        );
        ReliabilitySummary::factory()
            ->for($asset)
            ->create([
                'baseline_date' => '2017-01-01',
                'calculation_date' => '2026-08-08',
                'calculation_profile' => [],
            ]);

        $workbook = app(ReliabilityWorkbookExportService::class)->workbook($user, $unit);
        $path = tempnam(sys_get_temp_dir(), 'reliability-formula-').'.xlsx';

        try {
            (new Xlsx($workbook))->save($path);
            $reloaded = IOFactory::load($path);

            foreach ($reloaded->getWorksheetIterator() as $worksheet) {
                $this->assertLessThanOrEqual(31, mb_strlen($worksheet->getTitle()));
                foreach ($worksheet->getCellCollection()->getCoordinates() as $coordinate) {
                    $value = $worksheet->getCell($coordinate)->getValue();
                    if (is_string($value) && str_starts_with($value, '=')) {
                        $this->assertStringNotContainsString('#REF!', $value);
                    }
                }
            }

            $reloaded->disconnectWorksheets();
        } finally {
            $workbook->disconnectWorksheets();
            @unlink($path);
        }
    }

    private function asset(UnitKerja $unit, string $subsystemName, int $unitCount, string $installedAt): Asset
    {
        $subsystem = AssetSubsystem::factory()->create(['name' => $subsystemName]);

        return Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'nama_aset' => $subsystemName,
                'subsystem' => mb_strtoupper($subsystemName),
                'jumlah_unit' => $unitCount,
                'tanggal_pemasangan' => $installedAt,
            ]);
    }

    private function failure(
        Asset $asset,
        User $creator,
        int $sourceRow,
        string $startedAt,
        string $resolvedAt,
        bool $sparePart,
        bool $vandalism,
    ): FailureLog {
        return FailureLog::factory()
            ->for($asset)
            ->for($creator, 'creator')
            ->create([
                'source_row' => $sourceRow,
                'location' => 'Stasiun Uji',
                'resort' => 'Resor 1.1',
                'qc' => 'QC',
                'failure_event' => "Gangguan {$sourceRow}",
                'cause' => 'Penyebab uji',
                'action_taken' => 'Tindakan uji',
                'started_at' => $startedAt,
                'resolved_at' => $resolvedAt,
                'downtime_minutes' => 120,
                'spare_part_replaced' => $sparePart,
                'spare_part_marker' => $sparePart ? 'Ya' : null,
                'vandalism' => $vandalism,
                'vandalism_marker' => $vandalism ? 'Ya' : null,
            ]);
    }
}
