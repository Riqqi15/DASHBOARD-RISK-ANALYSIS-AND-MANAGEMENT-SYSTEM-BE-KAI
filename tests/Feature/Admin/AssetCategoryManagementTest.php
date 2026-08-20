<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AssetCategorySourceAlias;
use App\Models\AssetGroup;
use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\AuditLog;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AssetCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_category_management(): void
    {
        $this->get('/admin/asset-categories')->assertRedirect('/login');
    }

    public function test_unit_account_can_view_index_but_is_forbidden_from_every_mutation(): void
    {
        $user = User::factory()->unit()->create();
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();

        $this->actingAs($user)->get('/admin/asset-categories')->assertOk();

        $requests = [
            fn () => $this->actingAs($user)->post('/admin/asset-groups', []),
            fn () => $this->actingAs($user)->put("/admin/asset-groups/{$group->id}", []),
            fn () => $this->actingAs($user)->patch("/admin/asset-groups/{$group->id}/status", []),
            fn () => $this->actingAs($user)->delete("/admin/asset-groups/{$group->id}"),
            fn () => $this->actingAs($user)->post('/admin/asset-systems', []),
            fn () => $this->actingAs($user)->put("/admin/asset-systems/{$system->id}", []),
            fn () => $this->actingAs($user)->patch("/admin/asset-systems/{$system->id}/status", []),
            fn () => $this->actingAs($user)->delete("/admin/asset-systems/{$system->id}"),
            fn () => $this->actingAs($user)->post('/admin/asset-subsystems', []),
            fn () => $this->actingAs($user)->put("/admin/asset-subsystems/{$subsystem->id}", []),
            fn () => $this->actingAs($user)->patch("/admin/asset-subsystems/{$subsystem->id}/status", []),
            fn () => $this->actingAs($user)->delete("/admin/asset-subsystems/{$subsystem->id}"),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }
    }

    public function test_index_only_shows_legacy_categories_connected_to_the_selected_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1', 'name' => 'Daerah Operasi 1']);
        $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2', 'name' => 'Daerah Operasi 2']);
        $legacyGroup = AssetGroup::factory()->create(['unit_kerja_id' => null, 'name' => 'Legacy DAOP 1']);
        $legacySystem = AssetSystem::factory()->for($legacyGroup)->create();
        $legacySubsystem = AssetSubsystem::factory()->for($legacySystem)->create();
        Asset::factory()->for($daopOne, 'unitKerja')->for($legacySubsystem)->create();
        AssetGroup::factory()->create(['unit_kerja_id' => $daopOne->id, 'name' => 'Manual DAOP 1']);

        $this->actingAs($pusat)
            ->get('/admin/asset-categories?unit_kerja_id='.$daopTwo->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedUnitId', $daopTwo->id)
                ->has('groups', 0)
                ->where('selectedGroupId', null)
                ->where('selectedSystemId', null));

        $this->actingAs($pusat)
            ->get('/admin/asset-categories?unit_kerja_id='.$daopOne->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedUnitId', $daopOne->id)
                ->has('groups', 2)
                ->where('groups.0.name', 'Legacy DAOP 1')
                ->where('groups.1.name', 'Manual DAOP 1'));
    }

    public function test_index_returns_complete_ordered_hierarchy_counts_and_valid_selection(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $second = AssetGroup::factory()->create(['unit_kerja_id' => $unit->id, 'name' => 'Zulu', 'sort_order' => 20, 'is_active' => false]);
        $first = AssetGroup::factory()->create(['unit_kerja_id' => $unit->id, 'name' => 'Alpha', 'sort_order' => 10]);
        $firstSystem = AssetSystem::factory()->for($first)->create(['name' => 'First', 'sort_order' => 10, 'is_active' => false]);
        $secondSystem = AssetSystem::factory()->for($first)->create(['name' => 'Second', 'sort_order' => 20]);
        $subsystem = AssetSubsystem::factory()->for($firstSystem)->create(['name' => 'Child', 'is_active' => false]);
        Asset::factory()->for($unit, 'unitKerja')->for($subsystem)->create();
        AssetCategorySourceAlias::query()->create($this->aliasAttributes('group', $first->id, 'Alpha'));
        AssetCategorySourceAlias::query()->create($this->aliasAttributes('system', $firstSystem->id, 'Alpha|First'));
        $deleted = AssetGroup::factory()->create(['name' => 'Deleted']);
        $deleted->delete();

        $this->actingAs($pusat)->get("/admin/asset-categories?unit_kerja_id={$unit->id}&group={$first->id}&system={$firstSystem->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AssetCategories/Index', false)
                ->has('groups', 2)
                ->where('groups.0.id', $first->id)
                ->where('groups.0.is_active', true)
                ->where('groups.0.systems_count', 2)
                ->where('groups.0.aliases_count', 1)
                ->where('groups.0.systems.0.id', $firstSystem->id)
                ->where('groups.0.systems.0.is_active', false)
                ->where('groups.0.systems.0.subsystems_count', 1)
                ->where('groups.0.systems.0.aliases_count', 1)
                ->where('groups.0.systems.0.subsystems.0.id', $subsystem->id)
                ->where('groups.0.systems.0.subsystems.0.assets_count', 1)
                ->where('groups.1.id', $second->id)
                ->where('selectedGroupId', $first->id)
                ->where('selectedSystemId', $firstSystem->id)
                ->where('capabilities.manage', true));

        $this->actingAs($pusat)->get("/admin/asset-categories?unit_kerja_id={$unit->id}&group=999999&system=999999")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedGroupId', $first->id)
                ->where('selectedSystemId', $firstSystem->id));

        $this->actingAs($pusat)->get("/admin/asset-categories?unit_kerja_id={$unit->id}&group={$first->id}&system={$secondSystem->id}")
            ->assertInertia(fn (Assert $page) => $page->where('selectedSystemId', $secondSystem->id));
    }

    public function test_pusat_creates_all_levels_with_server_normalization_and_scoped_uniqueness(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);

        $this->actingAs($pusat)->post('/admin/asset-groups', [
            'unit_kerja_id' => $unit->id,
            'name' => "  Peralatan\u{00A0}\tDalam   Sinyal  ",
            'normalized_name' => 'malicious',
            'sort_order' => null,
        ])->assertRedirect('/admin/asset-categories?unit_kerja_id='.$unit->id);

        $group = AssetGroup::query()->where('name', 'Peralatan Dalam Sinyal')->firstOrFail();
        $this->assertSame('peralatan dalam sinyal', $group->normalized_name);
        $this->assertSame(0, $group->sort_order);
        $createdAudit = AuditLog::query()
            ->where('action', 'asset_category.created')
            ->where('auditable_type', $group->getMorphClass())
            ->where('auditable_id', $group->id)
            ->firstOrFail();
        $this->assertSame($pusat->id, $createdAudit->actor_id);
        $this->assertSame([], $createdAudit->old_values);
        $this->assertSame('group', $createdAudit->new_values['level']);
        $this->assertSame($group->id, $createdAudit->new_values['id']);
        $this->assertSame('Peralatan Dalam Sinyal', $createdAudit->new_values['name']);

        $this->actingAs($pusat)->post('/admin/asset-groups', [
            'unit_kerja_id' => $unit->id,
            'name' => ' PERALATAN   DALAM SINYAL ',
            'normalized_name' => 'unique-lie',
        ])->assertSessionHasErrors('normalized_name');

        $otherGroup = AssetGroup::factory()->create(['unit_kerja_id' => $unit->id, 'name' => 'Other Group']);
        $this->actingAs($pusat)->post('/admin/asset-systems', [
            'asset_group_id' => $group->id,
            'name' => "  Interlocking\u{2003}Elektrik ",
            'normalized_name' => 'malicious',
        ])->assertRedirect("/admin/asset-categories?group={$group->id}");
        $system = AssetSystem::query()->where('asset_group_id', $group->id)->where('name', 'Interlocking Elektrik')->firstOrFail();

        $this->actingAs($pusat)->post('/admin/asset-systems', [
            'asset_group_id' => $group->id,
            'name' => 'INTERLOCKING ELEKTRIK',
        ])->assertSessionHasErrors('normalized_name');
        $this->actingAs($pusat)->post('/admin/asset-systems', [
            'asset_group_id' => $otherGroup->id,
            'name' => 'INTERLOCKING ELEKTRIK',
        ])->assertSessionDoesntHaveErrors();

        $otherSystem = AssetSystem::query()->where('asset_group_id', $otherGroup->id)->firstOrFail();
        $this->actingAs($pusat)->post('/admin/asset-subsystems', [
            'asset_system_id' => $system->id,
            'name' => "  Local\n Control   Panel ",
        ])->assertRedirect("/admin/asset-categories?group={$group->id}&system={$system->id}");
        $this->assertDatabaseHas('asset_subsystems', [
            'asset_system_id' => $system->id,
            'name' => 'Local Control Panel',
            'normalized_name' => 'local control panel',
            'sort_order' => 0,
        ]);
        $this->actingAs($pusat)->post('/admin/asset-subsystems', [
            'asset_system_id' => $otherSystem->id,
            'name' => 'LOCAL CONTROL PANEL',
        ])->assertSessionDoesntHaveErrors();

        $group->update(['is_active' => false]);
        $this->actingAs($pusat)->post('/admin/asset-systems', [
            'asset_group_id' => $group->id,
            'name' => 'Rejected',
        ])->assertSessionHasErrors('asset_group_id');
        $system->update(['is_active' => false]);
        $this->actingAs($pusat)->post('/admin/asset-subsystems', [
            'asset_system_id' => $system->id,
            'name' => 'Rejected',
        ])->assertSessionHasErrors('asset_system_id');
    }

    public function test_pusat_can_override_and_reset_dashboard_color_with_hex_validation(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create([
            'dashboard_color' => '#FF0000',
            'dashboard_color_source' => 'excel',
        ]);

        $this->actingAs($pusat)->put("/admin/asset-groups/{$group->id}", [
            'name' => $group->name,
            'sort_order' => $group->sort_order,
            'dashboard_color' => '#12abef',
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame('#12ABEF', $group->fresh()->dashboard_color);
        $this->assertSame('manual', $group->fresh()->dashboard_color_source);

        $this->actingAs($pusat)->put("/admin/asset-groups/{$group->id}", [
            'name' => $group->name,
            'sort_order' => $group->sort_order,
            'dashboard_color' => 'merah',
        ])->assertSessionHasErrors('dashboard_color');

        $this->actingAs($pusat)->put("/admin/asset-groups/{$group->id}", [
            'name' => $group->name,
            'sort_order' => $group->sort_order,
            'dashboard_color' => '',
        ])->assertSessionDoesntHaveErrors();
        $this->assertNull($group->fresh()->dashboard_color);
        $this->assertNull($group->fresh()->dashboard_color_source);
    }

    public function test_renames_preserve_ids_relations_assets_aliases_and_audit_old_and_new_values(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create(['name' => 'Old Group', 'sort_order' => 1]);
        $otherGroup = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create(['name' => 'Old System', 'sort_order' => 2]);
        $otherSystem = AssetSystem::factory()->for($otherGroup)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'Old Subsystem', 'sort_order' => 3]);
        $asset = Asset::factory()->for($subsystem)->create();
        foreach ([['group', $group->id], ['system', $system->id], ['subsystem', $subsystem->id]] as [$type, $id]) {
            AssetCategorySourceAlias::query()->create($this->aliasAttributes($type, $id, "Alias {$type}"));
        }

        $this->actingAs($pusat)->put("/admin/asset-groups/{$group->id}", ['name' => 'New Group', 'sort_order' => 11]);
        $this->actingAs($pusat)->put("/admin/asset-systems/{$system->id}", [
            'name' => 'New System', 'sort_order' => 12, 'asset_group_id' => $otherGroup->id,
        ]);
        $this->actingAs($pusat)->put("/admin/asset-subsystems/{$subsystem->id}", [
            'name' => 'New Subsystem', 'sort_order' => 13, 'asset_system_id' => $otherSystem->id,
        ]);

        $this->assertSame($group->id, $system->fresh()->asset_group_id);
        $this->assertSame($system->id, $subsystem->fresh()->asset_system_id);
        $this->assertSame($subsystem->id, $asset->fresh()->asset_subsystem_id);
        $this->assertSame(3, AssetCategorySourceAlias::query()->count());
        $this->assertDatabaseHas('asset_groups', ['id' => $group->id, 'name' => 'New Group', 'sort_order' => 11]);
        $this->assertDatabaseHas('asset_systems', ['id' => $system->id, 'name' => 'New System', 'sort_order' => 12]);
        $this->assertDatabaseHas('asset_subsystems', ['id' => $subsystem->id, 'name' => 'New Subsystem', 'sort_order' => 13]);

        foreach ([['group', $group, 'Old Group', 'New Group'], ['system', $system, 'Old System', 'New System'], ['subsystem', $subsystem, 'Old Subsystem', 'New Subsystem']] as [$level, $model, $oldName, $newName]) {
            $audit = AuditLog::query()
                ->where('action', 'asset_category.updated')
                ->where('auditable_type', $model->getMorphClass())
                ->where('auditable_id', $model->id)
                ->firstOrFail();
            $this->assertSame($pusat->id, $audit->actor_id);
            $this->assertSame($level, $audit->old_values['level']);
            $this->assertSame($oldName, $audit->old_values['name']);
            $this->assertSame($newName, $audit->new_values['name']);
        }
    }

    public function test_status_changes_only_activity_and_audits_each_level(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();

        foreach ([['asset-groups', 'group', $group], ['asset-systems', 'system', $system], ['asset-subsystems', 'subsystem', $subsystem]] as [$uri, $level, $model]) {
            $this->actingAs($pusat)->patch("/admin/{$uri}/{$model->id}/status", ['is_active' => false])->assertSessionDoesntHaveErrors();
            $this->assertFalse($model->fresh()->is_active);
            $audit = AuditLog::query()->where('action', 'asset_category.status_changed')->where('auditable_type', $model->getMorphClass())->where('auditable_id', $model->id)->firstOrFail();
            $this->assertSame($level, $audit->old_values['level']);
            $this->assertTrue($audit->old_values['is_active']);
            $this->assertFalse($audit->new_values['is_active']);
        }

        $this->assertSame(1, AssetGroup::query()->whereKey($group->id)->count());
        $this->assertSame(1, AssetSystem::query()->whereKey($system->id)->count());
        $this->assertSame(1, AssetSubsystem::query()->whereKey($subsystem->id)->count());
    }

    public function test_unchanged_status_does_not_create_an_audit_for_any_level(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create(['is_active' => true]);
        $system = AssetSystem::factory()->for($group)->create(['is_active' => true]);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['is_active' => true]);

        foreach ([
            ['asset-groups', $group, 'Status kelompok aset tidak berubah.'],
            ['asset-systems', $system, 'Status sistem aset tidak berubah.'],
            ['asset-subsystems', $subsystem, 'Status subsistem aset tidak berubah.'],
        ] as [$uri, $model, $message]) {
            $this->actingAs($pusat)
                ->patch("/admin/{$uri}/{$model->id}/status", ['is_active' => true])
                ->assertRedirect()
                ->assertSessionHas('success', $message);

            $this->assertSame(0, AuditLog::query()
                ->where('action', 'asset_category.status_changed')
                ->where('auditable_type', $model->getMorphClass())
                ->where('auditable_id', $model->id)
                ->count());
        }
    }

    public function test_category_audits_are_isolated_by_morph_type_when_ids_overlap(): void
    {
        $pusat = User::factory()->pusat()->create();
        $sharedId = 987654;
        $group = AssetGroup::factory()->create(['id' => $sharedId]);
        $system = AssetSystem::factory()->for($group)->create(['id' => $sharedId]);
        $subsystem = AssetSubsystem::factory()->for($system)->create(['id' => $sharedId]);

        $this->assertSame($group->id, $system->id);
        $this->assertSame($system->id, $subsystem->id);

        foreach ([['asset-groups', $group], ['asset-systems', $system], ['asset-subsystems', $subsystem]] as [$uri, $model]) {
            $this->actingAs($pusat)
                ->patch("/admin/{$uri}/{$model->id}/status", ['is_active' => false])
                ->assertSessionDoesntHaveErrors();

            $this->assertSame(1, AuditLog::query()
                ->where('action', 'asset_category.status_changed')
                ->where('auditable_type', $model->getMorphClass())
                ->where('auditable_id', $model->id)
                ->count());
        }
    }

    public function test_mutations_lock_categories_and_revalidate_active_parents_inside_transactions(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(mb_strtolower($query->sql), 'for update')) {
                $queries[] = mb_strtolower($query->sql);
            }
        });

        $this->actingAs($pusat)->post('/admin/asset-systems', [
            'asset_group_id' => $group->id,
            'name' => 'Locked Child System',
        ])->assertSessionDoesntHaveErrors();
        $this->actingAs($pusat)->post('/admin/asset-subsystems', [
            'asset_system_id' => $system->id,
            'name' => 'Locked Child Subsystem',
        ])->assertSessionDoesntHaveErrors();

        foreach ([['asset-groups', $group], ['asset-systems', $system], ['asset-subsystems', $subsystem]] as [$uri, $model]) {
            $this->actingAs($pusat)->patch("/admin/{$uri}/{$model->id}/status", ['is_active' => false])->assertSessionDoesntHaveErrors();
            $this->actingAs($pusat)->delete("/admin/{$uri}/{$model->id}");
        }

        $lockingSql = implode("\n", $queries);
        $this->assertStringContainsString('from `asset_groups`', $lockingSql);
        $this->assertStringContainsString('from `asset_systems`', $lockingSql);
        $this->assertStringContainsString('from `asset_subsystems`', $lockingSql);
        $this->assertGreaterThanOrEqual(8, count($queries));
    }

    public function test_concurrent_child_creation_and_parent_deletion_cannot_both_succeed(): void
    {
        $scope = 'category-race-'.Str::lower((string) Str::uuid());
        $barrier = storage_path("framework/testing/{$scope}");
        $processes = [];
        File::ensureDirectoryExists(dirname($barrier));

        try {
            $setupProcess = $this->categoryMutationProcess(['setup', $scope]);
            $setupProcess->setTimeout(15);
            $setupProcess->mustRun();
            $setup = json_decode($setupProcess->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            foreach ([['create-system', 'creator'], ['delete-group', 'deleter']] as [$action, $worker]) {
                $process = $this->categoryMutationProcess([$action, (string) $setup['group_id'], (string) $setup['user_id'], $scope, $barrier, $worker]);
                $process->setTimeout(15);
                $process->start();
                $processes[$worker] = $process;
            }

            $this->releaseCategoryMutationBarrier($barrier, $processes);
            $results = $this->waitForCategoryMutationProcesses($processes);
            $group = AssetGroup::withTrashed()->findOrFail($setup['group_id']);
            $liveChildren = AssetSystem::query()->where('asset_group_id', $group->id)->count();

            $this->assertNotSame($results['creator']['success'], $results['deleter']['success']);
            $this->assertFalse($group->trashed() && $liveChildren > 0);
            $this->assertSame($group->trashed() ? 1 : 0, AuditLog::query()
                ->where('action', 'asset_category.deleted')
                ->where('auditable_type', $group->getMorphClass())
                ->where('auditable_id', $group->id)
                ->count());
        } finally {
            $this->cleanupCategoryMutationRace($scope, $barrier, $processes);
        }
    }

    public function test_concurrent_duplicate_deletes_create_at_most_one_delete_audit(): void
    {
        $scope = 'category-delete-race-'.Str::lower((string) Str::uuid());
        $barrier = storage_path("framework/testing/{$scope}");
        $processes = [];
        File::ensureDirectoryExists(dirname($barrier));

        try {
            $setupProcess = $this->categoryMutationProcess(['setup', $scope]);
            $setupProcess->setTimeout(15);
            $setupProcess->mustRun();
            $setup = json_decode($setupProcess->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            foreach (['deleter-1', 'deleter-2'] as $worker) {
                $process = $this->categoryMutationProcess(['delete-group', (string) $setup['group_id'], (string) $setup['user_id'], $scope, $barrier, $worker]);
                $process->setTimeout(15);
                $process->start();
                $processes[$worker] = $process;
            }

            $this->releaseCategoryMutationBarrier($barrier, $processes);
            $results = $this->waitForCategoryMutationProcesses($processes);
            $group = AssetGroup::withTrashed()->findOrFail($setup['group_id']);

            $this->assertSame(1, collect($results)->where('success', true)->count());
            $this->assertTrue($group->trashed());
            $this->assertSame(1, AuditLog::query()
                ->where('action', 'asset_category.deleted')
                ->where('auditable_type', $group->getMorphClass())
                ->where('auditable_id', $group->id)
                ->count());
        } finally {
            $this->cleanupCategoryMutationRace($scope, $barrier, $processes);
        }
    }

    public function test_unused_categories_soft_delete_and_are_audited(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->create();
        $subsystem = AssetSubsystem::factory()->create();

        foreach ([['asset-subsystems', $subsystem], ['asset-systems', $system], ['asset-groups', $group]] as [$uri, $model]) {
            $this->actingAs($pusat)->delete("/admin/{$uri}/{$model->id}")->assertSessionDoesntHaveErrors();
            $this->assertNotNull($model->fresh()->deleted_at);
            $audit = AuditLog::query()->where('action', 'asset_category.deleted')->where('auditable_type', $model->getMorphClass())->where('auditable_id', $model->id)->firstOrFail();
            $this->assertSame($pusat->id, $audit->actor_id);
            $this->assertSame([], $audit->new_values);
            $this->actingAs($pusat)->patch("/admin/{$uri}/{$model->id}/status", ['is_active' => true])->assertNotFound();
            $this->assertNotNull($model->fresh()->deleted_at);
        }
    }

    public function test_deletes_are_blocked_by_children_assets_and_aliases_without_delete_audits(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $subsystem = AssetSubsystem::factory()->for($system)->create();
        Asset::factory()->for($subsystem)->create();

        foreach ([['asset-groups', $group, 'sistem'], ['asset-systems', $system, 'subsistem'], ['asset-subsystems', $subsystem, 'aset']] as [$uri, $model, $blocker]) {
            $response = $this->actingAs($pusat)->from('/admin/asset-categories')->delete("/admin/{$uri}/{$model->id}");
            $response->assertRedirect('/admin/asset-categories')->assertSessionHasErrors('category');
            $message = mb_strtolower($response->getSession()->get('errors')->first('category'));
            $this->assertStringContainsString($blocker, $message);
            $this->assertStringContainsString('nonaktifkan', $message);
            $this->assertNull($model->fresh()->deleted_at);
        }

        foreach ([['asset-groups', 'group', AssetGroup::factory()->create()], ['asset-systems', 'system', AssetSystem::factory()->create()], ['asset-subsystems', 'subsystem', AssetSubsystem::factory()->create()]] as [$uri, $type, $model]) {
            AssetCategorySourceAlias::query()->create($this->aliasAttributes($type, $model->id, "Only {$type}"));
            $response = $this->actingAs($pusat)->delete("/admin/{$uri}/{$model->id}");
            $response->assertSessionHasErrors('category');
            $message = mb_strtolower($response->getSession()->get('errors')->first('category'));
            $this->assertStringContainsString('alias', $message);
            $this->assertStringContainsString('nonaktifkan', $message);
            $this->assertNull($model->fresh()->deleted_at);
        }

        foreach ([$group, $system, $subsystem] as $model) {
            $this->assertSame(0, AuditLog::query()
                ->where('action', 'asset_category.deleted')
                ->where('auditable_type', $model->getMorphClass())
                ->where('auditable_id', $model->id)
                ->count());
        }
    }

    public function test_soft_deleted_dependents_still_block_deletion(): void
    {
        $pusat = User::factory()->pusat()->create();
        $group = AssetGroup::factory()->create();
        $system = AssetSystem::factory()->for($group)->create();
        $system->delete();
        $this->actingAs($pusat)->delete("/admin/asset-groups/{$group->id}")->assertSessionHasErrors('category');

        $liveSystem = AssetSystem::factory()->create();
        $subsystem = AssetSubsystem::factory()->for($liveSystem)->create();
        $subsystem->delete();
        $this->actingAs($pusat)->delete("/admin/asset-systems/{$liveSystem->id}")->assertSessionHasErrors('category');

        $liveSubsystem = AssetSubsystem::factory()->create();
        $asset = Asset::factory()->for($liveSubsystem)->create();
        $asset->delete();
        $this->actingAs($pusat)->delete("/admin/asset-subsystems/{$liveSubsystem->id}")->assertSessionHasErrors('category');
    }

    public function test_duplicate_update_returns_validation_errors_instead_of_a_database_exception(): void
    {
        $pusat = User::factory()->pusat()->create();
        AssetGroup::factory()->create(['name' => 'Duplicate']);
        $target = AssetGroup::factory()->create(['name' => 'Target']);

        $this->actingAs($pusat)->put("/admin/asset-groups/{$target->id}", [
            'name' => " DUPLICATE\t",
            'normalized_name' => 'safe-looking-value',
        ])->assertSessionHasErrors('normalized_name');

        $this->assertSame('Target', $target->fresh()->name);
    }

    public function test_policies_allow_only_pusat_for_every_category_ability(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = User::factory()->unit()->create();
        $models = [AssetGroup::factory()->create(), AssetSystem::factory()->create(), AssetSubsystem::factory()->create()];

        foreach ($models as $model) {
            foreach (['view', 'update', 'delete', 'status'] as $ability) {
                $this->assertTrue(Gate::forUser($pusat)->allows($ability, $model));
                $this->assertFalse(Gate::forUser($unit)->allows($ability, $model));
            }
            foreach (['viewAny', 'create'] as $ability) {
                $this->assertTrue(Gate::forUser($pusat)->allows($ability, $model::class));
                $this->assertFalse(Gate::forUser($unit)->allows($ability, $model::class));
            }
        }
    }

    /** @return array<string, mixed> */
    private function aliasAttributes(string $type, int $id, string $path): array
    {
        return [
            'category_type' => $type,
            'category_id' => $id,
            'source_path' => $path,
            'normalized_source_path' => mb_strtolower($path),
            'workbook_name' => 'test.xlsx',
            'sheet_name' => 'Sheet1',
            'first_imported_at' => now(),
            'last_imported_at' => now(),
        ];
    }

    /** @param list<string> $arguments */
    private function categoryMutationProcess(array $arguments): Process
    {
        return new Process(
            [PHP_BINARY, base_path('tests/Support/AssetCategoryMutationProcess.php'), ...$arguments],
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

    /** @param array<string, Process> $processes */
    private function releaseCategoryMutationBarrier(string $barrier, array $processes): void
    {
        $deadline = microtime(true) + 10;
        foreach (array_keys($processes) as $worker) {
            while (! File::exists("{$barrier}.{$worker}.ready")) {
                if ($processes[$worker]->isTerminated()) {
                    $this->fail("Category mutation worker {$worker} exited before the barrier: ".$processes[$worker]->getErrorOutput());
                }
                if (microtime(true) >= $deadline) {
                    $this->fail('Timed out waiting for category mutation workers.');
                }
                usleep(10_000);
            }
        }

        File::put("{$barrier}.go", 'go');
    }

    /** @param array<string, Process> $processes
     * @return array<string, array{success: bool}>
     */
    private function waitForCategoryMutationProcesses(array $processes): array
    {
        $results = [];
        foreach ($processes as $worker => $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), trim($process->getErrorOutput().' '.$process->getOutput()));
            $results[$worker] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    /** @param array<string, Process> $processes */
    private function cleanupCategoryMutationRace(string $scope, string $barrier, array $processes): void
    {
        File::put("{$barrier}.go", 'go');
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        $cleanup = $this->categoryMutationProcess(['cleanup', $scope]);
        $cleanup->setTimeout(15);
        $cleanup->mustRun();

        File::delete(array_merge(
            ["{$barrier}.go"],
            collect(array_keys($processes))->map(fn (string $worker): string => "{$barrier}.{$worker}.ready")->all(),
        ));
    }
}
