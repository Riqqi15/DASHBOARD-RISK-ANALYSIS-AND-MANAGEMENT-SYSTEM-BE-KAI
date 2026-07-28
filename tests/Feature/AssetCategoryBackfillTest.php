<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Services\AssetCategoryBackfill;
use App\Services\AssetCategoryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class AssetCategoryBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_normalized_equivalent_legacy_paths_share_categories_and_rerun_is_idempotent(): void
    {
        $first = $this->legacyAsset([
            'aset_prasarana_sintel' => '  Peralatan   Dalam Sinyal Elektrik ',
            'system' => "Interlocking\tElektrik",
            'subsystem' => ' Local Control Panel ',
        ]);
        $second = $this->legacyAsset([
            'aset_prasarana_sintel' => "PERALATAN\u{00A0}DALAM SINYAL ELEKTRIK",
            'system' => ' INTERLOCKING ELEKTRIK ',
            'subsystem' => "LOCAL\u{2003}CONTROL   PANEL",
        ]);

        $this->artisan('rams:backfill-asset-categories')
            ->expectsOutput('Backfill kategori aset selesai.')
            ->expectsOutput('Terhubung: 2')
            ->expectsOutput('Dilewati: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('asset_groups', 1);
        $this->assertDatabaseCount('asset_systems', 1);
        $this->assertDatabaseCount('asset_subsystems', 1);
        $this->assertDatabaseCount('asset_category_source_aliases', 3);
        $this->assertSame($first->fresh()->asset_subsystem_id, $second->fresh()->asset_subsystem_id);

        $this->artisan('rams:backfill-asset-categories')
            ->expectsOutput('Backfill kategori aset selesai.')
            ->expectsOutput('Terhubung: 0')
            ->expectsOutput('Dilewati: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('asset_groups', 1);
        $this->assertDatabaseCount('asset_systems', 1);
        $this->assertDatabaseCount('asset_subsystems', 1);
        $this->assertDatabaseCount('asset_category_source_aliases', 3);
    }

    public function test_alias_resolution_survives_an_admin_display_name_rename(): void
    {
        $resolver = app(AssetCategoryResolver::class);
        $first = $resolver->resolve(
            'Peralatan Luar Sinyal Elektrik',
            'Peraga Sinyal Elektrik',
            'Axle Counter',
            'master.xlsx',
            'Predictive Data Asset',
            12,
        );

        $first['group']->update(['name' => 'Kelompok Hasil Rename']);
        $first['system']->update(['name' => 'Sistem Hasil Rename']);
        $first['subsystem']->update(['name' => 'Subsistem Hasil Rename']);

        $second = $resolver->resolve(
            'Peralatan Luar Sinyal Elektrik',
            'Peraga Sinyal Elektrik',
            'Axle Counter',
            'master-v2.xlsx',
            'Assets',
            99,
        );

        $this->assertSame($first['group']->id, $second['group']->id);
        $this->assertSame($first['system']->id, $second['system']->id);
        $this->assertSame($first['subsystem']->id, $second['subsystem']->id);
        $this->assertSame('Kelompok Hasil Rename', $second['group']->name);
        $this->assertSame('Sistem Hasil Rename', $second['system']->name);
        $this->assertSame('Subsistem Hasil Rename', $second['subsystem']->name);
    }

    public function test_alias_import_time_and_context_are_updated_without_changing_first_import_time(): void
    {
        Carbon::setTestNow('2026-07-28 08:00:00');
        $resolver = app(AssetCategoryResolver::class);
        $resolver->resolve('Group A', 'System A', 'Subsystem A', 'first.xlsx', 'First Sheet', 4);

        Carbon::setTestNow('2026-07-29 14:30:00');
        $resolver->resolve(' GROUP  A ', "SYSTEM\tA", 'SUBSYSTEM A', 'second.xlsx', 'Second Sheet', 8);

        $aliases = AssetCategorySourceAlias::query()->orderBy('id')->get();

        $this->assertCount(3, $aliases);
        foreach ($aliases as $alias) {
            $this->assertSame('2026-07-28 08:00:00', $alias->first_imported_at->format('Y-m-d H:i:s'));
            $this->assertSame('2026-07-29 14:30:00', $alias->last_imported_at->format('Y-m-d H:i:s'));
            $this->assertSame('second.xlsx', $alias->workbook_name);
            $this->assertSame('Second Sheet', $alias->sheet_name);
        }

        $this->assertSame(' GROUP  A ', $aliases[0]->source_path);
        $this->assertSame(" GROUP  A |SYSTEM\tA", $aliases[1]->source_path);
        $this->assertSame(" GROUP  A |SYSTEM\tA|SUBSYSTEM A", $aliases[2]->source_path);
    }

    public function test_existing_aliases_can_resolve_inactive_non_deleted_categories(): void
    {
        $resolver = app(AssetCategoryResolver::class);
        $first = $resolver->resolve('Group A', 'System A', 'Subsystem A', 'first.xlsx', 'Assets');

        $first['group']->update(['is_active' => false]);
        $first['system']->update(['is_active' => false]);
        $first['subsystem']->update(['is_active' => false]);

        $second = $resolver->resolve('Group A', 'System A', 'Subsystem A', 'second.xlsx', 'Assets');

        $this->assertSame($first['group']->id, $second['group']->id);
        $this->assertSame($first['system']->id, $second['system']->id);
        $this->assertSame($first['subsystem']->id, $second['subsystem']->id);
    }

    public function test_same_child_names_under_different_source_parents_remain_distinct(): void
    {
        $resolver = app(AssetCategoryResolver::class);
        $first = $resolver->resolve('Group A', 'Shared System', 'Shared Subsystem', 'one.xlsx', 'Assets');
        $second = $resolver->resolve('Group B', 'Shared System', 'Shared Subsystem', 'two.xlsx', 'Assets');

        $this->assertNotSame($first['group']->id, $second['group']->id);
        $this->assertNotSame($first['system']->id, $second['system']->id);
        $this->assertNotSame($first['subsystem']->id, $second['subsystem']->id);
        $this->assertDatabaseCount('asset_groups', 2);
        $this->assertDatabaseCount('asset_systems', 2);
        $this->assertDatabaseCount('asset_subsystems', 2);
        $this->assertDatabaseCount('asset_category_source_aliases', 6);
    }

    public function test_blank_paths_are_skipped_and_soft_deleted_assets_are_ignored(): void
    {
        $blank = $this->legacyAsset(['system' => " \t\u{00A0} "]);
        $deleted = $this->legacyAsset();
        $deleted->delete();

        $result = app(AssetCategoryBackfill::class)->run();

        $this->assertSame(['linked' => 0, 'skipped' => 1], $result);
        $this->assertNull($blank->fresh()->asset_subsystem_id);
        $this->assertNull(Asset::withTrashed()->findOrFail($deleted->id)->asset_subsystem_id);
        $this->assertDatabaseCount('asset_groups', 0);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
    }

    public function test_backfill_preserves_every_non_target_asset_field(): void
    {
        $asset = $this->legacyAsset([
            'nama_aset' => 'Signal 42',
            'aset_prasarana_sintel' => 'Group Exact',
            'system' => 'System Exact',
            'subsystem' => 'Subsystem Exact',
            'lokasi' => 'KM 12+300',
            'jumlah_unit' => 17,
            'tanggal_pemasangan' => '2021-03-04',
            'status' => AssetStatus::DalamPerbaikan,
            'source_key' => 'legacy-key-42',
            'created_at' => '2025-01-02 03:04:05',
            'updated_at' => '2025-02-03 04:05:06',
        ]);
        $before = $asset->fresh()->getRawOriginal();

        Carbon::setTestNow('2026-07-28 11:22:33');
        $result = app(AssetCategoryBackfill::class)->run();
        $after = $asset->fresh()->getRawOriginal();

        $this->assertSame(['linked' => 1, 'skipped' => 0], $result);
        foreach ($before as $field => $value) {
            if (in_array($field, ['asset_subsystem_id', 'updated_at'], true)) {
                continue;
            }

            $this->assertSame($value, $after[$field], "Field {$field} changed during backfill.");
        }
        $this->assertNotNull($after['asset_subsystem_id']);
        $this->assertSame('2026-07-28 11:22:33', $after['updated_at']);
    }

    public function test_corrupt_alias_fails_with_context_and_rolls_back_the_asset_resolution(): void
    {
        $unrelatedSystem = AssetSystem::factory()->create();
        $unrelatedSubsystem = AssetSubsystem::factory()->for($unrelatedSystem)->create();
        $asset = $this->legacyAsset([
            'aset_prasarana_sintel' => 'Target Group',
            'system' => 'Target System',
            'subsystem' => 'Target Subsystem',
        ]);
        AssetCategorySourceAlias::query()->create([
            'category_type' => 'subsystem',
            'category_id' => $unrelatedSubsystem->id,
            'source_path' => 'Target Group|Target System|Target Subsystem',
            'normalized_source_path' => 'target group|target system|target subsystem',
            'workbook_name' => 'old.xlsx',
            'sheet_name' => 'Old Sheet',
            'first_imported_at' => now(),
            'last_imported_at' => now(),
        ]);
        $countsBefore = $this->categoryCounts();

        try {
            app(AssetCategoryBackfill::class)->run();
            $this->fail('Expected a corrupt alias to abort the backfill.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('legacy-database', $exception->getMessage());
            $this->assertStringContainsString('assets', $exception->getMessage());
            $this->assertStringContainsString("row {$asset->id}", $exception->getMessage());
            $this->assertStringContainsString('target group|target system|target subsystem', $exception->getMessage());
        }

        $this->assertSame($countsBefore, $this->categoryCounts());
        $this->assertNull($asset->fresh()->asset_subsystem_id);
    }

    public function test_alias_with_a_missing_target_is_rejected(): void
    {
        AssetCategorySourceAlias::query()->create([
            'category_type' => 'group',
            'category_id' => 999999,
            'source_path' => 'Missing Group',
            'normalized_source_path' => 'missing group',
            'workbook_name' => 'old.xlsx',
            'sheet_name' => 'Old Sheet',
            'first_imported_at' => now(),
            'last_imported_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('book.xlsx');
        $this->expectExceptionMessage('Sheet X');
        $this->expectExceptionMessage('row 7');
        $this->expectExceptionMessage('missing group');

        app(AssetCategoryResolver::class)->resolve(
            'Missing Group',
            'System',
            'Subsystem',
            'book.xlsx',
            'Sheet X',
            7,
        );
    }

    public function test_command_reports_failure_for_a_corrupt_alias(): void
    {
        $this->legacyAsset(['aset_prasarana_sintel' => 'Broken Group']);
        AssetCategorySourceAlias::query()->create([
            'category_type' => 'group',
            'category_id' => 999999,
            'source_path' => 'Broken Group',
            'normalized_source_path' => 'broken group',
            'workbook_name' => 'old.xlsx',
            'sheet_name' => 'Old Sheet',
            'first_imported_at' => now(),
            'last_imported_at' => now(),
        ]);

        $this->artisan('rams:backfill-asset-categories')
            ->expectsOutputToContain('Backfill kategori aset gagal:')
            ->assertFailed();
    }

    public function test_rerun_does_not_touch_an_already_linked_asset_or_its_aliases(): void
    {
        Carbon::setTestNow('2026-07-28 08:00:00');
        $asset = $this->legacyAsset();
        app(AssetCategoryBackfill::class)->run();
        $aliasTimes = AssetCategorySourceAlias::query()->pluck('last_imported_at', 'id');
        $assetUpdatedAt = $asset->fresh()->updated_at->format('Y-m-d H:i:s');

        Carbon::setTestNow('2026-07-30 09:00:00');
        $result = app(AssetCategoryBackfill::class)->run();

        $this->assertSame(['linked' => 0, 'skipped' => 0], $result);
        $this->assertSame($assetUpdatedAt, $asset->fresh()->updated_at->format('Y-m-d H:i:s'));
        $this->assertEquals(
            $aliasTimes->map->format('Y-m-d H:i:s')->all(),
            AssetCategorySourceAlias::query()->pluck('last_imported_at', 'id')->map->format('Y-m-d H:i:s')->all(),
        );
    }

    private function legacyAsset(array $attributes = []): Asset
    {
        return Asset::factory()->create(array_merge([
            'asset_subsystem_id' => null,
            'aset_prasarana_sintel' => 'Peralatan Luar Sinyal Elektrik',
            'system' => 'Peraga Sinyal Elektrik',
            'subsystem' => 'Axle Counter',
        ], $attributes));
    }

    /** @return array{groups:int, systems:int, subsystems:int, aliases:int} */
    private function categoryCounts(): array
    {
        return [
            'groups' => AssetGroup::query()->count(),
            'systems' => AssetSystem::query()->count(),
            'subsystems' => AssetSubsystem::query()->count(),
            'aliases' => AssetCategorySourceAlias::query()->count(),
        ];
    }
}
