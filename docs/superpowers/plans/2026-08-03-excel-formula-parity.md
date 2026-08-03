# Excel Formula Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement backend and UI parity with the RAMS Excel formulas for trouble report reliability summaries.

**Architecture:** Store Excel snapshot values separately, calculate backend summaries from `failure_logs` using the formula profile detected from each sheet, compare backend against Excel, and render backend values plus parity status in Vue.

**Tech Stack:** Laravel, Eloquent, MySQL migrations, PhpSpreadsheet, Inertia, Vue, Vitest, PHPUnit.

---

### Task 1: Schema and Models

**Files:**
- Create: `database/migrations/2026_08_03_000001_add_excel_formula_parity_fields.php`
- Create: `app/Models/ReliabilityExcelSnapshot.php`
- Modify: `app/Models/FailureLog.php`
- Modify: `app/Models/ReliabilitySummary.php`
- Modify: `database/factories/FailureLogFactory.php`
- Modify: `database/factories/ReliabilitySummaryFactory.php`
- Test: `tests/Feature/RamsOperationalSchemaTest.php`

- [ ] Add a failing schema test for the new snapshot table and parity columns.
- [ ] Run `php artisan test tests/Feature/RamsOperationalSchemaTest.php` and confirm it fails.
- [ ] Add the migration, model, casts, fillable fields, and factory defaults.
- [ ] Re-run the schema test and confirm it passes.

### Task 2: Excel Profile Snapshot

**Files:**
- Create: `app/Services/RamsWorkbookAssetResolver.php`
- Create: `app/Services/ExcelReliabilitySnapshotImporter.php`
- Modify: `app/Services/FailureLogWorkbookImporter.php`
- Test: `tests/Feature/ExcelReliabilitySnapshotImporterTest.php`
- Test: `tests/Feature/FailureLogWorkbookImporterTest.php`

- [ ] Add failing tests for summary header detection, Excel error handling, formula profile detection, and workbook metadata on failure rows.
- [ ] Run the targeted importer tests and confirm failure.
- [ ] Extract asset sheet resolution to `RamsWorkbookAssetResolver`.
- [ ] Implement snapshot import and failure row metadata.
- [ ] Re-run the targeted tests and confirm they pass.

### Task 3: Backend Formula Calculator

**Files:**
- Create: `app/Services/ExcelParityReliabilityCalculator.php`
- Create: `app/Services/ReliabilityParityService.php`
- Modify: `app/Services/FailureLogImportService.php`
- Modify: `app/Services/FailureLogService.php`
- Test: `tests/Unit/ExcelParityReliabilityCalculatorTest.php`
- Test: `tests/Feature/FailureLogImportUploadTest.php`
- Test: `tests/Feature/FailureLogManagementTest.php`

- [ ] Add failing tests for the 3-failure Interlocking Elektrik example and formula profiles.
- [ ] Run the targeted tests and confirm failure.
- [ ] Implement calculator and parity service.
- [ ] Wire upload import and manual input recalculation to the parity service.
- [ ] Re-run targeted tests and confirm they pass.

### Task 4: Backend Payload and Vue UI

**Files:**
- Modify: `app/Services/RamsDashboardQuery.php`
- Modify: `resources/js/pages/input-data/TroubleReport.vue`
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue`
- Test: `tests/js/TroubleReport.test.js` if present or create it.
- Test: `tests/js/TroubleReportImport.test.js`

- [ ] Add failing frontend tests for `Data belum ada`, parity badge, backend counts, and import snapshot counters.
- [ ] Run `npm run test:js -- TroubleReport` and confirm failure.
- [ ] Update payload mapping and Vue rendering.
- [ ] Re-run frontend tests and confirm they pass.

### Task 5: Verification

**Files:**
- Existing modified files only.

- [ ] Run focused PHPUnit tests for import, calculator, schema, and failure management.
- [ ] Run focused Vitest tests for trouble report pages.
- [ ] Run `php vendor/bin/pint --test` on changed PHP files.
- [ ] Run `npm run build`.
- [ ] Run `git diff --check`.
- [ ] Report any unrelated existing test failures separately.
