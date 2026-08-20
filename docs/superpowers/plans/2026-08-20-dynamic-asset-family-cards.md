# Dynamic Asset Family Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically number newly-created root asset categories and expose every selected region's root category as a dashboard family card with an acronym derived from its name.

**Architecture:** Keep the clean category name in `asset_groups.name` and persist the automatically allocated sequence in the existing `sort_order` column. Build dashboard family metrics from region-scoped `AssetGroup` records rather than a fixed five-item list, deriving the display code centrally in PHP and passing color/name/code to Vue.

**Tech Stack:** Laravel 13, Eloquent, Inertia, Vue 3, PHPUnit, Vitest.

---

### Task 1: Automatic root-category ordering

**Files:**
- Modify: `tests/Feature/Admin/AssetCategoryManagementTest.php`
- Modify: `app/Http/Controllers/Admin/AssetCategoryNodeController.php`
- Modify: `resources/js/pages/Admin/AssetCategories/Index.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/CategoryDialog.vue`

- [ ] Add a feature test that creates a root category without `sort_order`, expects the next regional order, and confirms the clean name is stored.
- [ ] Run the focused PHPUnit test and confirm it fails because the request currently defaults to zero.
- [ ] Allocate `max(sort_order) + 1` while holding a regional group lock, and hide the manual order field for create mode.
- [ ] Run the focused test and confirm it passes.

### Task 2: Dynamic dashboard family cards

**Files:**
- Modify: `tests/Feature/RamsDashboardBackendTest.php`
- Modify: `tests/js/Dashboard.test.js`
- Modify: `app/Services/RamsDashboardQuery.php`
- Modify: `resources/js/pages/dashboard/Dashboard.vue`

- [ ] Add backend coverage asserting `DAYA SATU` produces code `DS`, retains zero asset metrics, and remains scoped to its unit.
- [ ] Add frontend coverage asserting an unknown backend family renders as a card.
- [ ] Run both focused tests and confirm they fail because backend and frontend are fixed to five codes.
- [ ] Build family payloads from scoped groups, derive initials after removing numeric prefixes, preserve standard codes/colors, and render backend-provided families in Vue.
- [ ] Run both focused tests and confirm they pass.

### Task 3: Regression verification

**Files:**
- Verify all modified PHP and Vue files.

- [ ] Run the relevant PHP feature suites.
- [ ] Run the relevant Vitest suites.
- [ ] Run Pint on changed PHP files.
- [ ] Run the production Vite build.
- [ ] Review `git diff` to ensure unrelated worktree changes were preserved.
