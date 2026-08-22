# Sonar Maintainability Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve the 574 open maintainability issues exported from SonarCloud without changing application behavior.

**Architecture:** Treat `C:\Users\riyadh\Downloads\sonar-maintainability.csv` as the issue manifest. Apply behavior-preserving PHP formatting first, then fix the small JavaScript, Vue accessibility, and CSS groups rule-by-rule. Verify syntax, tests, production build, and the same local rule counts before pushing.

**Tech Stack:** Laravel 12, PHP 8.4, Laravel Pint, Vue 3, Inertia, Vitest, Vite, SonarCloud.

---

### Task 1: Establish baseline and issue groups

**Files:**
- Read: `C:\Users\riyadh\Downloads\sonar-maintainability.csv`
- Preserve: `package.json`
- Preserve: `package-lock.json`

- [ ] **Step 1: Confirm issue counts**

Run a CSV grouping command and confirm 574 rows: 450 `php:S103`, 91 `php:S1808`, and 33 frontend issues.

- [ ] **Step 2: Confirm pre-existing worktree changes**

Run `git status --short --branch`. Do not stage the pre-existing package manifest changes.

### Task 2: Apply behavior-preserving PHP formatting

**Files:**
- Modify: the 120 PHP files named by `php:S103` or `php:S1808` rows in the CSV manifest.

- [ ] **Step 1: Run Pint baseline check**

Run `php vendor/bin/pint --test app tests database routes config` and record existing formatting failures.

- [ ] **Step 2: Format only PHP files named by the manifest**

Use Laravel Pint on the exact unique file list from the CSV. Keep logic unchanged.

- [ ] **Step 3: Wrap remaining lines over 120 characters**

Use syntax-aware multiline calls, arrays, closures, fluent chains, and assertions. Do not split string contents unless concatenation preserves the exact value.

- [ ] **Step 4: Verify PHP syntax and formatting**

Run `php -l` for every changed PHP file and `php vendor/bin/pint --test` for the same files. Expected: zero syntax errors and zero Pint failures.

### Task 3: Fix frontend maintainability rules

**Files:**
- Modify: `resources/js/components/feedback/FlashMessage.vue`
- Modify: `resources/js/components/trouble-report/TroubleReportModal.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Index.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/AccessibleDialog.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/CategoryDialog.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/LevelDialog.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/TaxonomyAssetDialog.vue`
- Modify: `resources/js/pages/dashboard/Dashboard.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/AssetHierarchyCard.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/AssetHierarchyTable.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/DeleteAssetDialog.vue`
- Modify: `resources/js/pages/master-data/inventory/Inventory.vue`
- Modify: `resources/js/pages/master-data/inventory/Partials/InventoryTable.vue`
- Modify: `resources/js/pages/master-data/inventory/Partials/MovementDialog.vue`
- Modify: `resources/js/pages/master-data/inventory/Partials/MovementHistory.vue`
- Modify: `resources/js/pages/master-data/inventory/Partials/SparePartDialog.vue`
- Modify: `resources/js/pages/risk-register/Index.vue`

- [ ] **Step 1: Apply mechanical JavaScript fixes**

Remove the unused import, unnecessary regex escapes, nested template literals, nested ternaries, and replace the membership `.some()` call with `.includes()`.

- [ ] **Step 2: Fix Vue prop mutation**

Replace direct child mutation of the `form` prop with emitted updates or the existing parent-owned form pattern, preserving validation and submission behavior.

- [ ] **Step 3: Fix accessibility and contrast**

Use native `<dialog>` semantics where compatible with existing open/close behavior and adjust the reported text color to meet contrast requirements.

- [ ] **Step 4: Reduce modal cognitive complexity**

Extract named validation/data-building helpers from the reported submit function while keeping its public behavior and emitted events unchanged.

### Task 4: Verify and integrate

**Files:**
- Verify: all modified files.

- [ ] **Step 1: Recount reported patterns locally**

Confirm no changed PHP line exceeds 120 characters and manually map every non-PHP CSV row to its resolved code change.

- [ ] **Step 2: Run test suites**

Run `php artisan test`, `npm run test:js`, and `npm run build`. Expected: zero failures and successful production build.

- [ ] **Step 3: Review diff**

Run `git diff --check` and inspect `git diff --stat` plus representative diffs. Confirm package manifests remain unstaged.

- [ ] **Step 4: Commit and push**

Stage only the plan and Sonar remediation files, commit with `Fix Sonar maintainability issues`, and push `main` to `origin`.
