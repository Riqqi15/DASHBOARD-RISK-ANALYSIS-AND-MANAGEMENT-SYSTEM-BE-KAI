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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class AssetCategoryBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame(3307, (int) config('database.connections.mysql.port'));
        $this->assertSame('rams_testing', config('database.connections.mysql.database'));

        Artisan::call('migrate:fresh', ['--force' => true]);

        $migration = require database_path(
            'migrations/2026_07_28_000003_make_asset_subsystem_id_required.php',
        );
        $migration->down();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Artisan::call('migrate:fresh', ['--force' => true]);

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
            if (in_array($field, ['asset_subsystem_id', 'asset_category_node_id', 'updated_at'], true)) {
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

        try {
            app(AssetCategoryResolver::class)->resolve(
                'Missing Group',
                'System',
                'Subsystem',
                'book.xlsx',
                'Sheet X',
                7,
            );
            $this->fail('Expected a missing alias target to fail resolution.');
        } catch (Throwable $exception) {
            $this->assertSame(RuntimeException::class, $exception::class);
            $this->assertStringContainsString('resolution conflict', $exception->getMessage());
            $this->assertStringContainsString('book.xlsx', $exception->getMessage());
            $this->assertStringContainsString('Sheet X', $exception->getMessage());
            $this->assertStringContainsString('row 7', $exception->getMessage());
            $this->assertStringContainsString('missing group', $exception->getMessage());
        }
    }

    public function test_unaliased_inactive_group_conflict_is_contextual_and_atomic(): void
    {
        AssetGroup::factory()->create([
            'name' => 'Inactive Group',
            'is_active' => false,
        ]);
        $asset = $this->legacyAsset([
            'aset_prasarana_sintel' => ' INACTIVE  GROUP ',
            'system' => 'New System',
            'subsystem' => 'New Subsystem',
        ]);
        $countsBefore = $this->categoryCounts();

        try {
            app(AssetCategoryBackfill::class)->run();
            $this->fail('Expected the inactive category to abort the backfill.');
        } catch (Throwable $exception) {
            $this->assertSame(RuntimeException::class, $exception::class);
            $this->assertStringContainsString('legacy-database', $exception->getMessage());
            $this->assertStringContainsString('assets', $exception->getMessage());
            $this->assertStringContainsString("row {$asset->id}", $exception->getMessage());
            $this->assertStringContainsString('inactive group', $exception->getMessage());
        }

        $this->assertSame($countsBefore, $this->categoryCounts());
        $this->assertNull($asset->fresh()->asset_subsystem_id);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
    }

    public function test_unaliased_soft_deleted_nested_category_conflict_is_contextual_and_atomic(): void
    {
        $group = AssetGroup::factory()->create(['name' => 'Existing Group']);
        $deletedSystem = AssetSystem::factory()->for($group)->create(['name' => 'Deleted System']);
        $deletedSystem->delete();
        $asset = $this->legacyAsset([
            'aset_prasarana_sintel' => 'Existing Group',
            'system' => " DELETED\tSYSTEM ",
            'subsystem' => 'New Subsystem',
        ]);
        $countsBefore = [
            'groups' => AssetGroup::withTrashed()->count(),
            'systems' => AssetSystem::withTrashed()->count(),
            'subsystems' => AssetSubsystem::withTrashed()->count(),
        ];

        try {
            app(AssetCategoryBackfill::class)->run();
            $this->fail('Expected the soft-deleted category to abort the backfill.');
        } catch (Throwable $exception) {
            $this->assertSame(RuntimeException::class, $exception::class);
            $this->assertStringContainsString('legacy-database', $exception->getMessage());
            $this->assertStringContainsString('assets', $exception->getMessage());
            $this->assertStringContainsString("row {$asset->id}", $exception->getMessage());
            $this->assertStringContainsString('existing group|deleted system', $exception->getMessage());
        }

        $this->assertSame($countsBefore, [
            'groups' => AssetGroup::withTrashed()->count(),
            'systems' => AssetSystem::withTrashed()->count(),
            'subsystems' => AssetSubsystem::withTrashed()->count(),
        ]);
        $this->assertNull($asset->fresh()->asset_subsystem_id);
        $this->assertDatabaseCount('asset_category_source_aliases', 0);
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

    public function test_backfill_retries_the_outer_asset_transaction_after_a_deadlock(): void
    {
        $suffix = Str::lower((string) Str::uuid());
        $groupName = "Retry Group {$suffix}";
        $systemName = "Retry System {$suffix}";
        $subsystemName = "Retry Subsystem {$suffix}";

        try {
            $process = $this->categoryResolverProcess([
                'retry-backfill',
                $groupName,
                $systemName,
                $subsystemName,
            ]);
            $process->setTimeout(30);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(2, $result['attempts']);
            $this->assertSame(1, $result['linked']);
            $this->assertSame(0, $result['skipped']);
            $this->assertNotNull($result['asset_subsystem_id']);
        } finally {
            $cleanup = $this->categoryResolverProcess([
                'cleanup',
                $groupName,
                $systemName,
                $subsystemName,
            ]);
            $cleanup->setTimeout(30);
            $cleanup->mustRun();
        }
    }

    public function test_failure_reports_committed_progress_and_original_diagnostic_context(): void
    {
        $first = $this->legacyAsset([
            'aset_prasarana_sintel' => 'First Valid Group',
            'system' => 'First Valid System',
            'subsystem' => 'First Valid Subsystem',
        ]);
        $second = $this->legacyAsset([
            'aset_prasarana_sintel' => 'Second Broken Group',
            'system' => 'Second Broken System',
            'subsystem' => 'Second Broken Subsystem',
        ]);
        AssetCategorySourceAlias::query()->create([
            'category_type' => 'group',
            'category_id' => 999999,
            'source_path' => 'Second Broken Group',
            'normalized_source_path' => 'second broken group',
            'workbook_name' => 'old.xlsx',
            'sheet_name' => 'Old Sheet',
            'first_imported_at' => now(),
            'last_imported_at' => now(),
        ]);

        $exitCode = Artisan::call('rams:backfill-asset-categories');
        $output = preg_replace('/\s+/u', ' ', Artisan::output()) ?? Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Backfill kategori aset gagal:', $output);
        $this->assertStringContainsString('Terhubung: 1', $output);
        $this->assertStringContainsString('Dilewati: 0', $output);
        $this->assertStringContainsString('legacy-database', $output);
        $this->assertStringContainsString('assets', $output);
        $this->assertStringContainsString("row {$second->id}", $output);
        $this->assertStringContainsString('second broken group', $output);
        $this->assertNotNull($first->fresh()->asset_subsystem_id);
        $this->assertNull($second->fresh()->asset_subsystem_id);
        $this->assertDatabaseHas('asset_category_source_aliases', [
            'category_type' => 'subsystem',
            'normalized_source_path' => 'first valid group|first valid system|first valid subsystem',
        ]);
    }

    public function test_two_independent_processes_resolve_one_unseen_path_without_duplicates(): void
    {
        $suffix = Str::lower((string) Str::uuid());
        $groupName = "Concurrent Group {$suffix}";
        $systemName = "Concurrent System {$suffix}";
        $subsystemName = "Concurrent Subsystem {$suffix}";
        $barrier = storage_path("framework/testing/category-resolver-{$suffix}");
        $processes = [];

        File::ensureDirectoryExists(dirname($barrier));

        try {
            foreach ([1, 2] as $worker) {
                $process = $this->categoryResolverProcess([
                    'resolve',
                    $groupName,
                    $systemName,
                    $subsystemName,
                    $barrier,
                    (string) $worker,
                ]);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 15;
            while (! File::exists("{$barrier}.1.ready") || ! File::exists("{$barrier}.2.ready")) {
                foreach ($processes as $process) {
                    if ($process->isTerminated()) {
                        $this->fail('Concurrency worker exited before the barrier: '.$process->getErrorOutput());
                    }
                }

                if (microtime(true) >= $deadline) {
                    $this->fail('Timed out waiting for concurrency workers to reach the barrier.');
                }

                usleep(10_000);
            }

            File::put("{$barrier}.go", 'go');
            $resolvedIds = [];

            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue(
                    $process->isSuccessful(),
                    trim($process->getErrorOutput().' '.$process->getOutput()),
                );
                $resolvedIds[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }

            $this->assertSame($resolvedIds[0], $resolvedIds[1]);
            $this->assertSame(1, AssetGroup::query()->where('normalized_name', mb_strtolower($groupName))->count());
            $this->assertSame(1, AssetSystem::query()->where('normalized_name', mb_strtolower($systemName))->count());
            $this->assertSame(1, AssetSubsystem::query()->where('normalized_name', mb_strtolower($subsystemName))->count());
            $this->assertSame(3, AssetCategorySourceAlias::query()
                ->whereIn('normalized_source_path', [
                    mb_strtolower($groupName),
                    mb_strtolower($groupName.'|'.$systemName),
                    mb_strtolower($groupName.'|'.$systemName.'|'.$subsystemName),
                ])
                ->count());
        } finally {
            File::put("{$barrier}.go", 'go');
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            $cleanup = $this->categoryResolverProcess([
                'cleanup',
                $groupName,
                $systemName,
                $subsystemName,
            ]);
            $cleanup->setTimeout(30);
            $cleanup->mustRun();

            File::delete([
                "{$barrier}.go",
                "{$barrier}.1.ready",
                "{$barrier}.2.ready",
            ]);
        }
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

    /** @param list<string> $arguments */
    private function categoryResolverProcess(array $arguments): Process
    {
        return new Process(
            [PHP_BINARY, base_path('tests/Support/AssetCategoryResolverProcess.php'), ...$arguments],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '3307',
                'DB_DATABASE' => 'rams_testing',
            ],
        );
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
