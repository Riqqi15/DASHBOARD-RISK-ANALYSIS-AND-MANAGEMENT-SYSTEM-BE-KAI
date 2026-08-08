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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class RiskMatrixWorkbookImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_direct_risk_values_and_excel_colors_without_overwriting_manual_color(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $group = AssetGroup::factory()->create([
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

        try {
            $summary = app(RiskMatrixWorkbookImporter::class)->import($path, $unit);
        } finally {
            @unlink($path);
        }

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

    private function workbook(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'risk-matrix-').'.xlsx';
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Risk Matrix');
        foreach (['A1' => 'ASET PRASARANA SINTEL', 'B1' => 'System', 'C1' => 'Subsystem', 'D1' => 'Likelihood', 'E1' => 'Consequences', 'F1' => 'Rating', 'G1' => 'Concat', 'H1' => 'Desc'] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $sheet->fromArray(['13. CATU DAYA SINTEL', 'CATU DAYA SINYAL', 'CATU DAYA SINYAL', 1, 4, '=D2*E2', '=CONCATENATE(D2,E2)', 'High'], null, 'A2');
        $sheet->getStyle('A2:C2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
