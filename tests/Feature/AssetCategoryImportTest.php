<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\AuditLog;
use App\Models\UnitKerja;
use App\Models\UnitSubsystemOpening;
use App\Models\User;
use App\Queries\AssetHierarchyQuery;
use App\Services\MasterAssetWorkbookImporter;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class AssetCategoryImportTest extends TestCase
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

    public function test_unit_subsystem_opening_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasTable('unit_subsystem_openings'));
        $this->assertTrue(Schema::hasColumns('unit_subsystem_openings', [
            'id',
            'unit_kerja_id',
            'asset_subsystem_id',
            'source_key',
            'sparepart_in',
            'sparepart_out',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_opening_factory_builds_a_consistent_chain_and_model_scope(): void
    {
        $opening = UnitSubsystemOpening::factory()->create([
            'sparepart_in' => 7,
            'sparepart_out' => 0,
        ]);
        $regional = User::factory()->unit($opening->unitKerja)->create();
        $other = UnitSubsystemOpening::factory()->create();

        $this->assertSame(7, $opening->sparepart_in);
        $this->assertSame(0, $opening->sparepart_out);
        $this->assertSame(64, strlen($opening->source_key));
        $this->assertSame(
            hash('sha256', "{$opening->unit_kerja_id}|{$opening->asset_subsystem_id}|opening"),
            $opening->source_key,
        );
        $this->assertTrue($opening->unitKerja->openings->contains($opening));
        $this->assertTrue($opening->unitKerja->unitSubsystemOpenings->contains($opening));
        $this->assertTrue($opening->assetSubsystem->openings->contains($opening));
        $this->assertTrue($opening->assetSubsystem->unitSubsystemOpenings->contains($opening));
        $this->assertSame(
            [$opening->id],
            UnitSubsystemOpening::query()->visibleTo($regional)->pluck('id')->all(),
        );
        $this->assertNotSame($opening->unit_kerja_id, $other->unit_kerja_id);
    }

    public function test_opening_database_enforces_unique_and_restrict_constraints(): void
    {
        $opening = UnitSubsystemOpening::factory()->create();

        $this->assertMysqlError(1062, fn () => UnitSubsystemOpening::factory()->create([
            'source_key' => $opening->source_key,
        ]));
        $this->assertMysqlError(1062, fn () => UnitSubsystemOpening::factory()->create([
            'unit_kerja_id' => $opening->unit_kerja_id,
            'asset_subsystem_id' => $opening->asset_subsystem_id,
        ]));
        $this->assertMysqlError(1451, fn () => UnitKerja::query()->findOrFail($opening->unit_kerja_id)->forceDelete());
        $this->assertMysqlError(1451, fn () => AssetSubsystem::query()->findOrFail($opening->asset_subsystem_id)->forceDelete());
    }

    public function test_import_creates_category_assets_and_exact_openings_then_reimports_without_duplicates(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['  Kelompok   Sinyal ', 'Interlocking Elektrik', 'Track Circuit', 12, 5, 0, 40909],
            ['', '', 'Axle Counter', 0, 0, 3, 40909],
        ]);

        $first = app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $this->assertSame([
            'created' => 2,
            'updated' => 0,
            'unchanged' => 0,
            'duplicates_skipped' => 0,
            'duplicate_locations' => [],
            'skipped' => 0,
            'openings_created' => 2,
            'openings_updated' => 0,
            'predictive_snapshots' => 0,
        ], $first);
        $group = AssetGroup::query()->sole();
        $system = AssetSystem::query()->sole();
        $trackCircuit = AssetSubsystem::query()->where('name', 'Track Circuit')->sole();
        $this->assertSame('Kelompok Sinyal', $group->name);
        $this->assertSame($group->id, $system->asset_group_id);
        $this->assertSame($system->id, $trackCircuit->asset_system_id);
        $this->assertDatabaseHas('assets', [
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $trackCircuit->id,
            'aset_prasarana_sintel' => 'Kelompok Sinyal',
            'jumlah_unit' => 12,
        ]);
        $this->assertDatabaseHas('unit_subsystem_openings', [
            'unit_kerja_id' => $unit->id,
            'asset_subsystem_id' => $trackCircuit->id,
            'sparepart_in' => 5,
            'sparepart_out' => 0,
        ]);
        $this->assertDatabaseHas('unit_subsystem_openings', [
            'unit_kerja_id' => $unit->id,
            'sparepart_in' => 0,
            'sparepart_out' => 3,
        ]);

        $second = app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(0, $second['openings_created']);
        $this->assertSame(0, $second['openings_updated']);
        $this->assertDatabaseCount('asset_groups', 1);
        $this->assertDatabaseCount('asset_systems', 1);
        $this->assertDatabaseCount('asset_subsystems', 2);
        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseCount('unit_subsystem_openings', 2);
        $this->assertSame(2, AuditLog::query()->where('action', 'unit_subsystem_opening.imported')->count());
    }

    public function test_unchanged_reimport_has_zero_updates_and_no_duplicate_asset_or_opening_audits_in_result_and_command(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 5, 2, 1, 40909],
        ]);

        app(MasterAssetWorkbookImporter::class)->import($path, $unit);
        $unchanged = app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $this->assertSame([
            'created' => 0,
            'updated' => 0,
            'unchanged' => 1,
            'duplicates_skipped' => 1,
            'duplicate_locations' => ['Predictive Data Asset!3'],
            'skipped' => 0,
            'openings_created' => 0,
            'openings_updated' => 0,
            'predictive_snapshots' => 0,
        ], $unchanged);
        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->expectsOutputToContain('Dibuat: 0')
            ->expectsOutputToContain('Diperbarui: 0')
            ->expectsOutputToContain('Dilewati: 0')
            ->expectsOutputToContain('Pembukaan dibuat: 0')
            ->expectsOutputToContain('Pembukaan diperbarui: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('unit_subsystem_openings', 1);
        $this->assertSame(1, AuditLog::query()->where('action', 'asset.import_created')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'asset.import_updated')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'unit_subsystem_opening.imported')->count());
    }

    public function test_missing_sparepart_header_aborts_before_any_workbook_write(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 1, 2, 3, 40909],
        ], includeSparepartOut: false);

        try {
            app(MasterAssetWorkbookImporter::class)->import($path, $unit);
            $this->fail('Expected invalid workbook headers to abort the import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sparepart_out', $exception->getMessage());
        }

        $this->assertDatabaseCount('asset_groups', 0);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('unit_subsystem_openings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_negative_opening_quantity_aborts_the_atomic_workbook_import(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 1, -1, 0, 40909],
        ]);

        try {
            app(MasterAssetWorkbookImporter::class)->import($path, $unit);
            $this->fail('Expected a negative opening quantity to abort the import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tidak boleh negatif', $exception->getMessage());
        }

        $this->assertDatabaseCount('asset_groups', 0);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('unit_subsystem_openings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_quantities_report_workbook_sheet_row_and_header_and_roll_back(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $cases = [
            ['text', 'TOTAL', ['not-a-number', 1, 1]],
            ['formula error', 'Sparepart IN', [1, '#DIV/0!', 1]],
            ['fractional', 'Sparepart OUT', [1, 1, 1.5]],
            ['negative', 'Sparepart IN', [1, -1, 1]],
            ['overflow', 'TOTAL', [4294967296, 1, 1]],
            ['infinity text', 'Sparepart IN', [1, 'INF', 1]],
            ['nan text', 'Sparepart OUT', [1, 1, 'NAN']],
        ];

        foreach ($cases as [$label, $header, [$total, $sparepartIn, $sparepartOut]]) {
            $path = $this->workbook([
                ['Kelompok', 'System', 'Subsystem', $total, $sparepartIn, $sparepartOut, 40909],
            ]);
            $caught = null;

            try {
                app(MasterAssetWorkbookImporter::class)->import($path, $unit);
            } catch (RuntimeException $exception) {
                $caught = $exception;
            }

            $this->assertNotNull($caught, "Expected {$label} quantity to abort the import.");
            $this->assertStringContainsString(basename($path), $caught->getMessage());
            $this->assertStringContainsString('Predictive Data Asset', $caught->getMessage());
            $this->assertStringContainsString('row 3', $caught->getMessage());
            $this->assertStringContainsString($header, $caught->getMessage());
            $this->assertWorkbookImportTablesEmpty();
        }
    }

    public function test_blank_dash_and_en_dash_quantities_are_zero_and_unsigned_boundary_is_exact(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $zeroPath = $this->workbook([
            ['Kelompok', 'System', 'Kosong', '', null, '–', 40909],
            ['', '', 'Dash', '-', '–', '-', 40909],
        ]);

        app(MasterAssetWorkbookImporter::class)->import($zeroPath, $unit);

        $this->assertSame([0, 0], Asset::query()->orderBy('id')->pluck('jumlah_unit')->all());
        $this->assertSame([0, 0], UnitSubsystemOpening::query()->orderBy('id')->pluck('sparepart_in')->all());
        $this->assertSame([0, 0], UnitSubsystemOpening::query()->orderBy('id')->pluck('sparepart_out')->all());

        $boundaryPath = $this->workbook([
            ['Kelompok Lain', 'System Lain', 'Batas', 4294967295, 4294967295, 4294967295, 40909],
        ]);
        app(MasterAssetWorkbookImporter::class)->import($boundaryPath, $unit);

        $boundaryAsset = Asset::query()->where('subsystem', 'Batas')->sole();
        $boundaryOpening = UnitSubsystemOpening::query()
            ->where('asset_subsystem_id', $boundaryAsset->asset_subsystem_id)
            ->sole();
        $this->assertSame(4294967295, $boundaryAsset->jumlah_unit);
        $this->assertSame(4294967295, $boundaryOpening->sparepart_in);
        $this->assertSame(4294967295, $boundaryOpening->sparepart_out);
    }

    public function test_legacy_asset_key_is_migrated_and_user_fields_and_category_rename_survive_reimport(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $group = AssetGroup::factory()->create(['name' => 'Kelompok Sinyal']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'Interlocking Elektrik']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'Track Circuit']);
        $legacyKey = hash('sha256', 'DAOP-1|Predictive Data Asset|Interlocking   Elektrik|Track   Circuit');
        $asset = Asset::factory()
            ->for($unit)
            ->for($subsystem, 'assetSubsystem')
            ->create([
                'source_key' => $legacyKey,
                'nama_aset' => 'Nama suntingan operator',
                'status' => 'dalam_perbaikan',
            ]);
        $path = $this->workbook([
            ['Kelompok Sinyal', 'Interlocking   Elektrik', 'Track   Circuit', 22, 4, 1, 40909],
        ]);

        app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $asset->refresh();
        $stableKey = hash(
            'sha256',
            "rams:master-asset:v2|unit_id={$unit->id}|sheet=Predictive Data Asset|asset_subsystem_id={$asset->asset_subsystem_id}",
        );
        $this->assertDatabaseCount('assets', 1);
        $this->assertSame($stableKey, $asset->source_key);
        $this->assertSame($unit->id, $asset->assetSubsystem->assetSystem->assetGroup->unit_kerja_id);
        $this->assertSame('Nama suntingan operator', $asset->nama_aset);
        $this->assertSame('dalam_perbaikan', $asset->status->value);

        $regionalSubsystem = $asset->assetSubsystem;
        $regionalSubsystem->update(['name' => 'Track Circuit Hasil Rename Admin']);
        app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $this->assertDatabaseCount('assets', 1);
        $this->assertSame($regionalSubsystem->id, $asset->fresh()->asset_subsystem_id);
        $this->assertSame('Track Circuit Hasil Rename Admin', $regionalSubsystem->fresh()->name);
        $this->assertSame('Nama suntingan operator', $asset->fresh()->nama_aset);
    }

    public function test_global_imported_asset_is_reused_when_taxonomy_becomes_unit_scoped(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $globalGroup = AssetGroup::factory()->create([
            'unit_kerja_id' => null,
            'name' => '1. Peralatan Dalam Sinyal Elektrik',
        ]);
        $globalSystem = AssetSystem::factory()->for($globalGroup)->create([
            'name' => 'Interlocking Elektrik',
        ]);
        $globalSubsystem = AssetSubsystem::factory()->for($globalSystem)->create([
            'name' => 'Interlocking Elektrik',
        ]);
        $asset = Asset::factory()
            ->for($unit)
            ->for($globalSubsystem, 'assetSubsystem')
            ->create([
                'source_key' => hash(
                    'sha256',
                    "rams:master-asset:v2|unit_id={$unit->id}|sheet=Predictive Data Asset|asset_subsystem_id={$globalSubsystem->id}",
                ),
                'nama_aset' => 'Nama suntingan operator',
                'status' => 'dalam_perbaikan',
            ]);
        $path = $this->workbook([
            [
                '1. Peralatan Dalam Sinyal Elektrik',
                'Interlocking Elektrik',
                'Interlocking Elektrik',
                2,
                0,
                0,
                40909,
            ],
        ]);

        $result = app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount('assets', 1);
        $asset->refresh();
        $this->assertNotSame($globalSubsystem->id, $asset->asset_subsystem_id);
        $this->assertSame($unit->id, $asset->assetSubsystem->assetSystem->assetGroup->unit_kerja_id);
        $this->assertSame('Nama suntingan operator', $asset->nama_aset);
        $this->assertSame('dalam_perbaikan', $asset->status->value);
        $this->assertSame(2, $asset->jumlah_unit);
    }

    public function test_v2_source_keys_survive_unit_code_rename_without_duplicates_or_lost_edits(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 5, 2, 1, 40909],
        ]);

        app(MasterAssetWorkbookImporter::class)->import($path, $unit);
        $asset = Asset::query()->sole();
        $opening = UnitSubsystemOpening::query()->sole();
        $subsystemId = $asset->asset_subsystem_id;
        $expectedAssetKey = hash(
            'sha256',
            "rams:master-asset:v2|unit_id={$unit->id}|sheet=Predictive Data Asset|asset_subsystem_id={$subsystemId}",
        );
        $expectedOpeningKey = hash(
            'sha256',
            "rams:unit-subsystem-opening:v2|unit_id={$unit->id}|sheet=Predictive Data Asset|asset_subsystem_id={$subsystemId}",
        );
        $asset->update(['nama_aset' => 'Nama operator', 'status' => 'dalam_perbaikan']);

        $unit->update(['code' => 'DAOP-RENAME']);
        $this->rewriteImportValues($path, total: 8, sparepartIn: 4, sparepartOut: 2);
        $result = app(MasterAssetWorkbookImporter::class)->import($path, $unit->fresh());

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['openings_updated']);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('unit_subsystem_openings', 1);
        $asset->refresh();
        $opening->refresh();
        $this->assertSame($expectedAssetKey, $asset->source_key);
        $this->assertSame($expectedOpeningKey, $opening->source_key);
        $this->assertSame('Nama operator', $asset->nama_aset);
        $this->assertSame('dalam_perbaikan', $asset->status->value);
        $this->assertSame(8, $asset->jumlah_unit);
        $this->assertSame(4, $opening->sparepart_in);
        $this->assertSame(2, $opening->sparepart_out);
    }

    public function test_unique_prior_code_key_falls_back_by_unit_and_subsystem_after_code_changed_without_merging_manual_asset(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-OLD']);
        $group = AssetGroup::factory()->create(['name' => 'Kelompok']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'System']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'Subsystem']);
        $priorCodeKey = hash('sha256', "DAOP-OLD|Predictive Data Asset|{$subsystem->id}");
        $importedAsset = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'source_key' => $priorCodeKey,
            'nama_aset' => 'Imported candidate',
        ]);
        $manualAsset = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'source_key' => null,
            'nama_aset' => 'Manual asset',
        ]);
        $priorOpening = UnitSubsystemOpening::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'source_key' => hash('sha256', "DAOP-OLD|Predictive Data Asset|{$subsystem->id}|opening"),
        ]);
        $unit->update(['code' => 'DAOP-NEW']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 9, 1, 0, 40909],
        ]);

        app(MasterAssetWorkbookImporter::class)->import($path, $unit->fresh());

        $importedAsset->refresh();
        $expectedV2Key = hash(
            'sha256',
            "rams:master-asset:v2|unit_id={$unit->id}|sheet=Predictive Data Asset|asset_subsystem_id={$importedAsset->asset_subsystem_id}",
        );
        $this->assertDatabaseCount('assets', 2);
        $this->assertSame($expectedV2Key, $importedAsset->source_key);
        $this->assertSame(9, $importedAsset->jumlah_unit);
        $this->assertNull($manualAsset->fresh()->source_key);
        $this->assertSame('Manual asset', $manualAsset->fresh()->nama_aset);
        $this->assertDatabaseCount('unit_subsystem_openings', 1);
        $this->assertSame(
            hash(
                'sha256',
                "rams:unit-subsystem-opening:v2|unit_id={$unit->id}|sheet=Predictive Data Asset|asset_subsystem_id={$importedAsset->asset_subsystem_id}",
            ),
            $priorOpening->fresh()->source_key,
        );
    }

    public function test_live_and_trashed_import_candidates_abort_before_soft_delete_skip_and_roll_back(): void
    {
        [$path, $first, $second] = $this->conflictingImportCandidates();
        $second->delete();

        $this->assertAssetCandidateConflict($path, $first->unitKerja);

        $this->assertDatabaseCount('asset_category_source_aliases', 0);
        $this->assertDatabaseCount('unit_subsystem_openings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(2, Asset::withTrashed()->count());
        $this->assertFalse($first->fresh()->trashed());
        $this->assertTrue($second->fresh()->trashed());
    }

    public function test_two_trashed_import_candidates_abort_instead_of_skipping_and_roll_back(): void
    {
        [$path, $first, $second] = $this->conflictingImportCandidates();
        $first->delete();
        $second->delete();

        $this->assertAssetCandidateConflict($path, $first->unitKerja);

        $this->assertDatabaseCount('asset_category_source_aliases', 0);
        $this->assertDatabaseCount('unit_subsystem_openings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(2, Asset::withTrashed()->count());
        $this->assertTrue($first->fresh()->trashed());
        $this->assertTrue($second->fresh()->trashed());
    }

    public function test_exact_duplicate_canonical_rows_abort_with_both_rows_and_roll_back_workbook(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 5, 2, 1, 40909],
            ['Kelompok', 'System', 'Subsystem', 9, 8, 7, 40909],
        ]);

        $this->assertDuplicateCanonicalRows($path, $unit);
        $this->assertWorkbookImportTablesEmpty();
    }

    public function test_whitespace_and_case_alias_equivalent_duplicate_rows_abort_and_roll_back_workbook(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 5, 2, 1, 40909],
            ['  KELOMPOK ', ' SYSTEM  ', '  SUBSYSTEM ', 9, 8, 7, 40909],
        ]);

        $this->assertDuplicateCanonicalRows($path, $unit);
        $this->assertWorkbookImportTablesEmpty();
    }

    public function test_soft_deleted_category_conflict_rolls_back_every_write_from_the_workbook(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $conflictingGroup = AssetGroup::factory()->create([
            'unit_kerja_id' => $unit->id,
            'name' => 'Kategori Konflik',
        ]);
        $conflictingGroup->delete();
        $path = $this->workbook([
            ['Kategori Valid', 'System Valid', 'Subsystem Valid', 1, 2, 3, 40909],
            ['Kategori Konflik', 'System Konflik', 'Subsystem Konflik', 4, 5, 6, 40909],
        ]);

        try {
            app(MasterAssetWorkbookImporter::class)->import($path, $unit);
            $this->fail('Expected a soft-deleted category conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('row 4', $exception->getMessage());
            $this->assertStringContainsString('kategori konflik', $exception->getMessage());
        }

        $this->assertSame(1, AssetGroup::withTrashed()->count());
        $this->assertDatabaseCount('asset_systems', 0);
        $this->assertDatabaseCount('asset_subsystems', 0);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('unit_subsystem_openings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_opening_import_audit_is_written_only_for_create_or_changed_values(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 1, 2, 0, 40909],
        ]);

        app(MasterAssetWorkbookImporter::class)->import($path, $unit);
        app(MasterAssetWorkbookImporter::class)->import($path, $unit);
        $this->rewriteSpareparts($path, 8, 3);
        $result = app(MasterAssetWorkbookImporter::class)->import($path, $unit);

        $this->assertSame(1, $result['openings_updated']);
        $audits = AuditLog::query()->where('action', 'unit_subsystem_opening.imported')->orderBy('id')->get();
        $this->assertCount(2, $audits);
        $this->assertSame([], $audits[0]->old_values);
        $this->assertSame(2, $audits[0]->new_values['sparepart_in']);
        $this->assertSame(0, $audits[0]->new_values['sparepart_out']);
        $this->assertSame(2, $audits[1]->old_values['sparepart_in']);
        $this->assertSame(0, $audits[1]->old_values['sparepart_out']);
        $this->assertSame(8, $audits[1]->new_values['sparepart_in']);
        $this->assertSame(3, $audits[1]->new_values['sparepart_out']);
        $this->assertArrayHasKey('unit_kerja_id', $audits[1]->new_values);
        $this->assertArrayHasKey('asset_subsystem_id', $audits[1]->new_values);
        $this->assertArrayHasKey('source_key', $audits[1]->new_values);
    }

    public function test_hierarchy_query_orders_global_categories_and_scopes_integer_sums_without_n_plus_one(): void
    {
        $ownUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $regional = User::factory()->unit($ownUnit)->create();
        $pusat = User::factory()->pusat()->create();

        $laterGroup = AssetGroup::factory()->create(['name' => 'Alpha Group', 'sort_order' => 2]);
        $firstGroup = AssetGroup::factory()->create(['name' => 'Zulu Group', 'sort_order' => 1, 'is_active' => false]);
        $laterSystem = AssetSystem::factory()->for($laterGroup)->create(['name' => 'Alpha System', 'sort_order' => 2]);
        $firstSystem = AssetSystem::factory()->for($firstGroup)->create(['name' => 'Zulu System', 'sort_order' => 1, 'is_active' => false]);
        $laterSubsystem = AssetSubsystem::factory()->for($laterSystem)->create(['name' => 'Alpha Subsystem', 'sort_order' => 2]);
        $firstSubsystem = AssetSubsystem::factory()->for($firstSystem)->create(['name' => 'Zulu Subsystem', 'sort_order' => 1, 'is_active' => false]);
        $deletedSubsystem = AssetSubsystem::factory()->for($firstSystem)->create(['name' => 'Deleted Subsystem']);
        $deletedSubsystem->delete();

        Asset::factory()->for($ownUnit)->for($firstSubsystem, 'assetSubsystem')->create(['jumlah_unit' => 4]);
        Asset::factory()->for($otherUnit)->for($firstSubsystem, 'assetSubsystem')->create(['jumlah_unit' => 9]);
        $deletedAsset = Asset::factory()->for($ownUnit)->for($firstSubsystem, 'assetSubsystem')->create(['jumlah_unit' => 100]);
        $deletedAsset->delete();
        UnitSubsystemOpening::factory()->for($ownUnit)->for($firstSubsystem, 'assetSubsystem')->create([
            'sparepart_in' => 3,
            'sparepart_out' => 1,
        ]);
        UnitSubsystemOpening::factory()->for($otherUnit)->for($firstSubsystem, 'assetSubsystem')->create([
            'sparepart_in' => 8,
            'sparepart_out' => 2,
        ]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'asset_')) {
                $queries[] = $query->sql;
            }
        });
        $regionalRows = app(AssetHierarchyQuery::class)->forUser($regional, $otherUnit->id);

        $this->assertLessThanOrEqual(4, count($queries));
        $this->assertSame([$firstSubsystem->id, $laterSubsystem->id], $regionalRows->pluck('id')->all());
        $regionalFirst = $regionalRows->first();
        $this->assertSame(4, $regionalFirst->total);
        $this->assertSame(3, $regionalFirst->sparepart_in);
        $this->assertSame(1, $regionalFirst->sparepart_out);
        $this->assertIsInt($regionalFirst->total);
        $this->assertIsInt($regionalRows->last()->total);
        $this->assertSame(0, $regionalRows->last()->total);
        $this->assertFalse($regionalFirst->is_active);
        $this->assertTrue($regionalFirst->relationLoaded('assetSystem'));
        $this->assertTrue($regionalFirst->assetSystem->relationLoaded('assetGroup'));

        $selected = app(AssetHierarchyQuery::class)->forUser($pusat, $otherUnit->id)->first();
        $this->assertSame(9, $selected->total);
        $this->assertSame(8, $selected->sparepart_in);
        $this->assertSame(2, $selected->sparepart_out);

        $this->assertTrue(app(AssetHierarchyQuery::class)->forUser($pusat)->isEmpty());

        $queries = [];
        $filtered = app(AssetHierarchyQuery::class)->forUser($pusat, $ownUnit->id, [$laterSubsystem->id]);
        $this->assertSame([$laterSubsystem->id], $filtered->pluck('id')->all());
        $this->assertTrue(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, '`asset_subsystems`.`id` in ('),
        ));
    }

    private function assertMysqlError(int $expectedErrorNumber, Closure $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame($expectedErrorNumber, $exception->errorInfo[1] ?? null);

            return;
        }

        $this->fail('Expected the database to reject the query.');
    }

    /** @return array{string, Asset, Asset} */
    private function conflictingImportCandidates(): array
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $group = AssetGroup::factory()->create(['name' => 'Kelompok']);
        $system = AssetSystem::factory()->for($group)->create(['name' => 'System']);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'Subsystem']);
        $first = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'source_key' => hash('sha256', 'first-import-candidate'),
        ]);
        $second = Asset::factory()->for($unit)->for($subsystem, 'assetSubsystem')->create([
            'source_key' => hash('sha256', 'second-import-candidate'),
        ]);
        $path = $this->workbook([
            ['Kelompok', 'System', 'Subsystem', 5, 2, 1, 40909],
        ]);

        return [$path, $first, $second];
    }

    private function assertAssetCandidateConflict(string $path, UnitKerja $unit): void
    {
        $caught = null;

        try {
            app(MasterAssetWorkbookImporter::class)->import($path, $unit);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'Expected conflicting imported asset candidates to abort the workbook.');
        $this->assertStringContainsString(basename($path), $caught->getMessage());
        $this->assertStringContainsString('Predictive Data Asset', $caught->getMessage());
        $this->assertStringContainsString('row 3', $caught->getMessage());
        $this->assertStringContainsString('Kelompok|System|Subsystem', $caught->getMessage());
    }

    private function assertDuplicateCanonicalRows(string $path, UnitKerja $unit): void
    {
        $caught = null;

        try {
            app(MasterAssetWorkbookImporter::class)->import($path, $unit);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'Expected duplicate canonical workbook rows to abort the import.');
        $this->assertStringContainsString(basename($path), $caught->getMessage());
        $this->assertStringContainsString('Predictive Data Asset', $caught->getMessage());
        $this->assertStringContainsString('row 3', $caught->getMessage());
        $this->assertStringContainsString('row 4', $caught->getMessage());
        $this->assertStringContainsString('Kelompok|System|Subsystem', $caught->getMessage());
    }

    private function assertWorkbookImportTablesEmpty(): void
    {
        $this->assertDatabaseCount('asset_groups', 0);
        $this->assertDatabaseCount('asset_systems', 0);
        $this->assertDatabaseCount('asset_subsystems', 0);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('unit_subsystem_openings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * @param  list<array{string, string, string, int|float|string|null, int|float|string|null, int|float|string|null, mixed}>  $rows
     */
    private function workbook(array $rows, bool $includeSparepartOut = true): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Predictive Data Asset');
        $sheet->fromArray([
            'ASET PRASARANA SINTEL',
            'System',
            'Subsystem',
            'TOTAL',
            'Sparepart IN',
            $includeSparepartOut ? 'Sparepart OUT' : 'Kolom Salah',
        ], null, 'A2');
        $sheet->setCellValue('AA2', 'Tanggal Pemasangan');

        foreach ($rows as $offset => [$group, $system, $subsystem, $total, $sparepartIn, $sparepartOut, $date]) {
            $row = $offset + 3;
            $sheet->fromArray([$group, $system, $subsystem, $total, $sparepartIn, $sparepartOut], null, "A{$row}");
            $sheet->setCellValue("AA{$row}", $date);
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rams-category-import-'.Str::uuid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryWorkbooks[] = $path;

        return $path;
    }

    private function rewriteSpareparts(string $path, int $sparepartIn, int $sparepartOut): void
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Predictive Data Asset');
        $sheet->setCellValue('E3', $sparepartIn);
        $sheet->setCellValue('F3', $sparepartOut);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function rewriteImportValues(string $path, int $total, int $sparepartIn, int $sparepartOut): void
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Predictive Data Asset');
        $sheet->setCellValue('D3', $total);
        $sheet->setCellValue('E3', $sparepartIn);
        $sheet->setCellValue('F3', $sparepartOut);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
