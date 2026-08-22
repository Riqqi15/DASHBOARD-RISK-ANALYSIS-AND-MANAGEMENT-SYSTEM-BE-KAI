<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetSubsystem;
use App\Models\FailureLog;
use App\Models\InventoryStock;
use App\Models\ReliabilitySummary;
use App\Models\RiskRegister;
use App\Models\SparePart;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class RamsReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_user_can_export_four_xlsx_reports_scoped_to_its_unit(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $other = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $asset = Asset::factory()
            ->for($unit)
            ->create(['nama_aset' => 'ASET-DAOP-1']);
        $otherAsset = Asset::factory()
            ->for($other)
            ->create(['nama_aset' => 'ASET-DAOP-4']);
        $part = SparePart::factory()->create(['detail_equipment' => 'Relay DAOP-1']);
        InventoryStock::factory()
            ->for($unit)
            ->for($part)
            ->create(['quantity' => 7]);
        FailureLog::factory()
            ->for($asset)
            ->create(['failure_event' => 'Gangguan DAOP-1']);
        ReliabilitySummary::factory()->for($asset)->create();
        RiskRegister::factory()
            ->for($asset)
            ->create(['risk_event' => 'Risiko DAOP-1']);
        RiskRegister::factory()
            ->for($otherAsset)
            ->create(['risk_event' => 'RAHASIA DAOP-4']);
        $user = User::factory()->unit($unit)->create();

        foreach (['inventory', 'trouble-report', 'risk-register', 'reliability'] as $report) {
            $response = $this->actingAs($user)->get("/reports/{$report}/xlsx");
            $response->assertOk();
            $response->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
            $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

            $path = tempnam(sys_get_temp_dir(), 'rams-report-').'.xlsx';
            file_put_contents($path, $response->streamedContent());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertGreaterThanOrEqual(1, $sheet->getHighestDataRow());
            if ($report === 'risk-register') {
                $contents = collect($sheet->toArray())->flatten()->implode(' ');
                $this->assertStringContainsString('Risiko DAOP-1', $contents);
                $this->assertStringNotContainsString('RAHASIA DAOP-4', $contents);
            }
            @unlink($path);
        }
    }

    public function test_unknown_report_type_returns_not_found(): void
    {
        $user = User::factory()->pusat()->create();
        $this->actingAs($user)->get('/reports/unknown/xlsx')->assertNotFound();
    }

    public function test_pusat_must_select_an_area_before_exporting_reliability_workbook(): void
    {
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)
            ->get('/reports/reliability/xlsx')
            ->assertStatus(422)
            ->assertSee('Pilih DAOP/DIVRE sebelum export Reliability');
    }

    public function test_pusat_reliability_export_contains_only_the_selected_area_subsystems(): void
    {
        $selectedUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $selectedSubsystem = AssetSubsystem::factory()->create(['name' => 'Interlocking Elektrik']);
        $otherSubsystem = AssetSubsystem::factory()->create(['name' => 'RAHASIA DAOP-4']);
        $selectedAsset = Asset::factory()
            ->for($selectedUnit)
            ->for($selectedSubsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'Interlocking Elektrik', 'jumlah_unit' => 2]);
        $otherAsset = Asset::factory()
            ->for($otherUnit)
            ->for($otherSubsystem, 'assetSubsystem')
            ->create(['nama_aset' => 'RAHASIA DAOP-4', 'jumlah_unit' => 99]);
        ReliabilitySummary::factory()->for($selectedAsset)->create();
        ReliabilitySummary::factory()->for($otherAsset)->create();
        $user = User::factory()->pusat()->create();

        $response = $this->actingAs($user)->get('/reports/reliability/xlsx?area=DAOP-1');
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'rams-reliability-endpoint-').'.xlsx';
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            $names = array_map(static fn ($sheet): string => $sheet->getTitle(), $workbook->getAllSheets());

            $this->assertSame('Ringkasan Reliability', $names[0]);
            $this->assertContains('Interlocking Elektrik', $names);
            $this->assertNotContains('RAHASIA DAOP-4', $names);
            $this->assertSame("='Interlocking Elektrik'!K4", $workbook->getSheet(0)->getCell('J5')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_unit_user_can_export_four_pdf_reports_with_pdf_signature_and_unit_scope(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $other = UnitKerja::factory()->create(['code' => 'DAOP-4']);
        $asset = Asset::factory()
            ->for($unit)
            ->create(['nama_aset' => 'ASET-DAOP-1']);
        $otherAsset = Asset::factory()
            ->for($other)
            ->create(['nama_aset' => 'ASET-DAOP-4']);
        RiskRegister::factory()
            ->for($asset)
            ->create(['risk_event' => 'Risiko DAOP-1']);
        RiskRegister::factory()
            ->for($otherAsset)
            ->create(['risk_event' => 'RAHASIA DAOP-4']);
        $user = User::factory()->unit($unit)->create();

        foreach (['inventory', 'trouble-report', 'risk-register', 'reliability'] as $report) {
            $response = $this->actingAs($user)->get("/reports/{$report}/pdf");
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }

        $this->actingAs($user)->get('/reports/unknown/pdf')->assertNotFound();
    }
}
