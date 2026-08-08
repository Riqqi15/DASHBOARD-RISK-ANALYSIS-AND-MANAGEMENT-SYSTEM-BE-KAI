# Excel Parity Audit and Dashboard Asset Colors Implementation Plan

> Implement in the existing dirty worktree without reverting the user's dashboard hierarchy changes. Do not modify the source PDF or workbooks and do not commit unless requested.

## Task 1: Stabilize the Existing Baseline

**Files:** `app/Services/FailureLogImportService.php`, `tests/Feature/FailureLogImportUploadTest.php`, `tests/Feature/FailureLogManagementTest.php`, `tests/Feature/RamsDashboardBackendTest.php`, `tests/js/TroubleReportImport.test.js`

1. Add/adjust tests for optional master-asset import, current reliability period behavior, current Inventory Inertia contract, and current import labels.
2. Make the failure-log upload skip a missing `Predictive Data Asset` sheet with a warning instead of failing.
3. Run the focused failing tests and confirm they pass.

## Task 2: Enforce the No-User-Import Invariant

**Files:** `tests/Feature/FailureLogImportUploadTest.php`, `tests/Feature/RamsWorkbookImportCoordinatorTest.php`

1. Add users with representative roles/unit assignments.
2. Snapshot complete user records before each import path.
3. Import a workbook and assert the user table is byte-for-byte unchanged.
4. Confirm no production importer depends on `User` writes.

## Task 3: Preserve Predictive Excel Evidence and Signed Stock

**Files:** new migration, `app/Models/PredictiveAssetSnapshot.php`, `app/Services/PredictiveInventoryCalculator.php`, `app/Services/MasterAssetWorkbookImporter.php`, `tests/Unit/PredictiveInventoryCalculatorTest.php`, `tests/Feature/ImportMasterAssetsTest.php`

1. Write failing tests for negative stock, proposal quantity, raw Excel values/formulas, and parity differences.
2. Add signed `current_stock` plus JSON audit/parity columns through a new migration.
3. Remove zero clamping from predictive current stock and increase formula version.
4. Persist Excel formulas/values and backend comparison results from `Predictive Data Asset`.
5. Run focused predictive tests.

## Task 4: Import Risk Matrix Directly

**Files:** new migration, `app/Models/RiskMatrix.php`, new `app/Services/RiskMatrixWorkbookImporter.php`, `app/Services/RamsWorkbookImportCoordinator.php`, new/focused feature tests

1. Write a workbook fixture test where Risk Matrix differs from Predictive Data Asset.
2. Add source and audit fields without changing computed rating/level accessors.
3. Implement hierarchy-aware direct `Risk Matrix` sheet import.
4. Replace coordinator derivation from predictive snapshots with the direct importer.
5. Run coordinator and risk-matrix tests.

## Task 5: Import and Manage Asset Dashboard Colors

**Files:** new migration, `app/Models/AssetGroup.php`, `app/Models/AssetSystem.php`, `app/Models/AssetSubsystem.php`, `app/Services/MasterAssetWorkbookImporter.php`, admin requests/controllers, `app/Http/Controllers/Admin/AssetCategoryController.php`, `resources/js/pages/Admin/AssetCategories/Partials/CategoryDialog.vue`, related feature/JS tests

1. Write failing tests for the five canonical Excel colors, manual override protection, reset behavior, authorization, and hex validation.
2. Add `dashboard_color` and `dashboard_color_source` to all hierarchy levels.
3. Resolve RGB, indexed, and theme+tint Excel fills to normalized hex while parsing Risk Matrix hierarchy rows.
4. Apply imported colors only to unset/Excel-owned values.
5. Add an accessible color picker and reset control to existing category management for Akun Pusat.
6. Run focused category import/management tests.

## Task 6: Expose Colors and Independent Reliability Metrics on Dashboard

**Files:** `app/Services/RamsDashboardQuery.php`, `resources/js/pages/dashboard/Dashboard.vue`, `tests/Feature/RamsDashboardBackendTest.php`, `tests/js/Dashboard.test.js`

1. Extend existing dirty hierarchy tests to assert effective colors.
2. Split reliability-valid and availability-valid datasets and aggregations.
3. Include color fields at hierarchy levels in the dashboard payload.
4. Use backend colors with contrast-safe text and current palette fallback.
5. Run dashboard backend and JS tests.

## Task 7: Add Predictive Data Asset Inventory Tab

**Files:** `app/Http/Controllers/InventoryController.php`, `resources/js/pages/master-data/inventory/Inventory.vue`, new partial if useful, `tests/Feature/InventoryIndexTest.php`, `tests/js/Inventory.test.js`

1. Write failing contract/UI tests for predictive rows, filters, deficit labels, and parity badges.
2. Query the latest predictive snapshot per visible asset without altering transactional inventory stock.
3. Add the tab and compact table/cards matching the existing Inventory visual language.
4. Run focused inventory tests.

## Task 8: Verify Against All Five Workbooks

**Files:** parity/audit tests only as needed

1. Run read-only workbook checks for formulas, direct Risk Matrix values, and hierarchy colors in Daop 1, Daop 4, Daop 8, Divre III, and Divre IV.
2. Run `php artisan test --compact` and the JavaScript test suite.
3. Run Pint, the production build, and `git diff --check`.
4. Review the final diff for accidental user/account writes and unrelated changes.
