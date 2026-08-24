# RAMS Unit Data Isolation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep every RAMS module read, mutation, and export strictly inside the active DAOP or DIVRE while preserving the Admin Pusat selection across module navigation.

**Architecture:** Add one backend `RamsUnitContext` service as the authoritative unit resolver. Existing `area` and `unit_kerja_id` selectors feed the resolver, which stores a valid Admin Pusat choice in session and always forces regional accounts to their assigned unit. Controllers continue using their existing query structures but receive the same resolved unit and add missing category, spare-part, and mutation ownership constraints.

**Tech Stack:** Laravel 13, PHP 8.4, Eloquent, Inertia.js, Vue 3, PHPUnit, Vitest.

---

### Task 1: Centralize and persist the active RAMS unit

**Files:**
- Create: `app/Services/RamsUnitContext.php`
- Modify: `app/Http/Requests/RamsAreaRequest.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/RamsUnitContextTest.php`

- [ ] **Step 1: Write failing context tests**

Create tests proving that a pusat request with `area=DAOP-2` stores DAOP-2, a following request without a selector reuses DAOP-2, an explicit `unit_kerja_id` updates the same context, and a regional account cannot escape its assigned unit.

```php
$this->actingAs($pusat)->get('/dashboard?area=DAOP-2')->assertOk();
$this->assertSame($daopTwo->id, session('rams.active_unit_id'));
$this->actingAs($pusat)->get('/risk-matrix')
    ->assertInertia(fn (Assert $page) => $page->where('selected_area', 'DAOP-2'));

$this->actingAs($regional)->get('/dashboard?area=DAOP-2')
    ->assertSessionHasErrors('area');
```

- [ ] **Step 2: Run the context tests and confirm failure**

Run: `php artisan test tests/Feature/RamsUnitContextTest.php`

Expected: FAIL because the selected unit is not persisted across requests.

- [ ] **Step 3: Implement the resolver**

Create `RamsUnitContext` with one public method:

```php
public function resolve(Request $request): ?UnitKerja
{
    $user = $request->user();
    if ($user->isUnit()) {
        return UnitKerja::query()->whereKey($user->unit_kerja_id)->where('is_active', true)->firstOrFail();
    }

    $requested = $this->requestedUnit($request);
    $unit = $requested
        ?? UnitKerja::query()->whereKey($request->session()->get(self::SESSION_KEY))->where('is_active', true)->first()
        ?? UnitKerja::query()->where('is_active', true)->orderBy('code')->first();

    if ($unit) {
        $request->session()->put(self::SESSION_KEY, $unit->id);
    }

    return $unit;
}
```

`requestedUnit()` accepts a valid active `unit_kerja_id` first, otherwise a normalized `area`; an explicitly invalid selector fails instead of falling back. Make `RamsAreaRequest::selectedUnit()` delegate to this service. Share `active_rams_unit` from `HandleInertiaRequests` as `{id, code, name}` for authenticated users.

- [ ] **Step 4: Run the context tests**

Run: `php artisan test tests/Feature/RamsUnitContextTest.php`

Expected: PASS.

### Task 2: Apply the unit context to every read path

**Files:**
- Modify: `app/Http/Controllers/MasterAssetController.php`
- Modify: `app/Http/Controllers/InventoryController.php`
- Modify: `app/Services/RamsDashboardQuery.php`
- Modify: `app/Http/Controllers/RiskRegisterController.php`
- Modify: `app/Http/Controllers/RamsReportController.php`
- Test: `tests/Feature/RamsModuleUnitIsolationTest.php`
- Test: `tests/Feature/MasterAssetManagementTest.php`
- Test: `tests/Feature/InventoryIndexTest.php`

- [ ] **Step 1: Write failing cross-unit read tests**

Create DAOP-1, DAOP-2, and DIVRE-III records, select DAOP-2 as Admin Pusat, then assert each page contains only DAOP-2 IDs. Include asset statistics, risk matrices, risk registers, inventory stock/movements/predictive data, spare-part master rows, categories, and report datasets.

```php
$this->actingAs($pusat)->get("/master-asset?unit_kerja_id={$daopTwo->id}")
    ->assertInertia(fn (Assert $page) => $page
        ->where('filters.unit_kerja_id', (string) $daopTwo->id)
        ->has('assets.data', 1)
        ->where('assets.data.0.id', $daopTwoAsset->id)
        ->where('stats.total_assets', 1));
```

- [ ] **Step 2: Run the read-isolation tests and confirm the leaking props**

Run: `php artisan test tests/Feature/RamsModuleUnitIsolationTest.php tests/Feature/MasterAssetManagementTest.php tests/Feature/InventoryIndexTest.php`

Expected: FAIL for unscoped category/master-spare-part props or lost unit context.

- [ ] **Step 3: Add the missing scopes**

Inject `RamsUnitContext` into Master Asset and Inventory controllers. Replace their local fallback resolvers with `$context->resolve($request)`. Scope category trees using `AssetGroup::query()->forUnit($unitId)`. Scope spare parts with:

```php
->whereHas(
    'assetSubsystem.assetSystem.assetGroup',
    fn (Builder $group): Builder => $group->forUnit($unitId),
)
```

Keep every existing stock, movement, predictive, risk, Trouble Report, and report query constrained by the resolved unit. Never use an absent unit as permission to return all rows.

- [ ] **Step 4: Run the read-isolation tests**

Run: `php artisan test tests/Feature/RamsModuleUnitIsolationTest.php tests/Feature/MasterAssetManagementTest.php tests/Feature/InventoryIndexTest.php`

Expected: PASS.

### Task 3: Reject cross-unit mutations

**Files:**
- Modify: `app/Http/Requests/AssetDataRequest.php`
- Modify: `app/Http/Controllers/MasterAssetController.php`
- Modify: `app/Http/Controllers/FailureLogController.php`
- Modify: `app/Services/FailureLogService.php`
- Modify: `app/Http/Requests/StoreStockMovementRequest.php`
- Modify: `app/Http/Requests/CorrectStockMovementRequest.php`
- Modify: `app/Http/Controllers/StockMovementController.php`
- Test: `tests/Feature/RamsModuleUnitIsolationTest.php`
- Test: `tests/Feature/FailureLogManagementTest.php`
- Test: `tests/Feature/InventoryManagementTest.php`

- [ ] **Step 1: Write failing mutation-isolation tests**

Select DAOP-1 in session, then submit DAOP-2 asset, subsystem, spare-part, failure-log, movement, edit, delete, and correction IDs. Assert 404/403 or validation errors and unchanged database rows.

```php
$this->withSession(['rams.active_unit_id' => $daopOne->id])
    ->actingAs($pusat)
    ->delete("/master-asset/{$daopTwoAsset->id}")
    ->assertNotFound();
$this->assertDatabaseHas('assets', ['id' => $daopTwoAsset->id]);
```

- [ ] **Step 2: Run the mutation tests and confirm failure**

Run: `php artisan test tests/Feature/RamsModuleUnitIsolationTest.php tests/Feature/FailureLogManagementTest.php tests/Feature/InventoryManagementTest.php`

Expected: FAIL where pusat operations currently trust arbitrary record IDs.

- [ ] **Step 3: Enforce ownership at the backend boundary**

For assets, resolve the active unit and add `where('unit_kerja_id', $unit->id)` to lookup/mutation queries. Validate that the selected subsystem belongs to an `AssetGroup::forUnit($unit)`. For Trouble Reports, pass the active unit ID to `FailureLogService` and reject assets/logs outside it. For stock state/store/correction, require source unit and spare-part hierarchy to match the active unit before recording movement.

- [ ] **Step 4: Preserve scoped redirects**

Redirect back to the originating module while retaining the active session context; do not redirect to a national or all-unit view.

- [ ] **Step 5: Run mutation tests**

Run: `php artisan test tests/Feature/RamsModuleUnitIsolationTest.php tests/Feature/FailureLogManagementTest.php tests/Feature/InventoryManagementTest.php`

Expected: PASS with unchanged rows for rejected cross-unit requests.

### Task 4: Keep the selected unit visible across the Modul RAMS UI

**Files:**
- Modify: `resources/js/layouts/MainLayout.vue`
- Modify: `resources/js/pages/master-data/assets/MasterAsset.vue`
- Modify: `resources/js/pages/master-data/inventory/Inventory.vue`
- Test: `tests/js/MainLayout.test.js`
- Test: `tests/js/MasterAsset.test.js`
- Test: `tests/js/Inventory.test.js`

- [ ] **Step 1: Write failing frontend tests**

Assert that the Modul RAMS links use the shared active unit context, Master Asset and Inventory initialize their selectors from server props, and a unit change clears dependent filters before visiting the scoped route.

```js
expect(wrapper.get('[data-rams-module-link="inventory"]').attributes('href'))
  .toContain('unit_kerja_id=2')
```

- [ ] **Step 2: Run frontend tests and confirm failure**

Run: `npm run test:js -- tests/js/MainLayout.test.js tests/js/MasterAsset.test.js tests/js/Inventory.test.js`

Expected: FAIL because sidebar links currently omit the active unit.

- [ ] **Step 3: Implement scoped navigation**

Read `page.props.active_rams_unit`. Build module links with `area=<code>` for Dashboard/Matriks/Risk Register/Laporan and `unit_kerja_id=<id>` for Master Aset/Inventori. Add stable `data-rams-module-link` attributes. When a selector changes, clear search/category/status/page filters that could retain stale UI state, then visit the selected unit.

- [ ] **Step 4: Run frontend tests**

Run: `npm run test:js -- tests/js/MainLayout.test.js tests/js/MasterAsset.test.js tests/js/Inventory.test.js`

Expected: PASS.

### Task 5: Full verification

**Files:**
- Verify all modified PHP/Vue/test files.

- [ ] **Step 1: Run focused backend tests**

Run: `php artisan test tests/Feature/RamsUnitContextTest.php tests/Feature/RamsModuleUnitIsolationTest.php tests/Feature/MasterAssetManagementTest.php tests/Feature/InventoryIndexTest.php tests/Feature/FailureLogManagementTest.php tests/Feature/InventoryManagementTest.php tests/Feature/RamsDashboardBackendTest.php tests/Feature/RiskRegisterManagementTest.php tests/Feature/RamsReportExportTest.php`

Expected: zero failures.

- [ ] **Step 2: Run all frontend tests**

Run: `npm run test:js`

Expected: zero failures.

- [ ] **Step 3: Run production checks**

Run: `npm run build`

Run: `vendor/bin/pint --test`

Run: `git diff --check`

Expected: successful build, valid PHP formatting, and no whitespace errors.
