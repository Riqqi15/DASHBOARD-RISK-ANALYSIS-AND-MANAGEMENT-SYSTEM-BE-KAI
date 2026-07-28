<?php

namespace Tests\Feature;

use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\SparePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SparePartImportTest extends TestCase
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

    public function test_reorder_stock_sheet_is_imported_idempotently_with_all_twelve_columns(): void
    {
        $subsystem = $this->categoryPath('PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'Track Circuit');
        $path = $this->workbook([
            ['PERALATAN LUAR SINYAL ELEKTRIK', 'PERAGA SINYAL ELEKTRIK', 'Track Circuit', 'Relay Track', 4, 2.5, 3, 2, 8, 5, 13, 'Critical'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Dibuat: 1')
            ->assertSuccessful();
        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Dibuat: 0')
            ->expectsOutputToContain('Tidak berubah: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('spare_parts', 1);
        $part = SparePart::query()->sole();
        $this->assertTrue($part->assetSubsystem->is($subsystem));
        $this->assertSame('Track Circuit', $part->equipment);
        $this->assertSame('Relay Track', $part->detail_equipment);
        $this->assertSame('4.00', $part->max_yearly_failure);
        $this->assertSame('2.50', $part->average_yearly_failure);
        $this->assertSame('3.00', $part->max_lead_time_months);
        $this->assertSame('2.00', $part->average_lead_time_months);
        $this->assertSame(8, $part->safety_stock);
        $this->assertSame(5, $part->lead_time_demand);
        $this->assertSame(13, $part->reorder_point);
        $this->assertSame('Critical', $part->severity);
        $this->assertSame('unit', $part->unit_of_measure);
        $this->assertTrue($part->is_active);
        $this->assertMatchesRegularExpression('/^SP-[A-F0-9]{10}$/', $part->code);
        $this->assertSame(64, strlen($part->source_key));
        $this->assertDatabaseCount('inventory_stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_merged_hierarchy_cells_are_forward_filled_and_aliases_resolve_renamed_categories(): void
    {
        $subsystem = $this->categoryPath('Kelompok Global', 'System Global', 'Subsystem Global');
        $this->sourceAliases($subsystem, 'Kelompok Excel', 'System Excel', 'Subsystem Excel');
        $path = $this->workbook([
            ['Kelompok Excel', 'System Excel', 'Subsystem Excel', 'Detail A', 1, 1, 1, 1, 1, 1, 2, 'High'],
            ['', '', '', 'Detail B', null, null, null, null, null, null, null, null],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

        $this->assertDatabaseCount('spare_parts', 2);
        $this->assertSame([$subsystem->id], SparePart::query()->distinct()->pluck('asset_subsystem_id')->all());
        $this->assertEqualsCanonicalizing(['Detail A', 'Detail B'], SparePart::query()->pluck('detail_equipment')->all());
        $this->assertDatabaseHas('spare_parts', [
            'detail_equipment' => 'Detail B',
            'equipment' => 'Subsystem Excel',
            'max_yearly_failure' => null,
            'reorder_point' => null,
            'severity' => null,
        ]);
    }

    public function test_actual_reorder_names_resolve_to_the_single_canonical_subsystem(): void
    {
        $subsystem = $this->categoryPath(
            '1. PERALATAN DALAM SINYAL ELEKTRIK',
            'INTERLOCKING ELEKTRIK',
            'INTERLOCKING ELEKTRIK',
        );
        $path = $this->workbook([
            ['Peralatan Dalam Sinyal Elektrik', 'Interlocking Electric', 'Interlocking Electric', 'Modul Interlocking', 5, 5, 3, 1, 10, 5, 15, null],
            ['', 'Panel Pelayanan', 'Panel Pelayanan LCP', 'Meja Pelayanan LCP', null, null, null, null, null, null, null, null],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

        $this->assertSame(2, SparePart::query()->count());
        $this->assertSame([$subsystem->id], SparePart::query()->distinct()->pluck('asset_subsystem_id')->all());
        $this->assertDatabaseHas('spare_parts', [
            'equipment' => 'Panel Pelayanan LCP',
            'detail_equipment' => 'Meja Pelayanan LCP',
        ]);
    }

    public function test_import_updates_source_fields_but_preserves_admin_managed_fields(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Subsystem');
        $path = $this->workbook([
            ['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
        ]);
        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

        $part = SparePart::query()->sole();
        $part->update(['code' => 'ADMIN-001', 'unit_of_measure' => 'buah', 'is_active' => false]);
        $this->rewriteCell($path, 'K2', 17);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Diperbarui: 1')
            ->assertSuccessful();

        $part->refresh();
        $this->assertSame('ADMIN-001', $part->code);
        $this->assertSame('buah', $part->unit_of_measure);
        $this->assertFalse($part->is_active);
        $this->assertSame(17, $part->reorder_point);
    }

    public function test_soft_deleted_sparepart_is_skipped_without_being_restored(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Subsystem');
        $path = $this->workbook([
            ['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
        ]);
        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();
        SparePart::query()->sole()->delete();

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Dilewati: 1')
            ->assertSuccessful();

        $this->assertSame(1, SparePart::withTrashed()->count());
        $this->assertSame(0, SparePart::query()->count());
    }

    public function test_malformed_header_reports_workbook_sheet_row_and_header_and_rolls_back(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Subsystem');
        $path = $this->workbook([
            ['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
        ]);
        $this->rewriteCell($path, 'K1', 'Wrong Reorder Header');

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain(
                'Workbook '.basename($path).', sheet Reorder Stock, row 1, header Reorder Point',
            )
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_unmatched_subsystem_reports_the_source_row_and_rolls_back_all_rows(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Subsystem');
        $path = $this->workbook([
            ['Kelompok', 'System', 'Equipment', 'Valid Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
            ['Kelompok Hilang', 'System Hilang', 'Equipment Hilang', 'Invalid Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain(
                'Workbook '.basename($path).', sheet Reorder Stock, row 3, header Sub-System',
            )
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_duplicate_source_rows_fail_atomically_with_precise_context(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Subsystem');
        $row = ['Kelompok', 'System', 'Equipment', 'Duplicate Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'];
        $path = $this->workbook([$row, $row]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain(
                'Workbook '.basename($path).', sheet Reorder Stock, row 3, header Detail Equipment: '
                    .'duplikat source key dengan row 2 dan row 3.',
            )
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    private function categoryPath(string $groupName, string $systemName, string $subsystemName): AssetSubsystem
    {
        $group = AssetGroup::factory()->create(['name' => $groupName]);
        $system = AssetSystem::factory()->for($group)->create(['name' => $systemName]);

        return AssetSubsystem::factory()->for($system)->create(['name' => $subsystemName]);
    }

    private function sourceAliases(
        AssetSubsystem $subsystem,
        string $groupName,
        string $systemName,
        string $subsystemName,
    ): void {
        $now = now();
        $paths = [
            ['group', $subsystem->assetSystem->assetGroup->id, $groupName],
            ['system', $subsystem->assetSystem->id, "{$groupName}|{$systemName}"],
            ['subsystem', $subsystem->id, "{$groupName}|{$systemName}|{$subsystemName}"],
        ];

        foreach ($paths as [$type, $id, $path]) {
            AssetCategorySourceAlias::query()->create([
                'category_type' => $type,
                'category_id' => $id,
                'source_path' => $path,
                'normalized_source_path' => mb_strtolower($path),
                'workbook_name' => 'source.xlsx',
                'sheet_name' => 'Predictive Data Asset',
                'first_imported_at' => $now,
                'last_imported_at' => $now,
            ]);
        }
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function workbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reorder Stock');
        $sheet->fromArray([
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
        ], null, 'A1');

        foreach ($rows as $offset => $row) {
            $sheet->fromArray($row, null, 'A'.($offset + 2));
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rams-spareparts-'.Str::uuid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryWorkbooks[] = $path;

        return $path;
    }

    private function rewriteCell(string $path, string $cell, mixed $value): void
    {
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getSheetByName('Reorder Stock')->setCellValue($cell, $value);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
