<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportMasterAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryWorkbooks = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryWorkbooks as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_command_imports_and_updates_source_fields_without_duplicates(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([
            ['Kelompok Sinyal', 'Interlocking Elektrik', 'Track Circuit', 12, 40909],
        ]);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->expectsOutputToContain('Pembukaan dibuat: 1')
            ->expectsOutputToContain('Pembukaan diperbarui: 0')
            ->assertSuccessful();

        $asset = Asset::query()->sole();
        $this->assertSame($unit->id, $asset->unit_kerja_id);
        $this->assertSame('Track Circuit', $asset->nama_aset);
        $this->assertSame(12, $asset->jumlah_unit);
        $this->assertSame('2012-01-01', $asset->tanggal_pemasangan->toDateString());

        $asset->update([
            'nama_aset' => 'Nama yang disunting pengguna',
            'status' => AssetStatus::DalamPerbaikan,
        ]);
        $this->rewriteTotal($path, 18);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertSuccessful();

        $this->assertDatabaseCount('assets', 1);
        $asset->refresh();
        $this->assertSame(18, $asset->jumlah_unit);
        $this->assertSame('Nama yang disunting pengguna', $asset->nama_aset);
        $this->assertSame(AssetStatus::DalamPerbaikan, $asset->status);
    }

    public function test_invalid_headers_roll_back_the_entire_import(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([['Kelompok', 'System', 'Subsystem', 1, 40909]], false);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertFailed();

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_merged_group_and_system_values_are_forward_filled(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([
            ['Kelompok Sinyal', 'Peraga Sinyal Elektrik', 'Track Circuit', 12, 40909],
            ['', '', 'Axle Counter', '-', 40909],
        ]);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertSuccessful();

        $this->assertDatabaseHas('assets', [
            'subsystem' => 'Axle Counter',
            'aset_prasarana_sintel' => 'Kelompok Sinyal',
            'system' => 'Peraga Sinyal Elektrik',
            'jumlah_unit' => 0,
        ]);
    }

    public function test_import_skips_a_matching_soft_deleted_asset(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([['Kelompok', 'System', 'Subsystem', 1, 40909]]);
        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1']);
        Asset::query()->sole()->delete();

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->expectsOutputToContain('Dilewati: 1')
            ->assertSuccessful();

        $this->assertSame(1, Asset::withTrashed()->count());
        $this->assertSame(0, Asset::query()->count());
    }

    private function workbook(array $rows, bool $validHeaders = true): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Predictive Data Asset');
        $sheet->fromArray([
            'ASET PRASARANA SINTEL', 'System', 'Subsystem', 'TOTAL', 'Sparepart IN', 'Sparepart OUT',
        ], null, 'A2');
        $sheet->setCellValue('AA2', $validHeaders ? 'Tanggal Pemasangan' : 'Tanggal Salah');

        foreach ($rows as $offset => [$group, $system, $subsystem, $total, $date]) {
            $row = $offset + 3;
            $sheet->fromArray([$group, $system, $subsystem, $total], null, "A{$row}");
            $sheet->setCellValue("AA{$row}", $date);
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rams-assets-'.Str::uuid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryWorkbooks[] = $path;

        return $path;
    }

    private function rewriteTotal(string $path, int $total): void
    {
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getSheetByName('Predictive Data Asset')->setCellValue('D3', $total);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
