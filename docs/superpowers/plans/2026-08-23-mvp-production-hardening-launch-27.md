# MVP Production Hardening Launch 27 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. This is the launch scope and intentionally supersedes the broader post-launch PDF alignment plans for now. Do not modify the Excel workbook, discard unrelated working-tree changes, or perform broad refactors.

**Goal:** Stabilize the existing RAMS application for the 27 August launch by fixing only confirmed data-mismatch risks, making import problems visible, and preserving the current working architecture.

**Architecture:** Keep the current database, importer, parity snapshot, and calculator boundaries. The master asset and canonical trouble-report records remain operational truth; Excel summary values remain comparison evidence. Apply only small, test-backed changes before launch and defer new statistical data models until after launch.

**Tech Stack:** Laravel 13, PHP 8.4, Eloquent/MySQL, PhpSpreadsheet, PHPUnit, Vue, Vite, SonarQube.

---

## Launch Decision

The following work is **not** part of the pre-launch scope:

- full standard-deviation Goods Issues model
- lead-time variability model
- new database schema for rejected import rows
- full FMEA/MCA/risk-model separation
- historical recalculation of all summaries
- large dashboard or importer refactor
- changing the current safety-stock result model globally

The broader PDF documents remain local post-launch references and are excluded from Git until the follow-up contract is approved. This MVP plan is the only plan to execute before 27 August.

## Known And Expected Web vs Excel Differences

These differences are by design and must not be treated as deploy bugs:

1. Sparepart/vandalism replacement count: the web counts only positive markers (`Y`/`YA`/`YES`/`true`). Excel may use `COUNTA`, which counts any non-empty cell including `N`/`Tidak`. Affected sheets will show parity `mismatch`/`corrected`. This is semantically correct and should not be "fixed" before launch.
2. `failure_count_mode` and the marker count modes (`counta_all_minus_1`, `counta`, `countif_ya`) are resolved from the workbook formula but the calculator recomputes counts from detail rows and does not honor the `-1` variant. Because the Excel snapshot also recounts from the detail sheet, web and snapshot stay consistent. No action before launch.
3. Downtime units: the calculator replicates Excel's per-sheet unit convention (`minutes` vs `hours` vs `excel_day_fraction`) instead of enforcing one semantic unit. This is intentional parity behavior; do not normalize it before launch.
4. Dead code: `ReliabilityCalculator` is unused in `app/` and does not feed the dashboard. It can be cleaned up post-launch under the Sonar scope; it must not be wired into the launch path.

Any parity mismatch that is NOT covered by the four points above should be treated as a real bug to investigate before launch.

## Pre-Launch Changes

Implement only these behavior changes:

1. MTTF ordering stays exactly as today (Excel workbook row order) so parity with the Excel snapshot is preserved. Do not introduce chronological re-sorting before launch.
2. Excel/master unit-count conflicts remain visible and never trigger automatic adoption of the Excel snapshot value.
3. Every skipped, empty, or invalid import row has an explicit counter and source location where the existing result structure supports it. Cached combined timestamps are cross-checked against raw date/time cells; a mismatch is visible and the raw date/time cells are authoritative.
4. Existing live-period behavior is preserved; no new internal fallback to the server clock may be introduced. Existing explicit current-period refresh calls remain unchanged for launch safety.
5. Reorder hierarchy fallback may resolve only a unique subsystem within the proven asset group. Ambiguous paths remain skipped, while source-key identity, queue import flow, and admin-managed fields remain unchanged.

## File Map

Expected files to inspect or modify:

- Verify only: `app/Services/ExcelParityReliabilityCalculator.php` — MTTF row-order behavior is locked by an existing test and must not change before launch.
- Modify: `app/Services/ReliabilityParityService.php` only if a specific conflict is not already surfaced by the existing parity comparison.
- Modify: `app/Services/FailureLogWorkbookImporter.php` only if a skipped-row counter/location is missing from the current result.
- Modify: `app/Services/MasterAssetWorkbookImporter.php` only if master-vs-snapshot conflict metadata is not preserved.
- Verify only: `tests/Unit/ExcelParityReliabilityCalculatorTest.php` — the existing MTTF row-order tests must stay green.
- Modify: `tests/Feature/FailureLogImportUploadTest.php` for visible invalid-row, empty-row, malformed-header, and timestamp-conflict behavior.
- Modify: `tests/Feature/RamsImportHistoryTest.php` for batch status and issue behavior if required.
- Modify: `tests/Feature/SparePartImportTest.php` only if the existing reorder regression needs coverage.
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue` only if the existing batch detail cannot display the already-returned issue fields.

Do not add a migration unless the current application cannot represent a required launch status. If a migration appears necessary, stop and report it before implementing it; the launch scope is intended to be migration-free.

## Day 1: Baseline And Verification

### Task 1: Capture Current Launch Baseline

- [ ] Record current git status without changing unrelated files.
- [ ] Record the current focused test results.
- [ ] Record the current DAOP-1 import result and latest batch status.
- [ ] Record the current SonarQube quality gate result.

Run:

```powershell
rtk git status --short
rtk php artisan test tests/Unit/ExcelParityReliabilityCalculatorTest.php tests/Feature/FailureLogImportUploadTest.php tests/Feature/SparePartImportTest.php
```

Expected:

- Existing unrelated worktree changes remain untouched.
- The baseline output is saved in the handoff notes or launch ticket.
- Do not block the launch on unrelated pre-existing full-suite failures unless they touch the changed files.

### Task 2: Verify MTTF Row-Order Parity Is Preserved

**Test:** `tests/Unit/ExcelParityReliabilityCalculatorTest.php`

- [ ] Confirm `test_interval_uses_workbook_row_order_when_source_rows_are_available` stays green.
- [ ] Confirm `test_it_matches_interlocking_elektrik_workbook_formula_with_three_failures` and the other workbook-parity tests stay green.
- [ ] Do not add a chronological re-sort test and do not change the interval ordering in this launch scope.

Reason: the existing calculator and Excel snapshot both use workbook row order. Re-sorting chronologically would change MTTF and increase parity mismatches. That work is deferred to the post-launch PDF-alignment plan.

Run:

```powershell
rtk php artisan test tests/Unit/ExcelParityReliabilityCalculatorTest.php --filter="interval|mttf|interlocking"
```

Expected: all existing MTTF row-order tests remain green and unchanged.

### Task 3: Audit Existing Period Callers

**Files:** `app/Services/FailureLogService.php`, `app/Services/ReliabilityParityService.php`, `app/Http/Controllers/Admin/UnitKerjaController.php`, `app/Services/FailureLogImportService.php`

- [ ] Confirm `FailureLogService::recalculateReliability()` passes `resolvedAt` explicitly for the historical summary.
- [ ] Confirm its explicit `now()` call is retained for refreshing the current live summary; do not remove it before launch.
- [ ] Confirm `ReliabilityParityService` uses an imported snapshot date when one exists.
- [ ] Confirm every new or changed caller passes an explicit calculation date.
- [ ] Do not change the current live-period behavior in this task.

Run:

```powershell
rtk php artisan test tests/Feature/ReliabilityBaselinePrecedenceTest.php tests/Feature/RamsImportHistoryTest.php --filter="period|calculation|reliability|baseline"
```

Expected: existing snapshot precedence and live refresh behavior are documented and remain green.

## Day 2: Minimal Code Fixes

### Task 4: Keep MTTF Ordering Unchanged

**File:** verify only, `app/Services/ExcelParityReliabilityCalculator.php`

- [ ] Do not change the collection sort order.
- [ ] Do not change interval construction.
- [ ] Do not change the `MTBF = uptime / failure_count` formula.
- [ ] Leave downtime storage and conversion exactly as they are.

Reason: the existing `test_interval_uses_workbook_row_order_when_source_rows_are_available` test locks row-order behavior, and the Excel snapshot parity depends on it. Changing this before launch would create new parity mismatches and require rewriting that test plus several workbook parity fixtures.

Duration rule for launch:

- Do not change persisted downtime values or their units in this task.

Run:

```powershell
rtk php artisan test tests/Unit/ExcelParityReliabilityCalculatorTest.php --filter="interval|mttf|interlocking"
```

Expected: all existing MTTF tests remain green with no code change.

### Task 5: Preserve Period Flow Without New Fallbacks

**Files:** `app/Services/ReliabilityParityService.php`, `app/Services/FailureLogService.php`

- [ ] Preserve a snapshot `calculation_date` when it exists.
- [ ] Preserve an explicitly supplied fallback period when the caller provides one.
- [ ] Do not add or remove a fallback path in the launch patch.
- [ ] Keep `FailureLogService` current-summary refresh using its existing explicit `now()` argument.
- [ ] Keep snapshot-based calculations deterministic for the imported snapshot period.
- [ ] Add no new status value for this task.

Do not silently default a live report to the PDF reference period. The PDF reference period is `2020-01-01` through `2022-02-28` and must be requested explicitly; the existing live current-period refresh remains an explicit caller choice.

Run:

```powershell
rtk php artisan test tests/Feature/RamsImportHistoryTest.php --filter="period|calculation|reliability"
```

Expected: current live refresh and imported snapshot period behavior are unchanged.

### Task 6: Preserve Master Unit As Operational Truth

**Files:** verify first, `app/Services/ReliabilityParityService.php`; modify only if the mismatch is not already surfaced.

- [ ] Confirm the existing `TOLERANCES['unit_count'] = 0.0` already marks a master-vs-snapshot unit difference as `mismatch`.
- [ ] Confirm `Asset::jumlah_unit` stays the operational input to the web calculator.
- [ ] Confirm the Excel snapshot unit count stays comparison evidence only.
- [ ] Do not overwrite master `jumlah_unit` with the Excel summary value.
- [ ] Only if the current parity comparison is not surfacing the difference, add the missing comparison; otherwise make no code change.

Required example diagnostic:

```text
PENGAMAN WESEL SETEMPAT ELEKTRIK
master_unit_count = 0
excel_snapshot_unit_count = 186
status = mismatch
```

Run:

```powershell
rtk php artisan test tests/Feature/RamsImportHistoryTest.php tests/Feature/FailureLogImportUploadTest.php --filter="parity|unit|snapshot|mismatch"
```

Expected: unit-count mismatch is already visible as `mismatch`; no automatic adoption of the Excel value.

## Day 3: Import Visibility And Regression

### Task 7: Make Skipped Rows Countable

**File:** `app/Services/FailureLogWorkbookImporter.php`

- [ ] Keep the current acceptance rules for valid trouble reports.
- [ ] Count rows with a non-empty `Failure Event` and invalid date/time as rejected or needs-review.
- [ ] Count empty `Failure Event` rows separately as empty, without treating them as failures.
- [ ] Cross-check cached `Tanggal Jam Kejadian/Penanganan` values against the raw date and time cells when both sources exist.
- [ ] When those sources conflict, use the raw date/time cells and append a warning containing both values and the source row.
- [ ] Keep duplicate and conflict counters separate.
- [ ] Include `workbook_name`, `sheet_name`, `source_row`, source column, message, and severity for each visible issue.
- [ ] Record sheets that look like data sheets but have no recognized header as skipped with a reason.
- [ ] Do not throw an exception for unrecognized headers or empty rows. Only increment counters and append issue entries. The importer runs inside a DB transaction with retries; any new `RuntimeException` for a previously silent case would abort the whole batch.

Do not add a new database table in the launch scope. Use the current batch result/issue structure unless the existing API cannot carry the information.

Run:

```powershell
rtk php artisan test tests/Feature/FailureLogImportUploadTest.php --filter="invalid|skipped|empty|duplicate|issue"
```

Expected: no invalid populated row disappears without a counter or source location.

### Task 8: Confirm Existing Reorder Import Safety

**Files:** existing reorder importer tests only if regression coverage is missing.

- [ ] Confirm a unique hierarchy fallback within the proven asset group imports valid DAOP-1 reorder rows.
- [ ] Confirm ambiguous hierarchy is still skipped, not guessed.
- [ ] Confirm repeated `Detail Equipment` values under different `Equipment` values remain separate.
- [ ] Confirm taxonomy is not created during normal web import.
- [ ] Preserve the existing source-key identity behavior while allowing only the tested unique hierarchy fallback.

Run:

```powershell
rtk php artisan test tests/Feature/SparePartImportTest.php tests/Feature/FailureLogImportUploadTest.php --filter="reorder|hierarchy|source|idempotent"
```

### Task 9: Verify Existing Batch Detail UI

**File:** `resources/js/pages/input-data/TroubleReportImport.vue`

- [ ] Display existing `issues`, skipped counts, and parity status if the API already returns them.
- [ ] Do not redesign the page.
- [ ] Do not add a second calculation in JavaScript.
- [ ] If the current UI cannot render a returned field, add only the smallest binding needed.

Run:

```powershell
rtk npm run test:js -- tests/js/TroubleReportImport.test.js
```

Expected: batch detail renders import result and issue locations without a loading hang or undefined binding error.

## Day 4: Production Verification

### Task 10: Run Focused Backend And Frontend Tests

- [ ] Run the calculator, importer, parity, and UI tests after all changes.

```powershell
rtk php artisan test tests/Unit/ExcelParityReliabilityCalculatorTest.php tests/Feature/FailureLogImportUploadTest.php tests/Feature/SparePartImportTest.php tests/Feature/RamsImportHistoryTest.php tests/Feature/RamsWorkbookImportCoordinatorTest.php
rtk npm run test:js -- tests/js/TroubleReportImport.test.js
```

Expected: zero failures in the changed behavior. If an unrelated existing test fails, record the exact test and do not broaden scope.

### Task 11: Run Formatting And Static Checks

- [ ] Run formatting only on changed PHP files.
- [ ] Run the existing SonarQube command/configuration used by the project.
- [ ] Verify the quality gate does not regress from the baseline.

```powershell
rtk vendor/bin/pint --test app/Services/ExcelParityReliabilityCalculator.php app/Services/ReliabilityParityService.php app/Services/FailureLogWorkbookImporter.php app/Services/MasterAssetWorkbookImporter.php tests/Unit/ExcelParityReliabilityCalculatorTest.php tests/Feature/FailureLogImportUploadTest.php tests/Feature/RamsImportHistoryTest.php
```

Expected: formatting passes. Sonar issues introduced by changed files are zero or explicitly reviewed before launch.

### Task 12: DAOP-1 Shadow Import

- [ ] Use a disposable/test database first.
- [ ] Import the current DAOP-1 workbook with the queue worker running.
- [ ] Confirm the batch reaches the existing terminal status (`succeeded` or `failed` with visible issues), never remains silently queued.
- [ ] Confirm all reorder rows are accounted for.
- [ ] Confirm all invalid trouble-report rows have visible locations.
- [ ] Confirm any master-vs-Excel unit conflict is visible as the existing parity `mismatch`.
- [ ] Confirm a second import is idempotent.

Required invariants:

```text
no unexplained skipped rows
no ambiguous hierarchy guessed
no duplicate source-key records
same workbook + same explicit period = same result
master-vs-Excel conflict = visible parity mismatch
```

### Task 13: Launch Smoke Checklist

- [ ] Back up the production database before deployment.
- [ ] Verify Laravel application, Vite build, and queue worker versions match.
- [ ] Verify the queue connection is `database` and a worker is running.
- [ ] Verify login for admin and at least one Daop account.
- [ ] Verify master asset list loads.
- [ ] Verify trouble report import upload, batch detail, and issue display.
- [ ] Verify dashboard loads with no JavaScript console error.
- [ ] Verify one known parity mismatch is shown as a warning rather than silently presented as matched.
- [ ] Verify rollback instructions and previous build are available.

## Post-Launch Queue (Local And Git-Ignored)

These documents stay local and must not be committed or executed unless the follow-up contract is approved:

1. `post-launch/2026-08-23-pdf-formula-contract-for-grok.md`
2. `post-launch/2026-08-23-pdf-excel-code-gap-analysis-for-grok.md`
3. `post-launch/2026-08-23-single-source-of-truth-data-contract-for-grok.md`
4. `post-launch/2026-08-23-production-pdf-alignment-implementation-plan-for-grok.md`

The first post-launch feature should be the PDF standard-deviation Goods Issues model, but only after the required monthly usage data is available and validated.

## Final Acceptance

The launch candidate is acceptable when:

- focused changed-file tests pass
- SonarQube quality gate is not worse than baseline
- MTTF ordering and parity with the Excel snapshot are unchanged
- no new calculation path uses an implicit server-date period
- master-vs-Excel unit mismatch is visible as parity mismatch
- all populated rejected rows are traceable
- DAOP-1 import is idempotent
- queue worker completes the import
- no unrelated working-tree changes were reverted
