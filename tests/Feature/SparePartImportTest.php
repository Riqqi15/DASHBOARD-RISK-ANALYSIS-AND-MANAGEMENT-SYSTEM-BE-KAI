<?php

namespace Tests\Feature;

use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\SparePart;
use App\Services\SparePartWorkbookImporter;
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
            [
                'PERALATAN LUAR SINYAL ELEKTRIK',
                'PERAGA SINYAL ELEKTRIK',
                'Track Circuit',
                'Relay Track',
                4,
                2.5,
                3,
                2,
                8,
                5,
                13,
                'Critical',
            ],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Dibuat: 1')
            ->assertSuccessful();
        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Dibuat: 0')
            ->expectsOutputToContain('Tidak berubah: 1')
            ->assertSuccessful();
        $unchanged = app(SparePartWorkbookImporter::class)->import($path);

        $this->assertDatabaseCount('spare_parts', 1);
        $this->assertSame(1, $unchanged['duplicates_skipped']);
        $this->assertSame(['Reorder Stock!2'], $unchanged['duplicate_locations']);
        $part = SparePart::query()->sole();
        $this->assertTrue($part->assetSubsystem->is($subsystem));
        $this->assertSame('Track Circuit', $part->equipment);
        $this->assertSame('Relay Track', $part->detail_equipment);
        $this->assertSame('4.00', $part->max_yearly_failure);
        $this->assertSame('2.50', $part->average_yearly_failure);
        $this->assertSame('3.00', $part->max_lead_time_months);
        $this->assertSame('2.00', $part->average_lead_time_months);
        $this->assertSame(7, $part->safety_stock);
        $this->assertSame(5, $part->lead_time_demand);
        $this->assertSame(12, $part->reorder_point);
        $this->assertSame('calculated', $part->reorder_calculation_status);
        $this->assertSame('kai-reorder-v1.0.0', $part->reorder_formula_version);
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
        $this->assertEqualsCanonicalizing(
            ['Detail A', 'Detail B'],
            SparePart::query()->pluck('detail_equipment')->all(),
        );
        $this->assertDatabaseHas('spare_parts', [
            'detail_equipment' => 'Detail B',
            'equipment' => 'Subsystem Excel',
            'max_yearly_failure' => null,
            'reorder_point' => null,
            'severity' => null,
        ]);
    }

    public function test_typo_under_a_system_with_one_child_is_not_silently_remapped(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', 'Subsystem Typo', 'Detail', 5, 5, 3, 1, 10, 5, 15, null]]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_maps_reorder_sub_system_to_unique_master_subsystem_within_group(): void
    {
        $group = AssetGroup::factory()->create(['name' => '2. PERALATAN LUAR SINYAL ELEKTRIK']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'PERAGA SINYAL ELEKTRIK']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'PERAGA SINYAL ELEKTRIK UTAMA']);
        $path = $this->workbook([
            [
                'Peralatan Luar Sinyal Elektrik',
                'Peraga Sinyal Elektrik Utama',
                'Sinyal Langsir',
                'Pondasi',
                null, null, null, null, null, null, null, null,
            ],
        ]);

        $result = app(SparePartWorkbookImporter::class)->import(
            $path,
            bootstrapCategories: false,
            unit: null,
            skipUnmatchedCategories: true,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([], $result['issues']);
        $this->assertDatabaseHas('spare_parts', [
            'asset_subsystem_id' => $subsystem->id,
            'equipment' => 'Sinyal Langsir',
            'detail_equipment' => 'Pondasi',
            'reorder_calculation_status' => 'insufficient_data',
        ]);
    }

    public function test_reuses_proven_group_anchor_for_forward_filled_deeper_hierarchy(): void
    {
        $group = AssetGroup::factory()->create(['name' => '1. PERALATAN DALAM SINYAL ELEKTRIK']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'INTERLOCKING ELEKTRIK']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'INTERLOCKING ELEKTRIK']);
        $path = $this->workbook([
            ['Peralatan Dalam Sinyal Elektrik', 'Interlocking Electric', 'Interlocking Electric', 'Modul Interlocking', 1, 1, 1, 1, 1, 1, 2, 'High'],
            ['', 'Panel Pelayanan', 'Panel Pelayanan LCP', 'Meja Pelayanan LCP', null, null, null, null, null, null, null, null],
            ['', 'Terminal Peralatan', 'Data Logger', 'PC Based', null, null, null, null, null, null, null, null],
        ]);

        $result = app(SparePartWorkbookImporter::class)->import(
            $path,
            bootstrapCategories: false,
            unit: null,
            skipUnmatchedCategories: true,
        );

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([$subsystem->id], SparePart::query()->distinct()->pluck('asset_subsystem_id')->all());
    }

    public function test_repeated_detail_equipment_under_different_equipment_creates_distinct_parts(): void
    {
        $group = AssetGroup::factory()->create(['name' => '2. PERALATAN LUAR SINYAL ELEKTRIK']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'PERAGA SINYAL ELEKTRIK']);
        AssetSubsystem::factory()->for($system)->create(['name' => 'PERAGA SINYAL ELEKTRIK UTAMA']);
        $path = $this->workbook([
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Masuk', 'Tiang Sinyal', null, null, null, null, null, null, null, null],
            ['', '', 'Sinyal Langsir', 'Tiang Sinyal', null, null, null, null, null, null, null, null],
        ]);

        $result = app(SparePartWorkbookImporter::class)->import(
            $path,
            bootstrapCategories: false,
            unit: null,
            skipUnmatchedCategories: true,
        );

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseCount('spare_parts', 2);
        $this->assertEqualsCanonicalizing(
            ['Sinyal Masuk', 'Sinyal Langsir'],
            SparePart::query()->pluck('equipment')->all(),
        );
    }

    public function test_explicit_bootstrap_mode_creates_missing_reorder_hierarchy_and_aliases(): void
    {
        $path = $this->workbook([
            [
                'Peralatan Luar Sinyal Elektrik',
                'Pengaman Perlintasan Sebidang',
                'Palang Pintu',
                'Motor Palang',
                1,
                1,
                1,
                1,
                2,
                3,
                5,
                'High',
            ],
        ]);

        $this->artisan('rams:import-spare-parts', [
            'workbook' => $path,
            '--bootstrap-categories' => true,
        ])->assertSuccessful();

        $part = SparePart::query()->sole();
        $this->assertSame('Palang Pintu', $part->assetSubsystem->name);
        $this->assertSame('Pengaman Perlintasan Sebidang', $part->assetSubsystem->assetSystem->name);
        $this->assertSame('Peralatan Luar Sinyal Elektrik', $part->assetSubsystem->assetSystem->assetGroup->name);
        $this->assertSame(3, AssetCategorySourceAlias::query()->count());
    }

    public function test_default_mode_rejects_the_missing_reorder_hierarchy(): void
    {
        $path = $this->workbook([
            [
                'Peralatan Luar Sinyal Elektrik',
                'Pengaman Perlintasan Sebidang',
                'Palang Pintu',
                'Motor Palang',
                1,
                1,
                1,
                1,
                2,
                3,
                5,
                'High',
            ],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment')
            ->assertFailed();

        $this->assertDatabaseCount('asset_groups', 0);
        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_inactive_alias_fails_with_context_instead_of_importing(): void
    {
        $subsystem = $this->categoryPath('Global Group', 'Global System', 'Global Subsystem');
        $this->sourceAliases($subsystem, 'Excel Group', 'Excel System', 'Excel Subsystem');
        $subsystem->update(['is_active' => false]);
        $path = $this->workbook([
            ['Excel Group', 'Excel System', 'Excel Subsystem', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment: alias kategori tidak aktif')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_broken_alias_fails_with_context_instead_of_falling_back(): void
    {
        $this->categoryPath('Excel Group', 'Excel System', 'Excel Subsystem');
        AssetCategorySourceAlias::query()->create([
            'category_type' => 'subsystem',
            'category_id' => 999999,
            'source_path' => 'Excel Group|Excel System|Excel Subsystem',
            'normalized_source_path' => 'excel group|excel system|excel subsystem',
            'workbook_name' => 'old.xlsx',
            'sheet_name' => 'Predictive Data Asset',
            'first_imported_at' => now(),
            'last_imported_at' => now(),
        ]);
        $path = $this->workbook([
            ['Excel Group', 'Excel System', 'Excel Subsystem', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment: alias kategori rusak')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_alias_with_soft_deleted_parent_fails_as_a_contextual_broken_alias(): void
    {
        $subsystem = $this->categoryPath('Global Group', 'Global System', 'Global Subsystem');
        $this->sourceAliases($subsystem, 'Excel Group', 'Excel System', 'Excel Subsystem');
        $subsystem->assetSystem->delete();
        $path = $this->workbook([
            ['Excel Group', 'Excel System', 'Excel Subsystem', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment: alias kategori rusak')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_inactive_name_path_is_not_used_by_default_or_bootstrap_resolution(): void
    {
        $subsystem = $this->categoryPath('Kelompok', 'System', 'Equipment');
        $subsystem->update(['is_active' => false]);
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High']]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_name_fallback_persists_aliases_so_reimport_survives_category_rename(): void
    {
        $subsystem = $this->categoryPath('1. Kelompok Excel', 'System Excel', 'Subsystem Excel');
        $path = $this->workbook([
            ['Kelompok Excel', 'System Excel', 'Subsystem Excel', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();
        $this->assertSame(3, AssetCategorySourceAlias::query()->count());

        $subsystem->assetSystem->assetGroup->update(['name' => 'Renamed Group']);
        $subsystem->assetSystem->update(['name' => 'Renamed System']);
        $subsystem->update(['name' => 'Renamed Subsystem']);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Tidak berubah: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('spare_parts', 1);
        $this->assertTrue(SparePart::query()->sole()->assetSubsystem->is($subsystem));
    }

    public function test_numeric_formulas_are_evaluated_only_in_numeric_columns(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([
            ['Kelompok', 'System', 'Equipment', 'Detail', null, null, null, null, null, null, null, null],
        ]);
        $this->rewriteCell($path, 'E2', '=2+2');
        $this->rewriteCell($path, 'F2', '=1+1');
        $this->rewriteCell($path, 'G2', '=1+2');
        $this->rewriteCell($path, 'H2', '=1+1');
        $this->rewriteCell($path, 'I2', '=5+3');
        $this->rewriteCell($path, 'K2', '=I2+J2');

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

        $part = SparePart::query()->sole();
        $this->assertSame('4.00', $part->max_yearly_failure);
        $this->assertSame(8, $part->safety_stock);
        $this->assertSame(4, $part->lead_time_demand);
        $this->assertSame(12, $part->reorder_point);
    }

    public function test_formula_in_text_column_is_rejected_with_context(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High']]);
        $this->rewriteCell($path, 'D2', '="Formula Detail"');

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Detail Equipment: formula tidak diizinkan')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_formula_error_in_numeric_column_is_rejected_with_context(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', null, 1, 1, 1, 2, 3, 5, 'High']]);
        $this->rewriteCell($path, 'E2', '=1/0');

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Max yearly Failure: formula')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_decimal_precision_and_database_bounds_are_validated_with_context(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        foreach ([1.234, 100000000] as $invalid) {
            $path = $this->workbook([
                ['Kelompok', 'System', 'Equipment', 'Detail '.$invalid, $invalid, 1, 1, 1, 2, 3, 5, 'High'],
            ]);

            $this->artisan('rams:import-spare-parts', ['workbook' => $path])
                ->expectsOutputToContain('row 2, header Max yearly Failure')
                ->assertFailed();
        }

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_excel_output_columns_do_not_override_the_authoritative_formula(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 4294967296, 3, 5, 'High']]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

        $part = SparePart::query()->sole();
        $this->assertSame(0, $part->safety_stock);
        $this->assertSame(1, $part->lead_time_demand);
        $this->assertSame(1, $part->reorder_point);
    }

    public function test_text_field_lengths_are_validated_before_database_write(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', str_repeat('E', 256), 'Detail', 1, 1, 1, 1, 2, 3, 5, 'High']]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('row 2, header Equipment: maksimal 255 karakter')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_all_imported_text_columns_honor_schema_lengths(): void
    {
        $cases = [
            [1, 'System'],
            [2, 'Sub-System'],
            [3, 'Equipment'],
            [4, 'Detail Equipment'],
            [12, 'Severity Equipment'],
        ];

        foreach ($cases as [$column, $header]) {
            $this->categoryPath('Kelompok '.$column, 'System '.$column, 'Equipment '.$column);
            $row = [
                'Kelompok '.$column,
                'System '.$column,
                'Equipment '.$column,
                'Detail',
                1,
                1,
                1,
                1,
                2,
                3,
                5,
                'High',
            ];
            $row[$column - 1] = str_repeat('X', 256);
            $path = $this->workbook([$row]);

            $this->artisan('rams:import-spare-parts', ['workbook' => $path])
                ->expectsOutputToContain("row 2, header {$header}: maksimal 255 karakter")
                ->assertFailed();
        }

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_actual_thirteen_reorder_paths_bootstrap_once_then_import_strictly(): void
    {
        $inside = AssetGroup::factory()->create(['name' => '1. PERALATAN DALAM SINYAL ELEKTRIK']);
        $insideSystem = AssetSystem::factory()
            ->for($inside)
            ->create(['name' => 'INTERLOCKING ELEKTRIK']);
        AssetSubsystem::factory()
            ->for($insideSystem)
            ->create(['name' => 'INTERLOCKING ELEKTRIK']);
        $outside = AssetGroup::factory()->create(['name' => '2. PERALATAN LUAR SINYAL ELEKTRIK']);
        $outsideSystem = AssetSystem::factory()
            ->for($outside)
            ->create(['name' => 'PERAGA SINYAL ELEKTRIK']);
        AssetSubsystem::factory()
            ->for($outsideSystem)
            ->create(['name' => 'PERAGA SINYAL ELEKTRIK UTAMA']);

        $paths = [
            ['Peralatan Dalam Sinyal Elektrik', 'Interlocking Electric', 'Interlocking Electric'],
            ['Peralatan Dalam Sinyal Elektrik', 'Panel Pelayanan', 'Panel Pelayanan LCP'],
            ['Peralatan Dalam Sinyal Elektrik', 'Panel Pelayanan', 'Panel Pelayanan VDU'],
            ['Peralatan Dalam Sinyal Elektrik', 'Peralatan Blok', 'Peralatan Blok'],
            ['Peralatan Dalam Sinyal Elektrik', 'Terminal Peralatan', 'Data Logger'],
            ['Peralatan Dalam Sinyal Elektrik', 'Terminal Peralatan', 'Technician Terminal'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Masuk'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Keluar / Berangkat 2 Aspek'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Berangkat/ Keluar 3 Aspek'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Blok 2 Aspek'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Blok 3 Aspek'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Langsir'],
            ['Peralatan Luar Sinyal Elektrik', 'Peraga Sinyal Elektrik Utama', 'Sinyal Darurat'],
        ];
        $rows = array_map(
            fn (array $path, int $index): array => [...$path, 'Detail '.$index, 1, 1, 1, 1, 2, 3, 5, 'High'],
            $paths,
            array_keys($paths),
        );
        $path = $this->workbook($rows);

        $this->artisan('rams:import-spare-parts', [
            'workbook' => $path,
            '--bootstrap-categories' => true,
        ])
            ->expectsOutputToContain('Dibuat: 13')
            ->assertSuccessful();
        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Tidak berubah: 13')
            ->assertSuccessful();

        $this->assertDatabaseCount('spare_parts', 13);
    }

    public function test_import_updates_source_fields_but_preserves_admin_managed_fields(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium']]);
        $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

        $part = SparePart::query()->sole();
        $part->update(['code' => 'ADMIN-001', 'unit_of_measure' => 'buah', 'is_active' => false]);
        $this->rewriteCell($path, 'G2', 2);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Diperbarui: 1')
            ->assertSuccessful();

        $part->refresh();
        $this->assertSame('ADMIN-001', $part->code);
        $this->assertSame('buah', $part->unit_of_measure);
        $this->assertFalse($part->is_active);
        $this->assertSame(2, $part->reorder_point);
    }

    public function test_soft_deleted_sparepart_is_skipped_without_being_restored(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium']]);
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
        $path = $this->workbook([['Kelompok', 'System', 'Equipment', 'Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium']]);
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
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $path = $this->workbook([
            ['Kelompok', 'System', 'Equipment', 'Valid Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
            ['Kelompok Hilang', 'System Hilang', 'Equipment Hilang', 'Invalid Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'],
        ]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain('Workbook '.basename($path).', sheet Reorder Stock, row 3, header Equipment')
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    public function test_duplicate_source_rows_fail_atomically_with_precise_context(): void
    {
        $this->categoryPath('Kelompok', 'System', 'Equipment');
        $row = ['Kelompok', 'System', 'Equipment', 'Duplicate Detail', 1, 1, 1, 1, 2, 3, 5, 'Medium'];
        $path = $this->workbook([$row, $row]);

        $this->artisan('rams:import-spare-parts', ['workbook' => $path])
            ->expectsOutputToContain(
                'Workbook '.
                    basename($path).
                    ', sheet Reorder Stock, row 3, header Detail Equipment: '.
                    'duplikat source key dengan row 2 dan row 3.',
            )
            ->assertFailed();

        $this->assertDatabaseCount('spare_parts', 0);
    }

    private function categoryPath(string $groupName, string $systemName, string $subsystemName): AssetSubsystem
    {
        $group = AssetGroup::factory()->create(['name' => $groupName]);
        $system = AssetSystem::factory()
            ->for($group)
            ->create(['name' => $systemName]);

        return AssetSubsystem::factory()
            ->for($system)
            ->create(['name' => $subsystemName]);
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
        $sheet->fromArray(
            [
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
            ],
            null,
            'A1',
        );

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
