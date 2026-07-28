<?php

namespace Tests\Feature;

use App\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AssetCategoryRequiredMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertMysqlTestDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        $this->assertMysqlTestDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_up_rejects_a_live_asset_without_a_category_before_altering_the_column(): void
    {
        $migration = $this->requiredCategoryMigration();
        $migration->down();
        $asset = Asset::factory()->create(['asset_subsystem_id' => null]);

        try {
            $migration->up();
            $this->fail('Expected the migration to reject an asset without a category.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Cannot make assets.asset_subsystem_id required: 1 asset(s), including soft-deleted assets, still have NULL category linkage.',
                $exception->getMessage(),
            );
            $this->assertSame('YES', $this->assetSubsystemColumn()->is_nullable);
            $this->assertCategoryForeignKeyAndIndexesExist();
        } finally {
            DB::table('assets')->where('id', $asset->id)->delete();
        }
    }

    public function test_up_rejects_a_soft_deleted_asset_without_a_category(): void
    {
        $migration = $this->requiredCategoryMigration();
        $migration->down();
        $asset = Asset::factory()->create(['asset_subsystem_id' => null]);
        $asset->delete();

        try {
            $migration->up();
            $this->fail('Expected the migration to include soft-deleted assets in its preflight.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Cannot make assets.asset_subsystem_id required: 1 asset(s), including soft-deleted assets, still have NULL category linkage.',
                $exception->getMessage(),
            );
            $this->assertSame(1, Asset::withTrashed()->whereNull('asset_subsystem_id')->count());
            $this->assertSame('YES', $this->assetSubsystemColumn()->is_nullable);
            $this->assertCategoryForeignKeyAndIndexesExist();
        } finally {
            DB::table('assets')->where('id', $asset->id)->delete();
        }
    }

    public function test_up_and_down_toggle_nullability_without_changing_type_foreign_key_or_indexes(): void
    {
        $migration = $this->requiredCategoryMigration();
        $migration->down();
        $asset = Asset::factory()->create();

        $this->assertSame('YES', $this->assetSubsystemColumn()->is_nullable);

        $migration->up();

        $this->assertSame('NO', $this->assetSubsystemColumn()->is_nullable);
        $this->assertSame('bigint unsigned', $this->assetSubsystemColumn()->column_type);
        $this->assertCategoryForeignKeyAndIndexesExist();
        $this->assertSame($asset->asset_subsystem_id, $asset->fresh()->asset_subsystem_id);

        $migration->down();

        $this->assertSame('YES', $this->assetSubsystemColumn()->is_nullable);
        $this->assertSame('bigint unsigned', $this->assetSubsystemColumn()->column_type);
        $this->assertCategoryForeignKeyAndIndexesExist();
    }

    private function requiredCategoryMigration(): Migration
    {
        $path = database_path('migrations/2026_07_28_000003_make_asset_subsystem_id_required.php');

        $this->assertFileExists($path, 'The required-category migration has not been implemented.');

        return require $path;
    }

    private function assetSubsystemColumn(): object
    {
        return DB::selectOne(<<<'SQL'
            SELECT IS_NULLABLE AS is_nullable, COLUMN_TYPE AS column_type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'assets'
              AND COLUMN_NAME = 'asset_subsystem_id'
            SQL);
    }

    private function assertCategoryForeignKeyAndIndexesExist(): void
    {
        $foreignKeyCount = DB::scalar(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'assets'
              AND COLUMN_NAME = 'asset_subsystem_id'
              AND REFERENCED_TABLE_NAME = 'asset_subsystems'
            SQL);
        $indexedColumnSets = DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'assets')
            ->groupBy('INDEX_NAME')
            ->pluck(DB::raw("GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')"))
            ->all();

        $this->assertSame(1, (int) $foreignKeyCount);
        $this->assertContains('asset_subsystem_id', $indexedColumnSets);
        $this->assertContains('unit_kerja_id,asset_subsystem_id', $indexedColumnSets);
    }

    private function assertMysqlTestDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame(3307, (int) config('database.connections.mysql.port'));
        $this->assertSame('rams_testing', config('database.connections.mysql.database'));
    }
}
