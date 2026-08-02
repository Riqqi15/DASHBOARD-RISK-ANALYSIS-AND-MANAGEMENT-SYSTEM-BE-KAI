# Dashboard Asset Category Hierarchy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the dashboard render every active asset category, system, and subsystem, including empty nodes, while keeping asset and unit totals scoped to the authorized area.

**Architecture:** Laravel builds a complete `asset_hierarchy` Inertia prop from the normalized category tables. It aggregates scoped asset counts at subsystem level and rolls them up to systems and groups. Vue renders this backend-owned structure with the existing card layout and sends the subsystem ID when opening Trouble Report.

**Tech Stack:** Laravel 13, Eloquent, Inertia.js 3, Vue 3, Vitest, PHPUnit, MySQL 8.4

---

## File structure

- Modify `app/Services/RamsDashboardQuery.php`: build the active hierarchy and scope aggregate counts.
- Modify `app/Http/Requests/RamsAreaRequest.php`: validate an optional subsystem ID.
- Modify `app/Http/Controllers/RamsDashboardController.php`: forward the subsystem ID to the query service.
- Modify `resources/js/pages/dashboard/Dashboard.vue`: render `asset_hierarchy` and empty child states.
- Modify `tests/Feature/RamsDashboardBackendTest.php`: cover empty nodes, active filtering, area scope, and exact subsystem selection.
- Modify `tests/js/Dashboard.test.js`: cover backend-owned hierarchy rendering and navigation.

### Task 1: Add the backend hierarchy contract

**Files:**
- Modify: `tests/Feature/RamsDashboardBackendTest.php`
- Modify: `app/Services/RamsDashboardQuery.php`

- [ ] **Step 1: Write the failing hierarchy feature test**

Import `Asset`, `AssetGroup`, `AssetSystem`, and `AssetSubsystem`. Add a test that creates active empty nodes, an inactive branch, and assets in two units:

```php
public function test_dashboard_includes_the_complete_active_hierarchy_with_area_scoped_totals(): void
{
    $pusat = User::factory()->pusat()->create(['is_active' => true]);
    $daopOne = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
    $daopTwo = UnitKerja::factory()->create(['code' => 'DAOP-2', 'is_active' => true]);

    $group = AssetGroup::factory()->create(['name' => 'Active Group', 'sort_order' => 1]);
    $system = AssetSystem::factory()->for($group)->create(['name' => 'Active System', 'sort_order' => 1]);
    $populated = AssetSubsystem::factory()->for($system)->create(['name' => 'Populated', 'sort_order' => 1]);
    $empty = AssetSubsystem::factory()->for($system)->create(['name' => 'Empty', 'sort_order' => 2]);
    $emptySystem = AssetSystem::factory()->for($group)->create(['name' => 'Empty System', 'sort_order' => 2]);
    $emptyGroup = AssetGroup::factory()->create(['name' => 'Empty Group', 'sort_order' => 2]);

    $inactiveGroup = AssetGroup::factory()->create(['name' => 'Inactive Group', 'is_active' => false]);
    AssetSystem::factory()->for($inactiveGroup)->create();

    Asset::factory()->for($daopOne)->for($populated, 'assetSubsystem')->create(['jumlah_unit' => 3]);
    Asset::factory()->for($daopTwo)->for($populated, 'assetSubsystem')->create(['jumlah_unit' => 7]);

    $this->actingAs($pusat)
        ->get('/dashboard?area=DAOP1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('asset_hierarchy', 2)
            ->where('asset_hierarchy.0.id', $group->id)
            ->where('asset_hierarchy.0.assetCount', 1)
            ->where('asset_hierarchy.0.unitCount', 3)
            ->where('asset_hierarchy.0.systems.0.id', $system->id)
            ->where('asset_hierarchy.0.systems.0.subsystems.0.id', $populated->id)
            ->where('asset_hierarchy.0.systems.0.subsystems.1.id', $empty->id)
            ->where('asset_hierarchy.0.systems.0.subsystems.1.assetCount', 0)
            ->where('asset_hierarchy.0.systems.1.id', $emptySystem->id)
            ->where('asset_hierarchy.0.systems.1.subsystems', [])
            ->where('asset_hierarchy.1.id', $emptyGroup->id)
            ->where('asset_hierarchy.1.systems', [])
            ->where('selected_area', 'DAOP-1'));
}
```

- [ ] **Step 2: Run the feature test and verify RED**

Run:

```powershell
rtk php artisan test tests/Feature/RamsDashboardBackendTest.php --filter=complete_active_hierarchy
```

Expected: FAIL because the page does not contain `asset_hierarchy`.

- [ ] **Step 3: Implement the hierarchy query**

Add `AssetGroup` to the service imports. Add `asset_hierarchy` to `dashboard()`:

```php
'asset_hierarchy' => $this->assetHierarchy($user, $unit),
```

Add a private method that loads active nodes, aggregates scoped assets at subsystem level, and rolls totals upward:

```php
/** @return array<int, array<string, mixed>> */
private function assetHierarchy(User $user, ?UnitKerja $unit): array
{
    $scopeAssets = fn (Builder $assets): Builder => $assets
        ->visibleTo($user)
        ->when($unit, fn (Builder $query): Builder => $query->where('unit_kerja_id', $unit->id));

    return AssetGroup::query()
        ->where('is_active', true)
        ->with(['systems' => fn ($systems) => $systems
            ->where('is_active', true)
            ->with(['subsystems' => fn ($subsystems) => $subsystems
                ->where('is_active', true)
                ->withCount(['assets as dashboard_asset_count' => $scopeAssets])
                ->withSum(['assets as dashboard_unit_count' => $scopeAssets], 'jumlah_unit')])])
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get()
        ->map(function (AssetGroup $group): array {
            $systems = $group->systems->map(function ($system): array {
                $subsystems = $system->subsystems->map(fn ($subsystem): array => [
                    'id' => $subsystem->id,
                    'name' => $subsystem->name,
                    'assetCount' => (int) $subsystem->dashboard_asset_count,
                    'unitCount' => (int) ($subsystem->dashboard_unit_count ?? 0),
                ])->values();

                return [
                    'id' => $system->id,
                    'name' => $system->name,
                    'assetCount' => $subsystems->sum('assetCount'),
                    'unitCount' => $subsystems->sum('unitCount'),
                    'subsystems' => $subsystems->all(),
                ];
            })->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'assetCount' => $systems->sum('assetCount'),
                'unitCount' => $systems->sum('unitCount'),
                'systems' => $systems->all(),
            ];
        })
        ->values()
        ->all();
}
```

- [ ] **Step 4: Run the hierarchy feature test and verify GREEN**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit the backend hierarchy**

```powershell
rtk git add app/Services/RamsDashboardQuery.php tests/Feature/RamsDashboardBackendTest.php
rtk git commit -m "feat: expose dashboard asset hierarchy"
```

### Task 2: Render the backend-owned hierarchy

**Files:**
- Modify: `tests/js/Dashboard.test.js`
- Modify: `resources/js/pages/dashboard/Dashboard.vue`

- [ ] **Step 1: Write failing Vue tests**

Change the mounted props so `assets` contains a record that must not drive the UI, while `asset_hierarchy` contains populated and empty nodes:

```js
assets: [{
  id: 99,
  aset_prasarana_sintel: 'Must Not Render',
  system: 'Ghost System',
  subsystem: 'Ghost Subsystem',
  jumlah_unit: 99,
}],
asset_hierarchy: [{
  id: 1,
  name: 'Active Group',
  assetCount: 1,
  unitCount: 3,
  systems: [{
    id: 2,
    name: 'Active System',
    assetCount: 1,
    unitCount: 3,
    subsystems: [
      { id: 3, name: 'Populated', assetCount: 1, unitCount: 3 },
      { id: 4, name: 'Empty Subsystem', assetCount: 0, unitCount: 0 },
    ],
  }, {
    id: 5,
    name: 'Empty System',
    assetCount: 0,
    unitCount: 0,
    subsystems: [],
  }],
}, {
  id: 6,
  name: 'Empty Group',
  assetCount: 0,
  unitCount: 0,
  systems: [],
}],
```

Assert that the hierarchy labels and empty messages render, while `Must Not Render` is absent. Update the navigation assertion:

```js
expect(routerGet).toHaveBeenCalledWith('/trouble-report', {
  subsystem_id: 3,
  subsystem: 'Populated',
  area: 'DAOP-1',
})
```

- [ ] **Step 2: Run the Vue test and verify RED**

Run:

```powershell
rtk npm run test:js -- Dashboard.test.js
```

Expected: FAIL because the component still groups `assets` and does not render empty hierarchy nodes.

- [ ] **Step 3: Replace frontend grouping with the backend prop**

Add the prop:

```js
asset_hierarchy: {
  type: Array,
  default: () => [],
},
```

Replace the grouping helpers with:

```js
const assetGroups = computed(() => props.asset_hierarchy)
```

Use IDs for Vue keys. Add these empty child messages:

```vue
<p v-if="!group.systems.length" class="text-sm text-slate-500 py-4 text-center">
  Belum ada system aktif
</p>

<p v-if="!system.subsystems.length" class="text-xs text-slate-500 py-3 text-center">
  Belum ada subsystem aktif
</p>
```

Pass the complete subsystem to navigation:

```js
const goToTroubleReport = (subsystem) => {
  router.get('/trouble-report', {
    subsystem_id: subsystem.id,
    subsystem: subsystem.name,
    ...(props.selected_area ? { area: props.selected_area } : {}),
  })
}
```

- [ ] **Step 4: Run the Vue test and verify GREEN**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit the dashboard renderer**

```powershell
rtk git add resources/js/pages/dashboard/Dashboard.vue tests/js/Dashboard.test.js
rtk git commit -m "feat: render complete dashboard hierarchy"
```

### Task 3: Select Trouble Report subsystem by ID

**Files:**
- Modify: `tests/Feature/RamsDashboardBackendTest.php`
- Modify: `app/Http/Requests/RamsAreaRequest.php`
- Modify: `app/Http/Controllers/RamsDashboardController.php`
- Modify: `app/Services/RamsDashboardQuery.php`

- [ ] **Step 1: Write the failing exact-subsystem test**

Create two active subsystems with the same name under different systems and one asset under each. Request Trouble Report with the target ID:

```php
$this->actingAs($pusat)
    ->get("/trouble-report?subsystem=Shared&subsystem_id={$target->id}")
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->where('subsystem', 'Shared')
        ->has('assets', 1)
        ->where('assets.0.id', $targetAsset->id));
```

- [ ] **Step 2: Run the exact-subsystem test and verify RED**

```powershell
rtk php artisan test tests/Feature/RamsDashboardBackendTest.php --filter=selects_trouble_report_subsystem_by_id
```

Expected: FAIL because the service currently matches every subsystem with the same name.

- [ ] **Step 3: Validate and forward `subsystem_id`**

Add this request rule:

```php
'subsystem_id' => [
    'nullable',
    'integer',
    Rule::exists('asset_subsystems', 'id')
        ->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
],
```

Forward the validated ID from `RamsDashboardController::troubleReport()`:

```php
$subsystemId = $request->validated('subsystem_id');

return Inertia::render(
    'input-data/TroubleReport',
    $query->troubleReport(
        $request->user(),
        $request->selectedUnit(),
        $subsystem,
        $subsystemId === null ? null : (int) $subsystemId,
    ),
);
```

Update `RamsDashboardQuery::troubleReport()` to accept `?int $subsystemId` and filter by ID when present:

```php
public function troubleReport(User $user, ?UnitKerja $unit, string $subsystem, ?int $subsystemId = null): array
{
    $assets = $this->assetQuery($user, $unit)
        ->whereHas('assetSubsystem', fn (Builder $query): Builder => $subsystemId === null
            ? $query->whereRaw('LOWER(name) = ?', [mb_strtolower($subsystem)])
            : $query->whereKey($subsystemId))
        ->with('assetSubsystem')
        ->get();
```

- [ ] **Step 4: Run the exact-subsystem test and verify GREEN**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit exact subsystem navigation**

```powershell
rtk git add app/Http/Requests/RamsAreaRequest.php app/Http/Controllers/RamsDashboardController.php app/Services/RamsDashboardQuery.php tests/Feature/RamsDashboardBackendTest.php
rtk git commit -m "fix: target trouble report subsystem by id"
```

### Task 4: Full verification and browser check

**Files:**
- Verify: `app/Services/RamsDashboardQuery.php`
- Verify: `resources/js/pages/dashboard/Dashboard.vue`
- Verify: `tests/Feature/RamsDashboardBackendTest.php`
- Verify: `tests/js/Dashboard.test.js`

- [ ] **Step 1: Run focused tests**

```powershell
rtk php artisan test tests/Feature/RamsDashboardBackendTest.php
rtk npm run test:js -- Dashboard.test.js
```

Expected: all focused tests pass.

- [ ] **Step 2: Run regression checks**

```powershell
rtk php artisan test
rtk npm run test:js
rtk npm run build
rtk php vendor/bin/pint --test
```

Expected: PHPUnit and Vitest report zero failures, Vite completes the production build, and Pint reports no style violations.

- [ ] **Step 3: Verify the local database and browser**

Open `/dashboard` as `admin.pusat`. Confirm that:

- category `1234` appears with `0 system`;
- system `123` appears with `0 subsystem` under its parent;
- subsystem `12` appears with `0 aset - 0 unit` under its parent;
- existing populated cards retain their counts and layout;
- changing area updates counts while preserving the active hierarchy.

- [ ] **Step 4: Commit any verification-only formatting change**

Only if Pint or the build required a mechanical formatting change:

```powershell
rtk git add app/Services/RamsDashboardQuery.php app/Http/Requests/RamsAreaRequest.php app/Http/Controllers/RamsDashboardController.php resources/js/pages/dashboard/Dashboard.vue tests/Feature/RamsDashboardBackendTest.php tests/js/Dashboard.test.js
rtk git commit -m "style: normalize dashboard hierarchy implementation"
```
