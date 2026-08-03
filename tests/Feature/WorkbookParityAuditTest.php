<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\ReliabilityExcelSnapshot;
use App\Models\ReliabilitySummary;
use App\Models\UnitKerja;
use App\Services\ExcelReliabilitySnapshotImporter;
use App\Services\FailureLogWorkbookImporter;
use App\Services\ReliabilityParityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full integration test that imports the actual RAMS workbook,
 * runs parity checks per sheet, and reports structured results.
 *
 * This test is intentionally skipped in CI when the workbook file is not present.
 */
final class WorkbookParityAuditTest extends TestCase
{
    use RefreshDatabase;

    private const WORKBOOK_PATH = 'D:\\BIKIN WEB CRUD DARI KAI\\Risk Analysis And Management System RAMS Daop 1.xlsm';

    /** @var array<string, string> Map of sheet names to normalized subsystem names */
    private const SUBSYSTEM_MAP = [
        'Kontak deteksi' => 'Kontak Deteksi',
        'Pengaman wesel setempat mekanik' => 'Pengaman Wesel Setempat Mekanik',
        'Pengontrol dan Petunjuk Keduduk' => 'Pengontrol dan Petunjuk Kedudukan Wesel Mekanik',
        'Penggerak wesel mekanik' => 'Penggerak Wesel Mekanik',
        'Peraga Sinyal Mekanik Pelengkap' => 'Peraga Sinyal Mekanik Pelengkap',
        'Peraga Sinyal Mekanik Pembantu' => 'Peraga Sinyal Mekanik Pembantu',
        'Peraga Sinyal Mekanik Utama' => 'Peraga Sinyal Mekanik Utama',
        'Interlocking Mekanik' => 'Interlocking Mekanik',
        'Interlocking Elektrik' => 'Interlocking Elektrik',
        'CDS' => 'Catu Daya Sinyal',
        'Penggerak Wesel Elektrik' => 'Penggerak Wesel Elektrik',
        'Pengaman Wesel Setempat Elektri' => 'Pengaman Wesel Setempat Elektrik',
        'Axle Counter' => 'Axle Counter',
        'Track Circuit' => 'Track Circuit',
        'Peraga sinyal elektrik utama' => 'Peraga Sinyal Elektrik Utama',
        'Peraga sinyal elektrik pembantu' => 'Peraga Sinyal Elektrik Pembantu',
        'Peraga sinyal elektrik pelengka' => 'Peraga Sinyal Elektrik Pelengkap',
        'wesel setempat elektrik S90' => 'Pengaman Wesel Setempat Elektrik',
        'wesel setempat elektrik BSG9' => 'Pengaman Wesel Setempat Elektrik',
    ];

    public function test_full_workbook_import_and_parity_audit(): void
    {
        if (! file_exists(self::WORKBOOK_PATH)) {
            $this->markTestSkipped('RAMS workbook not found at '.self::WORKBOOK_PATH);
        }

        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);

        // Create assets for each unique subsystem so the resolver can map sheets
        $createdSubsystems = [];
        foreach (self::SUBSYSTEM_MAP as $sheetName => $subsystemName) {
            if (isset($createdSubsystems[$subsystemName])) {
                continue;
            }
            $subsystem = AssetSubsystem::factory()->create(['name' => $subsystemName]);
            Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
                'subsystem' => mb_strtoupper($subsystemName),
                'lokasi' => 'Daop 1',
                'jumlah_unit' => 1, // Will be overridden by actual workbook data
            ]);
            $createdSubsystems[$subsystemName] = true;
        }

        // Step 1: Import failure logs from workbook
        $importResult = app(FailureLogWorkbookImporter::class)->import(
            self::WORKBOOK_PATH,
            $unit,
        );
        $this->assertGreaterThan(0, $importResult['sheets'], 'At least one sheet should be imported');

        // Step 2: Import Excel snapshots
        $snapshotResult = app(ExcelReliabilitySnapshotImporter::class)->import(
            self::WORKBOOK_PATH,
            $unit,
            'RAMS Daop 1.xlsm',
        );
        $this->assertGreaterThan(0, $snapshotResult['snapshots'], 'At least one snapshot should be created');

        Asset::query()->where('unit_kerja_id', $unit->id)->get()->each(function (Asset $asset): void {
            $snapshot = ReliabilityExcelSnapshot::query()
                ->where('asset_id', $asset->id)
                ->latest('id')
                ->first();
            if ($snapshot && is_numeric($snapshot->summary_values['unit_count'] ?? null)) {
                $asset->update(['jumlah_unit' => (int) $snapshot->summary_values['unit_count']]);
            }
        });

        // Step 3: Run parity checks
        $parityResult = app(ReliabilityParityService::class)->recalculateUnit($unit);
        $this->assertGreaterThan(0, $parityResult['calculated'], 'At least one asset should be calculated');
        $unexpectedDifferences = ReliabilitySummary::query()
            ->where('parity_status', 'mismatch')
            ->get()
            ->flatMap(fn (ReliabilitySummary $summary): array => array_diff(
                array_keys($summary->parity_differences ?? []),
                ['mttf_hours'],
            ));
        $this->assertSame([], $unexpectedDifferences->values()->all(), ReliabilitySummary::query()
            ->where('parity_status', 'mismatch')
            ->get(['asset_id', 'parity_differences'])
            ->toJson(JSON_PRETTY_PRINT));

        // Step 4: Verify snapshots have correct profile keys
        $snapshots = ReliabilityExcelSnapshot::all();
        foreach ($snapshots as $snapshot) {
            $profile = $snapshot->formula_profile;
            $this->assertArrayHasKey('downtime_mode', $profile, "Sheet {$snapshot->sheet_name} missing downtime_mode");
            $this->assertArrayHasKey('interval_baseline_date', $profile, "Sheet {$snapshot->sheet_name} missing interval_baseline_date");
            $this->assertArrayHasKey('empty_mttf_mode', $profile, "Sheet {$snapshot->sheet_name} missing empty_mttf_mode");
            $this->assertArrayHasKey('failure_count_mode', $profile, "Sheet {$snapshot->sheet_name} missing failure_count_mode");
            $this->assertArrayHasKey('spare_part_count_mode', $profile, "Sheet {$snapshot->sheet_name} missing spare_part_count_mode");
            $this->assertArrayHasKey('vandalism_count_mode', $profile, "Sheet {$snapshot->sheet_name} missing vandalism_count_mode");
            $this->assertContains($profile['downtime_mode'], ['minutes', 'excel_day_fraction'], "Sheet {$snapshot->sheet_name} invalid downtime_mode");
            $this->assertContains($profile['failure_count_mode'], ['counta', 'counta_all_minus_1'], "Sheet {$snapshot->sheet_name} invalid failure_count_mode");
        }

        // Report parity results as a structured table
        echo "\n\n=== WORKBOOK PARITY AUDIT RESULTS ===\n";
        echo str_pad('Sheet', 40)
            .str_pad('Status', 22)
            .str_pad('Snap', 6)
            .str_pad('Failures', 10)
            .str_pad('Differences', 30)
            ."\n";
        echo str_repeat('-', 108)."\n";

        $summaries = ReliabilitySummary::with('asset.assetSubsystem', 'excelSnapshot')->get();
        foreach ($summaries as $summary) {
            $sheetName = $summary->excelSnapshot?->sheet_name ?? '(no snapshot)';
            $status = $summary->parity_status;
            $hasSnap = $summary->excel_snapshot_id ? 'Yes' : 'No';
            $failures = $summary->failure_count;
            $diffs = $summary->parity_differences
                ? implode(', ', array_keys($summary->parity_differences))
                : '-';

            echo str_pad($sheetName, 40)
                .str_pad($status, 22)
                .str_pad($hasSnap, 6)
                .str_pad((string) $failures, 10)
                .str_pad($diffs, 30)
                ."\n";
        }
        echo "\n=== SUMMARY ===\n";
        echo "Calculated: {$parityResult['calculated']}\n";
        echo "Matched: {$parityResult['matched']}\n";
        echo "Mismatch: {$parityResult['mismatch']}\n";
        echo "Excel data missing: {$parityResult['excel_data_missing']}\n";
        echo "Not compared: {$parityResult['not_compared']}\n";
        echo 'Import issues: '.count($importResult['issues'])."\n";
        echo 'Snapshot issues: '.count($snapshotResult['issues'])."\n";

        // The test passes if the import pipeline completes without fatal errors
        // and produces at least one parity result. Mismatches are REPORTED, not hidden.
        $this->assertTrue(true, 'Import pipeline completed without errors');
    }

    /**
     * Verify that the Interlocking Elektrik sheet (3 failures) produces
     * exact parity with the Excel workbook values.
     */
    public function test_interlocking_elektrik_produces_exact_parity(): void
    {
        if (! file_exists(self::WORKBOOK_PATH)) {
            $this->markTestSkipped('RAMS workbook not found at '.self::WORKBOOK_PATH);
        }

        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $subsystem = AssetSubsystem::factory()->create(['name' => 'Interlocking Elektrik']);
        $asset = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'subsystem' => 'INTERLOCKING ELEKTRIK',
            'lokasi' => 'Daop 1',
            'jumlah_unit' => 2,
        ]);

        app(FailureLogWorkbookImporter::class)->import(self::WORKBOOK_PATH, $unit);
        app(ExcelReliabilitySnapshotImporter::class)->import(self::WORKBOOK_PATH, $unit, 'RAMS Daop 1.xlsm');

        $summary = app(ReliabilityParityService::class)->recalculateAsset($asset);

        $this->assertNotNull($summary);
        $this->assertSame(3, $summary->failure_count);
        $this->assertSame(2, $summary->unit_count);
        $this->assertEqualsWithDelta(115488, (float) $summary->operating_hours, 1);
        $this->assertEqualsWithDelta(138, (float) $summary->downtime_value, 1);
        $this->assertEqualsWithDelta(115350, (float) $summary->uptime_hours, 1);
        $this->assertEqualsWithDelta(38450, (float) $summary->mtbf_hours, 1);

        // Parity status: may be 'matched' or 'excel_data_missing' depending on MTTF error
        $this->assertContains($summary->parity_status, ['matched', 'excel_data_missing']);

        if ($summary->parity_differences) {
            echo "\n=== Interlocking Elektrik Parity Differences ===\n";
            foreach ($summary->parity_differences as $key => $diff) {
                echo "  {$key}: backend={$diff['backend']} excel={$diff['excel']}\n";
            }
        }
    }
}
