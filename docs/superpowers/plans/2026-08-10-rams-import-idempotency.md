# RAMS Import Idempotency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make repeated RAMS workbook imports update changed active data, skip unchanged/duplicate data, preserve audit history, and report duplicate source locations without changing the `Jumlah Unit` source.

**Architecture:** Each operational importer owns its stable source identity and returns consistent created/updated/unchanged/duplicate counters. `FailureLogImportService` aggregates those counters into a stable UI contract while batch and Excel snapshot records remain historical.

**Tech Stack:** Laravel 13, Eloquent/MySQL, PhpSpreadsheet, Inertia, Vue 3, Vitest, PHPUnit.

---

### Task 1: Trouble-report blank text and duplicate reporting

**Files:**
- Modify: `tests/Feature/FailureLogWorkbookImporterTest.php`
- Modify: `app/Services/FailureLogWorkbookImporter.php`

- [ ] Add a failing test proving blank `Penyebab`, `Tindakan`, and optional text cells are imported as `-` while numeric/date behavior is unchanged.
- [ ] Add a failing assertion proving an identical re-import reports `duplicates_skipped` and a sheet/row duplicate location.
- [ ] Run the focused PHPUnit test and confirm the failures are caused by the missing behavior.
- [ ] Implement the smallest importer changes: normalize imported blank text, retain valid rows, and expose duplicate counters/locations.
- [ ] Re-run the focused PHPUnit test until green.

### Task 2: Stable risk-register identity

**Files:**
- Create: `tests/Feature/RiskRegisterWorkbookImporterTest.php`
- Modify: `app/Services/RiskRegisterWorkbookImporter.php`

- [ ] Add a failing test importing two workbook versions at the same `LxC` row and assert one active record is updated rather than duplicated.
- [ ] Add a failing test re-importing identical content and assert no database write plus duplicate reporting.
- [ ] Run the focused test and confirm the current workbook-hash source key causes duplication.
- [ ] Replace the hash-versioned key with a unit/sheet/source-row key and adopt an existing legacy row at the same source position.
- [ ] Re-run the focused test until green.

### Task 3: Combined result contract and import UI

**Files:**
- Modify: `tests/Feature/FailureLogImportUploadTest.php`
- Modify: `tests/js/TroubleReportImport.test.js`
- Modify: `app/Services/FailureLogImportService.php`
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue`

- [ ] Add failing backend assertions for `data_updated`, `data_unchanged`, `duplicates_skipped`, and `duplicate_locations`.
- [ ] Add failing Vue assertions for the three requested summary cards and duplicate source list.
- [ ] Run focused PHPUnit and Vitest tests to confirm RED.
- [ ] Aggregate per-importer counters without changing existing detailed counters.
- [ ] Render the new counters and duplicate source locations in current-result and batch-history views.
- [ ] Re-run focused backend and frontend tests until green.

### Task 4: Regression verification

**Files:**
- Verify only; no planned production edits.

- [ ] Run all RAMS import feature tests.
- [ ] Run the relevant Vue test suite.
- [ ] Run the frontend production build.
- [ ] Review `git diff` and confirm no code changes the `Jumlah Unit` source.
- [ ] Restart the Redis import worker so the running application loads the new importer code.
