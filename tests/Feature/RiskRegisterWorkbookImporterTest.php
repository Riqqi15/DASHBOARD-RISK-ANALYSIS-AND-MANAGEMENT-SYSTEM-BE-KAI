<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\RiskRegister;
use App\Models\UnitKerja;
use App\Services\RiskRegisterWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class RiskRegisterWorkbookImporterTest extends TestCase
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

    public function test_new_workbook_version_updates_the_same_source_position_without_duplication(): void
    {
        [$unit] = $this->assetContext();
        $path = $this->workbook([$this->validRow()]);
        $importer = app(RiskRegisterWorkbookImporter::class);

        $first = $importer->import($path, $unit);
        $this->rewriteWorkbook($path, [$this->validRow(['cause' => 'Penyebab versi terbaru'])]);
        $second = $importer->import($path, $unit);

        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('risk_registers', 1);
        $this->assertSame('Penyebab versi terbaru', RiskRegister::query()->sole()->risk_cause);
    }

    public function test_identical_reimport_is_not_written_again_and_reports_duplicate_location(): void
    {
        [$unit] = $this->assetContext();
        $path = $this->workbook([$this->validRow()]);
        $importer = app(RiskRegisterWorkbookImporter::class);

        $importer->import($path, $unit);
        $before = RiskRegister::query()->sole()->updated_at;
        $result = $importer->import($path, $unit);

        $this->assertSame(1, $result['unchanged']);
        $this->assertSame(1, $result['duplicates_skipped']);
        $this->assertSame(['LxC!2'], $result['duplicate_locations']);
        $this->assertTrue($before->equalTo(RiskRegister::query()->sole()->updated_at));
    }

    public function test_blank_risk_text_is_stored_as_dash(): void
    {
        [$unit] = $this->assetContext();
        $path = $this->workbook([
            $this->validRow([
                'cause' => '',
                'impact' => '',
                'part_name' => '',
            ]),
        ]);

        $result = app(RiskRegisterWorkbookImporter::class)->import($path, $unit);

        $register = RiskRegister::query()->sole();
        $this->assertSame(1, $result['created']);
        $this->assertSame('-', $register->risk_cause);
        $this->assertSame('-', $register->impact);
        $this->assertSame('-', $register->part_name);
    }

    public function test_duplicate_identity_inside_one_workbook_rejects_both_rows(): void
    {
        [$unit] = $this->assetContext();
        $path = $this->workbook([
            $this->validRow(),
            $this->validRow(['cause' => 'Isi berbeda tetapi identitas risiko sama']),
        ]);

        $result = app(RiskRegisterWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['duplicates_skipped']);
        $this->assertSame(['LxC!2', 'LxC!3'], $result['duplicate_locations']);
        $this->assertDatabaseCount('risk_registers', 0);
    }

    public function test_asset_without_legacy_subsystem_does_not_break_standard_asset_matching(): void
    {
        [$unit] = $this->assetContext();
        Asset::factory()->for($unit)->create([
            'asset_subsystem_id' => null,
            'nama_aset' => 'testing',
            'subsystem' => '',
        ]);
        $path = $this->workbook([$this->validRow()]);

        $result = app(RiskRegisterWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseCount('risk_registers', 1);
    }

    /** @return array{UnitKerja, Asset} */
    private function assetContext(): array
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $subsystem = AssetSubsystem::factory()->create(['name' => 'Interlocking Elektrik']);
        $asset = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'subsystem' => 'INTERLOCKING ELEKTRIK',
            ]);

        return [$unit, $asset];
    }

    /** @param list<array<string, mixed>> $rows */
    private function workbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rams-risk-register-').'.xlsx';
        $this->temporaryFiles[] = $path;
        $this->rewriteWorkbook($path, $rows);

        return $path;
    }

    /** @param list<array<string, mixed>> $rows */
    private function rewriteWorkbook(string $path, array $rows): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('LxC');
        $sheet->fromArray([
            'Kelompok',
            'Aset',
            'Sub',
            'Risk Event',
            'Cause',
            'Impact',
            'Part',
            'Likelihood',
            'Consequence',
        ]);

        foreach ($rows as $offset => $row) {
            $sheet->fromArray(
                [
                    'PDSE',
                    $row['asset'],
                    $row['sub'],
                    $row['event'],
                    $row['cause'],
                    $row['impact'],
                    $row['part_name'],
                    $row['likelihood'],
                    $row['consequence'],
                ],
                null,
                'A'.(2 + $offset),
            );
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRow(array $overrides = []): array
    {
        return [
            ...[
                'asset' => 'Interlocking Elektrik',
                'sub' => 'Interlocking Elektrik',
                'event' => 'Gangguan interlocking',
                'cause' => 'Modul rusak',
                'impact' => 'Operasi terganggu',
                'part_name' => 'Signal module',
                'likelihood' => 2,
                'consequence' => 3,
            ],
            ...$overrides,
        ];
    }
}
