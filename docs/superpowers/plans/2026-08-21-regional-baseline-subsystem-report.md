# Regional Baseline and Subsystem Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove master-asset location, retain event location/resort, show subsystem installation dates on Trouble Report, and make the Excel regional baseline an audited Admin Pusat override.

**Architecture:** Keep event geography in `failure_logs`; remove `assets.lokasi` after archiving legacy non-null values to `audit_logs`. Keep Excel snapshots as imported baseline evidence, use `unit_kerjas.operating_start_date` only as an explicit regional override, and recalculate all regional summaries when it changes. Reuse existing Inertia forms, `AuditLogger`, and `ReliabilityParityService` without adding packages.

**Tech Stack:** Laravel 13, Eloquent, MySQL, Inertia Vue 3, Vitest, PHPUnit.

---

### Task 1: Remove master-asset location safely

**Files:**
- Create: `database/migrations/2026_08_21_000000_archive_and_remove_asset_location.php`
- Modify: `app/Models/Asset.php`
- Modify: `app/Http/Requests/AssetDataRequest.php`
- Modify: `app/Http/Requests/StoreTaxonomyAssetRequest.php`
- Modify: `app/Http/Controllers/MasterAssetController.php`
- Modify: `app/Http/Controllers/Admin/AssetCategoryController.php`
- Modify: `app/Services/MasterAssetWorkbookImporter.php`
- Test: `tests/Feature/MasterAssetSchemaTest.php`
- Test: `tests/Feature/MasterAssetManagementTest.php`
- Test: `tests/Feature/AssetTaxonomyRegionalAssetTest.php`

- [ ] **Step 1: Write failing schema and request tests**

Assert `assets.lokasi` no longer exists, asset create/update ignores or rejects `lokasi`, and non-null legacy values are copied into `audit_logs` with action `asset.location_archived` before the column is dropped.

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
php artisan test tests/Feature/MasterAssetSchemaTest.php tests/Feature/MasterAssetManagementTest.php tests/Feature/AssetTaxonomyRegionalAssetTest.php
```

Expected: FAIL because `assets.lokasi` and request fields still exist.

- [ ] **Step 3: Add migration and remove backend field usage**

Migration behavior:

```php
$rows = DB::table('assets')->whereNotNull('lokasi')->where('lokasi', '<>', '')->get(['id', 'unit_kerja_id', 'lokasi']);
foreach ($rows as $row) {
    DB::table('audit_logs')->insert([
        'actor_id' => null,
        'action' => 'asset.location_archived',
        'auditable_type' => Asset::class,
        'auditable_id' => $row->id,
        'unit_kerja_id' => $row->unit_kerja_id,
        'old_values' => json_encode(['lokasi' => $row->lokasi], JSON_THROW_ON_ERROR),
        'new_values' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => now(),
    ]);
}
Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('lokasi'));
```

Remove `lokasi` from fillable data, search, validation, normalized request payloads, controller payloads, audit payloads, and importer payloads.

- [ ] **Step 4: Run focused tests**

Expected: all focused tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app database tests
git commit -m "refactor: remove master asset location"
```

### Task 2: Remove asset location from UI and risk payloads

**Files:**
- Modify: `resources/js/pages/master-data/assets/MasterAsset.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/AssetForm.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/AssetHierarchyCard.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/AssetHierarchyTable.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Index.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/TaxonomyAssetDialog.vue`
- Modify: `app/Services/RamsDashboardQuery.php`
- Test: `tests/js/AssetForm.test.js`
- Test: `tests/js/MasterAsset.test.js`
- Test: `tests/js/AssetCategories.test.js`
- Test: `tests/Feature/RamsDashboardBackendTest.php`

- [ ] **Step 1: Write failing UI and backend tests**

Assert master-asset pages contain no asset-location field/column/search copy, while `failurePayload()` still returns `lokasi` and `resor`. Assert Risk Matrix/Register payload no longer reads location from the asset.

- [ ] **Step 2: Run tests and verify failure**

```bash
npm run test:js -- --run tests/js/AssetForm.test.js tests/js/MasterAsset.test.js tests/js/AssetCategories.test.js
php artisan test tests/Feature/RamsDashboardBackendTest.php
```

- [ ] **Step 3: Remove only master-asset location UI**

Delete the `lokasi` form state/input, columns, card text, search placeholder, and copy. Keep Trouble Report columns bound to `log.lokasi` and `log.resor` unchanged.

- [ ] **Step 4: Run focused tests and commit**

```bash
git add app resources tests
git commit -m "refactor: keep location only on failure reports"
```

### Task 3: Show subsystem installation dates on Trouble Report

**Files:**
- Modify: `resources/js/pages/input-data/TroubleReport.vue`
- Modify: `tests/js/TroubleReport.test.js`
- Modify: `tests/Feature/RamsDashboardBackendTest.php`

- [ ] **Step 1: Write failing rendering tests**

Cover one unique date, missing date (`Belum tercatat`), and multiple unique dates. Expected UI is a compact identity panel above reliability data, not a column on every failure row.

- [ ] **Step 2: Run tests and verify failure**

```bash
npm run test:js -- --run tests/js/TroubleReport.test.js
php artisan test tests/Feature/RamsDashboardBackendTest.php
```

- [ ] **Step 3: Add minimal computed installation-date display**

Use existing `assets[].tahun_pemasangan`:

```js
const installationDates = computed(() => [...new Set(
  props.assets.map((asset) => asset.tahun_pemasangan).filter(Boolean),
)])
```

Render subsystem, selected region, total units, and formatted unique dates. Do not expose global baseline.

- [ ] **Step 4: Run focused tests and commit**

```bash
git add resources tests
git commit -m "feat: show subsystem installation dates in trouble report"
```

### Task 4: Add audited regional baseline override and recalculation

**Files:**
- Modify: `app/Http/Requests/Admin/UpdateUnitKerjaRequest.php`
- Modify: `app/Http/Controllers/Admin/UnitKerjaController.php`
- Modify: `app/Services/ReliabilityParityService.php`
- Modify: `app/Services/RamsDashboardQuery.php`
- Modify: `resources/js/pages/Admin/Units/Partials/UnitForm.vue`
- Modify: `resources/js/pages/Admin/Units/Edit.vue`
- Test: `tests/Feature/Admin/UnitKerjaManagementTest.php`
- Test: `tests/Feature/ReliabilityParityServiceTest.php`
- Test: `tests/js/UnitForm.test.js`

- [ ] **Step 1: Write failing baseline tests**

Cover precedence `admin override -> latest Excel snapshot -> no calculation`; require reason and confirmation when baseline changes; verify `unit.baseline_updated` audit values; verify regional recalculation; verify edit props contain imported baseline and override status; verify unit accounts cannot access admin settings.

- [ ] **Step 2: Run tests and verify failure**

```bash
php artisan test tests/Feature/Admin/UnitKerjaManagementTest.php tests/Feature/ReliabilityParityServiceTest.php
npm run test:js -- --run tests/js/UnitForm.test.js
```

- [ ] **Step 3: Implement request and controller workflow**

Validate only changed baselines:

```php
'baseline_change_reason' => [Rule::requiredIf($this->baselineChanged()), 'nullable', 'string', 'max:500'],
'baseline_change_confirmed' => [Rule::requiredIf($this->baselineChanged()), 'nullable', 'accepted'],
```

Update unit and audit in one transaction. After commit, call `ReliabilityParityService::recalculateUnit($unit)` when baseline changed. Return a visible error flash if recalculation fails without deleting previous summaries.

- [ ] **Step 4: Implement baseline precedence**

Load `asset.unitKerja` and choose:

```php
$baselineDate = $asset->unitKerja?->operating_start_date
    ? CarbonImmutable::instance($asset->unitKerja->operating_start_date)
    : ($snapshot?->baseline_date ? CarbonImmutable::instance($snapshot->baseline_date) : null);
if ($baselineDate === null) {
    return null;
}
```

Never fall back to `assets.tanggal_pemasangan` or hard-coded `2020-01-01`.

- [ ] **Step 5: Update Admin Pusat form**

Rename the field to `Baseline Operating Days sesuai Excel`; show imported baseline and `Koreksi manual aktif` only when stored baseline differs; require reason and confirmation only on change. Keep the panel absent from create mode and inaccessible to regional accounts.

- [ ] **Step 6: Run focused tests and commit**

```bash
git add app resources tests
git commit -m "feat: add audited regional baseline override"
```

### Task 5: Regression verification

**Files:**
- Modify only if a regression test exposes an in-scope defect.

- [ ] **Step 1: Run formatting and static diff checks**

```bash
vendor/bin/pint --test
git diff --check
```

- [ ] **Step 2: Run backend suite**

```bash
composer test
```

Expected: all PHP tests PASS against the MySQL test database.

- [ ] **Step 3: Run frontend suite and production build**

```bash
npm run test:js
npm run build
```

Expected: all Vitest tests PASS and Vite build exits 0.

- [ ] **Step 4: Verify migration round trip**

Run migration on a test database containing one non-null legacy asset location. Confirm audit archive exists, `assets.lokasi` is absent, and `migrate:rollback` recreates the nullable column.

- [ ] **Step 5: Final commit if verification changed files**

```bash
git add -A
git commit -m "test: verify regional baseline workflow"
```
