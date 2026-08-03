# Trouble Report Workbook Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan upload workbook RAMS multi-sheet yang mengimpor hanya detail trouble report ke `failure_logs` dan mengaudit masalah tanpa menghitung ulang reliability.

**Architecture:** `FailureLogWorkbookImporter` tetap menjadi parser PhpSpreadsheet dan dibuat toleran terhadap error per sheet/baris. `FailureLogImportService` menangani batch audit, sedangkan request/controller menangani otorisasi unit dan upload; halaman Vue khusus menggunakan Inertia `useForm` untuk progress dan hasil.

**Tech Stack:** Laravel 13, Inertia, Vue 3, PhpSpreadsheet, PHPUnit, Vitest.

---

### Task 1: Lock importer behavior with feature tests

**Files:**
- Create: `tests/Feature/FailureLogWorkbookImporterTest.php`
- Modify: `app/Services/FailureLogWorkbookImporter.php`

- [ ] **Step 1: Write failing tests**

Create real `.xlsx` fixtures in the test with a summary table, a detail header beginning after column A, valid rows, an empty row, and a row containing `#VALUE!`. Assert valid rows are created, invalid rows become issues, a second import is unchanged, an edited source row is updated, and `reliability_summaries` remains empty.

- [ ] **Step 2: Run tests to verify RED**

Run: `php artisan test tests/Feature/FailureLogWorkbookImporterTest.php`

Expected: failure because the current importer recalculates reliability, aborts some sheet failures, and keys rows by workbook hash.

- [ ] **Step 3: Implement minimal importer changes**

Remove `ReliabilityCalculator` and `ReliabilitySummary` dependencies. Detect headers dynamically, convert Excel error values to `null`, validate required row values, catch mapping errors per sheet, add combined datetime fallback, and calculate a stable key:

```php
$sourceKey = hash('sha256', implode('|', [
    self::IMPORT_VERSION,
    (string) $asset->unit_kerja_id,
    $this->failureComparable($sheetName),
    (string) $row,
]));
```

- [ ] **Step 4: Run tests to verify GREEN**

Run: `php artisan test tests/Feature/FailureLogWorkbookImporterTest.php`

Expected: all importer tests pass with zero reliability summaries.

### Task 2: Add audited upload endpoint with authorization

**Files:**
- Create: `app/Http/Requests/ImportFailureLogsRequest.php`
- Create: `app/Services/FailureLogImportService.php`
- Create: `app/Http/Controllers/FailureLogImportController.php`
- Create: `tests/Feature/FailureLogImportUploadTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing endpoint tests**

Assert unauthenticated requests redirect to login, a unit user receives only its own unit, a pusat user receives active units, invalid extensions/oversized files are rejected, unit users cannot select another unit, a valid workbook creates a succeeded batch and issues, and the response flashes `import_result`.

- [ ] **Step 2: Run tests to verify RED**

Run: `php artisan test tests/Feature/FailureLogImportUploadTest.php`

Expected: 404 because the import routes and controller do not exist.

- [ ] **Step 3: Implement request, service, controller, and routes**

Use these route contracts:

```php
Route::get('/trouble-report/import', [FailureLogImportController::class, 'index'])
    ->name('failure-logs.import.index');
Route::post('/trouble-report/import', [FailureLogImportController::class, 'store'])
    ->name('failure-logs.import.store');
```

The request accepts `workbook` with `File::types(['xlsx', 'xlsm'])->max(50 * 1024)` and conditionally requires `unit_kerja_id` for pusat users. The service writes every importer issue to the existing `rams_import_issues` relationship and returns a flattened summary.

- [ ] **Step 4: Run tests to verify GREEN**

Run: `php artisan test tests/Feature/FailureLogImportUploadTest.php tests/Feature/FailureLogWorkbookImporterTest.php`

Expected: all upload and importer tests pass.

### Task 3: Add the Inertia upload page

**Files:**
- Create: `resources/js/pages/input-data/TroubleReportImport.vue`
- Create: `tests/js/TroubleReportImport.test.js`
- Modify: `resources/js/layouts/MainLayout.vue`

- [ ] **Step 1: Write failing component tests**

Mock `useForm` and assert the page posts multipart data to `/trouble-report/import`, renders the unit selector only when `can_choose_unit` is true, exposes upload percentage, displays field errors, renders counters, and lists sheet/row issue details.

- [ ] **Step 2: Run tests to verify RED**

Run: `npm run test:js -- tests/js/TroubleReportImport.test.js`

Expected: failure because the component does not exist.

- [ ] **Step 3: Implement the page and navigation**

Create a focused form with:

```js
const form = useForm({ unit_kerja_id: props.selected_unit_id ?? '', workbook: null })
const submit = () => form.post('/trouble-report/import', {
  forceFormData: true,
  preserveScroll: true,
})
```

Render progress from `form.progress?.percentage`, validation messages from `form.errors`, and result props for `created`, `updated`, `unchanged`, `skipped`, `sheets`, and `issues`. Add `Import Trouble Report` to the main navigation.

- [ ] **Step 4: Run tests to verify GREEN**

Run: `npm run test:js -- tests/js/TroubleReportImport.test.js`

Expected: all component tests pass.

### Task 4: Regression and production verification

**Files:**
- Modify only files required by failures found in this task.

- [ ] **Step 1: Run backend regression tests**

Run: `php artisan test tests/Feature/FailureLogWorkbookImporterTest.php tests/Feature/FailureLogImportUploadTest.php tests/Feature/FailureLogManagementTest.php tests/Feature/RamsWorkbookImportCoordinatorTest.php`

Expected: all tests pass.

- [ ] **Step 2: Run frontend tests and build**

Run: `npm run test:js -- tests/js/TroubleReportImport.test.js`

Run: `npm run build`

Expected: Vitest and Vite exit with code 0.

- [ ] **Step 3: Inspect the final diff and scope**

Run: `git diff --check`

Run: `git status --short`

Expected: no whitespace errors; only Tahap 1 importer, upload, UI, tests, and documentation are changed.
