# Dashboard Latest Import Badge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Display `Data Terbaru · <tanggal>` only on dashboard asset-family cards changed by the latest successful regional Excel import, while reducing excess vertical card spacing.

**Architecture:** `RamsDashboardQuery` resolves the latest successful non-dry-run batch for the selected unit, maps meaningful `rams_import_changes` records through assets/subsystems/systems to the five existing family codes, and exposes the result as `summary.latestImport`. `Dashboard.vue` consumes that payload to render a dated badge and uses tighter CSS spacing.

**Tech Stack:** Laravel 13, Eloquent, Inertia.js, Vue 3, Tailwind CSS, Vitest, PHPUnit.

---

### Task 1: Resolve Changed Asset Families in the Backend

**Files:**
- Modify: `tests/Feature/RamsDashboardBackendTest.php`
- Modify: `app/Services/RamsDashboardQuery.php`

- [ ] **Step 1: Write the failing feature test**

Create two units, family hierarchies and assets, then create a successful import batch for each unit. Add `rams_import_changes` rows for a changed `failure_logs` row in PDSE and a change containing only timestamp differences in PLSE. Assert `/dashboard?area=DAOP1` returns:

```php
->where('summary.latestImport.date', '2026-08-20')
->where('summary.latestImport.groupCodes', ['PDSE'])
```

Also create a later dry-run batch and assert it is ignored.

- [ ] **Step 2: Run the backend test to verify RED**

Run:

```powershell
rtk php artisan test tests/Feature/RamsDashboardBackendTest.php --filter=latest_import
```

Expected: FAIL because `summary.latestImport` is not present.

- [ ] **Step 3: Implement the backend resolver**

Add `RamsImportBatch` and `RamsImportChange` imports. Extend `summary()` with:

```php
'latestImport' => $this->latestImport($unit),
```

Implement a focused private method that:

1. Selects the newest `succeeded`, non-dry-run batch for the selected unit.
2. Ignores changes where `before_values` and `after_values` differ only in lifecycle timestamps (`created_at`, `updated_at`, `calculated_at`, `imported_at`).
3. Collects affected asset, subsystem, system and group IDs from supported import tables.
4. Resolves the corresponding `AssetGroup` names.
5. Converts names with a shared `assetGroupCode(string $name)` helper.
6. Returns `['date' => $batch->finished_at?->toDateString() ?? $batch->created_at->toDateString(), 'groupCodes' => $codes]`, or `null` when no successful batch exists.

- [ ] **Step 4: Run the backend test to verify GREEN**

Run:

```powershell
rtk php artisan test tests/Feature/RamsDashboardBackendTest.php --filter=latest_import
```

Expected: PASS.

### Task 2: Render the Dated Badge and Compact Cards

**Files:**
- Modify: `tests/js/Dashboard.test.js`
- Modify: `resources/js/pages/dashboard/Dashboard.vue`

- [ ] **Step 1: Write the failing Vue test**

Mount the dashboard with:

```js
summary: {
  latestImport: { date: '2026-08-20', groupCodes: ['PDSE', 'PLSE'] },
}
```

Assert PDSE and PLSE contain `Data Terbaru · 20 Agu 2026`, PDSM does not, and the family cards include the compact layout hooks.

- [ ] **Step 2: Run the Vue test to verify RED**

Run:

```powershell
rtk npm run test:js -- tests/js/Dashboard.test.js
```

Expected: FAIL because no latest-data badge exists.

- [ ] **Step 3: Implement the Vue rendering**

Add computed lookup and Indonesian date formatting:

```js
const latestImportGroupCodes = computed(() => new Set(props.summary?.latestImport?.groupCodes || []))
const latestImportLabel = computed(() => {
  const date = props.summary?.latestImport?.date
  if (!date) return null
  return `Data Terbaru · ${new Intl.DateTimeFormat('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC',
  }).format(new Date(`${date}T00:00:00Z`))}`
})
```

Render a badge beside the family code only when its code is in the lookup. Reduce `.family-metric__header` minimum height and vertical padding, reduce `.family-metric__values` gap/padding, and keep contrast accessible.

- [ ] **Step 4: Run the Vue test to verify GREEN**

Run:

```powershell
rtk npm run test:js -- tests/js/Dashboard.test.js
```

Expected: PASS.

### Task 3: Regression Verification

**Files:**
- Verify: `app/Services/RamsDashboardQuery.php`
- Verify: `resources/js/pages/dashboard/Dashboard.vue`

- [ ] **Step 1: Run targeted backend and frontend suites**

```powershell
rtk php artisan test tests/Feature/RamsDashboardBackendTest.php
rtk npm run test:js -- tests/js/Dashboard.test.js
```

Expected: all targeted tests pass.

- [ ] **Step 2: Run formatting and production build checks**

```powershell
rtk vendor/bin/pint --test app/Services/RamsDashboardQuery.php tests/Feature/RamsDashboardBackendTest.php
rtk npm run build
rtk git diff --check
```

Expected: exit code 0 for every command.

- [ ] **Step 3: Review the final diff**

```powershell
rtk git diff -- app/Services/RamsDashboardQuery.php resources/js/pages/dashboard/Dashboard.vue tests/Feature/RamsDashboardBackendTest.php tests/js/Dashboard.test.js
```

Expected: changes remain limited to the latest-import payload, badge UI, compact spacing, and their tests.
