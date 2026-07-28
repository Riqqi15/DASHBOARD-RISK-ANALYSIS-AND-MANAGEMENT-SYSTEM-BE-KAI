<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssetCategorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_hierarchy_and_asset_relation_are_persisted(): void
    {
        $group = AssetGroup::factory()->create([
            'name' => "\u{00A0} Peralatan\tDalam   Sinyal Elektrik \u{2003}",
        ]);
        $system = AssetSystem::factory()->for($group)->create([
            'name' => '  Interlocking   Elektrik  ',
        ]);
        $subsystem = AssetSubsystem::factory()->for($system)->create([
            'name' => '  Interlocking   Elektrik  ',
        ]);
        $asset = Asset::factory()->for($subsystem, 'assetSubsystem')->create();

        $this->assertSame('Peralatan Dalam Sinyal Elektrik', $group->name);
        $this->assertSame('peralatan dalam sinyal elektrik', $group->normalized_name);
        $this->assertSame('Interlocking Elektrik', $system->name);
        $this->assertSame('interlocking elektrik', $system->normalized_name);
        $this->assertSame('Interlocking Elektrik', $subsystem->name);
        $this->assertSame('interlocking elektrik', $subsystem->normalized_name);
        $this->assertTrue($asset->assetSubsystem->is($subsystem));
        $this->assertTrue($subsystem->assetSystem->is($system));
        $this->assertTrue($system->assetGroup->is($group));
    }

    public function test_category_schema_and_source_alias_casts_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('asset_groups', [
            'id',
            'name',
            'normalized_name',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('asset_systems', [
            'id',
            'asset_group_id',
            'name',
            'normalized_name',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('asset_subsystems', [
            'id',
            'asset_system_id',
            'name',
            'normalized_name',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('asset_category_source_aliases', [
            'id',
            'category_type',
            'category_id',
            'source_path',
            'normalized_source_path',
            'workbook_name',
            'sheet_name',
            'first_imported_at',
            'last_imported_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumn('assets', 'asset_subsystem_id'));

        $group = AssetGroup::factory()->create(['sort_order' => 7, 'is_active' => 1]);
        $alias = AssetCategorySourceAlias::query()->create([
            'category_type' => 'group',
            'category_id' => $group->id,
            'source_path' => 'Workbook.xlsx/Sheet 1/Group',
            'normalized_source_path' => 'workbook.xlsx/sheet 1/group',
            'workbook_name' => 'Workbook.xlsx',
            'sheet_name' => 'Sheet 1',
            'first_imported_at' => '2026-07-27 09:00:00',
            'last_imported_at' => '2026-07-28 09:00:00',
        ]);

        $this->assertTrue($group->is_active);
        $this->assertSame(7, $group->sort_order);
        $this->assertSame('2026-07-27 09:00:00', $alias->first_imported_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-28 09:00:00', $alias->last_imported_at->format('Y-m-d H:i:s'));
    }

    public function test_category_names_are_unique_within_their_parent_only(): void
    {
        $firstGroup = AssetGroup::factory()->create();
        $secondGroup = AssetGroup::factory()->create();
        $firstSystem = AssetSystem::factory()->for($firstGroup)->create(['name' => 'Interlocking Elektrik']);

        $this->assertDuplicateKeyRejected(fn () => AssetSystem::factory()->for($firstGroup)->create([
            'name' => '  INTERLOCKING   ELEKTRIK ',
        ]));

        $secondSystem = AssetSystem::factory()->for($secondGroup)->create(['name' => 'Interlocking Elektrik']);
        AssetSubsystem::factory()->for($firstSystem)->create(['name' => 'Local Control Panel']);

        $this->assertDuplicateKeyRejected(fn () => AssetSubsystem::factory()->for($firstSystem)->create([
            'name' => ' LOCAL   CONTROL PANEL ',
        ]));

        $sameNameInAnotherSystem = AssetSubsystem::factory()->for($secondSystem)->create([
            'name' => 'Local Control Panel',
        ]);

        $this->assertSame('local control panel', $sameNameInAnotherSystem->normalized_name);
    }

    public function test_asset_group_names_are_unique_globally_after_normalization(): void
    {
        AssetGroup::factory()->create(['name' => 'Peralatan Dalam Sinyal Elektrik']);

        $this->assertDuplicateKeyRejected(fn () => AssetGroup::factory()->create([
            'name' => " \tPERALATAN   DALAM SINYAL ELEKTRIK  ",
        ]));
    }

    public function test_source_alias_paths_are_unique_within_category_type_only(): void
    {
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();

        AssetCategorySourceAlias::query()->create([
            'category_type' => 'group',
            'category_id' => $group->id,
            'source_path' => 'Workbook.xlsx/Sheet 1/Group',
            'normalized_source_path' => 'workbook.xlsx/sheet 1/group',
            'workbook_name' => 'Workbook.xlsx',
            'sheet_name' => 'Sheet 1',
            'first_imported_at' => '2026-07-27 09:00:00',
            'last_imported_at' => '2026-07-28 09:00:00',
        ]);

        $this->assertDuplicateKeyRejected(fn () => AssetCategorySourceAlias::query()->create([
            'category_type' => 'group',
            'category_id' => $group->id,
            'source_path' => 'WORKBOOK.xlsx/SHEET 1/GROUP',
            'normalized_source_path' => 'workbook.xlsx/sheet 1/group',
            'workbook_name' => 'WORKBOOK.xlsx',
            'sheet_name' => 'SHEET 1',
            'first_imported_at' => '2026-07-27 10:00:00',
            'last_imported_at' => '2026-07-28 10:00:00',
        ]));

        $systemAlias = AssetCategorySourceAlias::query()->create([
            'category_type' => 'system',
            'category_id' => $system->id,
            'source_path' => 'Workbook.xlsx/Sheet 1/Group',
            'normalized_source_path' => 'workbook.xlsx/sheet 1/group',
            'workbook_name' => 'Workbook.xlsx',
            'sheet_name' => 'Sheet 1',
            'first_imported_at' => '2026-07-27 09:00:00',
            'last_imported_at' => '2026-07-28 09:00:00',
        ]);

        $this->assertSame('system', $systemAlias->category_type);
        $this->assertDatabaseCount('asset_category_source_aliases', 2);
    }

    public function test_asset_category_relation_is_required_after_the_final_migration(): void
    {
        $column = collect(Schema::getColumns('assets'))
            ->firstWhere('name', 'asset_subsystem_id');

        $this->assertFalse($column['nullable']);
        $this->assertMysqlError(
            1048,
            fn () => Asset::factory()->create(['asset_subsystem_id' => null]),
        );
    }

    public function test_database_restricts_deleting_categories_that_are_in_use(): void
    {
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();
        Asset::factory()->for($subsystem, 'assetSubsystem')->create();

        $this->assertRestrictedDeleteRejected(fn () => $group->forceDelete());
        $this->assertRestrictedDeleteRejected(fn () => $system->forceDelete());
        $this->assertRestrictedDeleteRejected(fn () => $subsystem->forceDelete());
    }

    public function test_all_category_levels_support_soft_deletes(): void
    {
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();

        $group->delete();
        $system->delete();
        $subsystem->delete();

        $this->assertSoftDeleted('asset_groups', ['id' => $group->id]);
        $this->assertSoftDeleted('asset_systems', ['id' => $system->id]);
        $this->assertSoftDeleted('asset_subsystems', ['id' => $subsystem->id]);
    }

    public function test_ordered_relations_return_categories_by_sort_order_then_name(): void
    {
        $group = AssetGroup::factory()->create();
        $systemB = AssetSystem::factory()->for($group)->create(['name' => 'Beta', 'sort_order' => 2]);
        $systemA = AssetSystem::factory()->for($group)->create(['name' => 'Alpha', 'sort_order' => 2]);
        $systemFirst = AssetSystem::factory()->for($group)->create(['name' => 'Zulu', 'sort_order' => 1]);

        AssetSubsystem::factory()->for($systemFirst)->create(['name' => 'Beta', 'sort_order' => 2]);
        AssetSubsystem::factory()->for($systemFirst)->create(['name' => 'Alpha', 'sort_order' => 2]);
        AssetSubsystem::factory()->for($systemFirst)->create(['name' => 'Zulu', 'sort_order' => 1]);

        $this->assertSame(
            [$systemFirst->id, $systemA->id, $systemB->id],
            $group->systems()->pluck('id')->all(),
        );
        $this->assertSame(
            ['Zulu', 'Alpha', 'Beta'],
            $systemFirst->subsystems()->pluck('name')->all(),
        );
    }

    public function test_asset_factory_builds_exactly_one_consistent_category_chain(): void
    {
        $asset = Asset::factory()->create();

        $this->assertNotNull($asset->asset_subsystem_id);
        $this->assertTrue($asset->assetSubsystem->assets->contains($asset));
        $this->assertDatabaseCount('asset_groups', 1);
        $this->assertDatabaseCount('asset_systems', 1);
        $this->assertDatabaseCount('asset_subsystems', 1);

        $subsystem = AssetSubsystem::factory()->create();
        $categoryCounts = [
            AssetGroup::query()->count(),
            AssetSystem::query()->count(),
            AssetSubsystem::query()->count(),
        ];
        $relatedAsset = Asset::factory()->for($subsystem, 'assetSubsystem')->create();

        $this->assertTrue($relatedAsset->assetSubsystem->is($subsystem));
        $this->assertSame($categoryCounts, [
            AssetGroup::query()->count(),
            AssetSystem::query()->count(),
            AssetSubsystem::query()->count(),
        ]);
    }

    private function assertDuplicateKeyRejected(Closure $operation): void
    {
        $this->assertMysqlError(1062, $operation);
    }

    private function assertRestrictedDeleteRejected(Closure $operation): void
    {
        $this->assertMysqlError(1451, $operation);
    }

    private function assertMysqlError(int $expectedErrorNumber, Closure $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame(
                $expectedErrorNumber,
                $exception->errorInfo[1] ?? null,
                'The database rejected the query for an unexpected reason.',
            );

            return;
        }

        $this->fail('Expected the database to reject the query.');
    }
}
