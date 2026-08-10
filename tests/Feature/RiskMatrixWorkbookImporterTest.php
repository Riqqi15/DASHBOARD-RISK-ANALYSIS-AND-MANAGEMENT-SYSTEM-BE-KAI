<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\RiskMatrix;
use App\Models\UnitKerja;
use App\Services\RiskMatrixWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class RiskMatrixWorkbookImporterTest extends TestCase
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

    public function test_it_imports_direct_risk_values_and_excel_colors_without_overwriting_manual_color(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $group = AssetGroup::factory()->create([
            'unit_kerja_id' => $unit->id,
            'name' => '5. CATU DAYA SINTEL',
            'dashboard_color' => '#123456',
            'dashboard_color_source' => 'manual',
        ]);
        $system = AssetSystem::factory()->create(['asset_group_id' => $group->id, 'name' => 'CATU DAYA SINYAL']);
        $subsystem = AssetSubsystem::factory()->create(['asset_system_id' => $system->id, 'name' => 'CATU DAYA SINYAL']);
        $asset = Asset::factory()->create([
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $subsystem->id,
        ]);
        $path = $this->workbook();

        $summary = app(RiskMatrixWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(2, $summary['colors_updated']);
        $this->assertSame('#123456', $group->fresh()->dashboard_color);
        $this->assertSame('#FF0000', $system->fresh()->dashboard_color);
        $this->assertSame('excel', $system->fresh()->dashboard_color_source);
        $this->assertSame('#FF0000', $subsystem->fresh()->dashboard_color);

        $risk = RiskMatrix::query()->where('asset_id', $asset->id)->sole();
        $this->assertSame(1, $risk->likelihood);
        $this->assertSame(4, $risk->consequence);
        $this->assertSame(4, $risk->rating);
        $this->assertSame('High', $risk->level);
        $this->assertSame('matched', $risk->parity_status);
        $this->assertSame('Risk Matrix', $risk->sheet_name);
    }

    public function test_reimport_uses_a_stable_source_key_and_skips_unchanged_matrix_data(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $group = AssetGroup::factory()->create(['unit_kerja_id' => $unit->id, 'name' => '5. CATU DAYA SINTEL']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'CATU DAYA SINYAL']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'CATU DAYA SINYAL']);
        $asset = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create();
        $path = $this->workbook();
        $importer = app(RiskMatrixWorkbookImporter::class);

        $importer->import($path, $unit);
        $before = RiskMatrix::query()->where('asset_id', $asset->id)->sole();
        $sourceKey = $before->source_key;
        $updatedAt = $before->updated_at;
        $second = $importer->import($path, $unit);
        $this->rewriteWorkbook($path, 2);
        $third = $importer->import($path, $unit);

        $after = RiskMatrix::query()->where('asset_id', $asset->id)->sole();
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(1, $second['duplicates_skipped']);
        $this->assertTrue($updatedAt->equalTo($before->fresh()->updated_at));
        $this->assertSame(1, $third['updated']);
        $this->assertSame($sourceKey, $after->source_key);
        $this->assertSame(2, $after->likelihood);
        $this->assertDatabaseCount('risk_matrices', 1);
    }

    public function test_duplicate_matrix_identity_inside_one_workbook_rejects_both_rows(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $group = AssetGroup::factory()->create(['unit_kerja_id' => $unit->id, 'name' => '5. CATU DAYA SINTEL']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'CATU DAYA SINYAL']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'CATU DAYA SINYAL']);
        Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create();
        $path = $this->workbook();
        $this->appendMatrixRow($path);

        $result = app(RiskMatrixWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['duplicates_skipped']);
        $this->assertSame(['Risk Matrix!2', 'Risk Matrix!3'], $result['duplicate_locations']);
        $this->assertDatabaseCount('risk_matrices', 0);
    }

    private function workbook(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'risk-matrix-').'.xlsx';
        $this->temporaryFiles[] = $path;
        $this->rewriteWorkbook($path, 1);

        return $path;
    }

    private function rewriteWorkbook(string $path, int $likelihood): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Risk Matrix');
        foreach (['A1' => 'ASET PRASARANA SINTEL', 'B1' => 'System', 'C1' => 'Subsystem', 'D1' => 'Likelihood', 'E1' => 'Consequences', 'F1' => 'Rating', 'G1' => 'Concat', 'H1' => 'Desc'] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->fromArray(['13. CATU DAYA SINTEL', 'CATU DAYA SINYAL', 'CATU DAYA SINYAL', $likelihood, 4, '=D2*E2', '=CONCATENATE(D2,E2)', 'High'], null, 'A2');
        $sheet->getStyle('A2:C2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    private function appendMatrixRow(string $path): void
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Risk Matrix');
        $sheet->fromArray([
            '13. CATU DAYA SINTEL',
            'CATU DAYA SINYAL',
            'CATU DAYA SINYAL',
            2,
            4,
            '=D3*E3',
            '=CONCATENATE(D3,E3)',
            'High',
        ], null, 'A3');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }
}
