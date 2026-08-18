# Unlimited Asset Taxonomy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. The user explicitly requested inline execution without sub-agents.

**Goal:** Replace the three-column taxonomy administration surface with an unlimited level hierarchy while preserving Excel compatibility and adding region-scoped asset management and safe subtree archive.

**Architecture:** Add generic level and node tables as the application-facing taxonomy, backfill them from the three legacy tables, and retain legacy IDs as a compatibility bridge for existing import and dashboard services. Assets gain an optional generic node reference; legacy subsystem linkage remains available when a Level 3 ancestor exists. The category page reads a flat generic tree and renders any number of columns client-side.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, MySQL 8, Inertia.js, Vue 3, Tailwind CSS, PHPUnit, Vitest.

---

### Task 1: Create generic taxonomy schema and backfill

**Files:**
- Create: `database/migrations/2026_08_18_000000_create_unlimited_asset_taxonomy.php`
- Create: `app/Models/AssetCategoryLevel.php`
- Create: `app/Models/AssetCategoryNode.php`
- Create: `database/factories/AssetCategoryLevelFactory.php`
- Create: `database/factories/AssetCategoryNodeFactory.php`
- Modify: `app/Models/Asset.php`
- Test: `tests/Feature/UnlimitedAssetTaxonomySchemaTest.php`

- [ ] Write failing schema tests for default Level 1–3, parent relationships, legacy mappings, asset node FK, uniqueness, and soft deletion.
- [ ] Run `php artisan test tests/Feature/UnlimitedAssetTaxonomySchemaTest.php` and confirm failure before implementation.
- [ ] Add `asset_category_levels`, `asset_category_nodes`, and nullable `assets.asset_category_node_id`; make legacy `asset_subsystem_id` nullable for assets placed above Level 3.
- [ ] Backfill levels, group/system/subsystem nodes, and asset links with idempotent insert/update logic. Do not drop legacy tables or columns.
- [ ] Add typed Eloquent relationships, normalization hooks, casts, scopes, and factories.
- [ ] Run the focused schema tests and `php artisan migrate:status`.

### Task 2: Add hierarchy synchronization and subtree queries

**Files:**
- Create: `app/Services/AssetTaxonomyService.php`
- Modify: `app/Services/AssetCategoryResolver.php`
- Modify: `app/Models/Asset.php`
- Test: `tests/Feature/AssetTaxonomyServiceTest.php`

- [ ] Write failing tests for legacy-tree synchronization, idempotence, unlimited depth, path validation, subtree IDs, and resolving the nearest legacy subsystem ancestor.
- [ ] Implement one transaction-safe service that synchronizes legacy Level 1–3 records, creates deeper nodes, validates adjacent parent levels, returns paths/subtrees, and maps assets to nodes.
- [ ] Call synchronization after Excel category resolution so imported categories immediately appear in the generic tree.
- [ ] Automatically map assets created through legacy flows to their corresponding generic node.
- [ ] Run focused service and existing import parity tests.

### Task 3: Add authorization and dynamic taxonomy endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/AssetCategoryLevelController.php`
- Create: `app/Http/Controllers/Admin/AssetCategoryNodeController.php`
- Create: `app/Http/Requests/Admin/StoreAssetCategoryLevelRequest.php`
- Create: `app/Http/Requests/Admin/StoreAssetCategoryNodeRequest.php`
- Create: `app/Http/Requests/Admin/UpdateAssetCategoryNodeRequest.php`
- Create: `app/Policies/AssetCategoryLevelPolicy.php`
- Create: `app/Policies/AssetCategoryNodePolicy.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/UnlimitedAssetTaxonomyManagementTest.php`

- [ ] Write failing tests proving Pusat can append/rename safe levels and manage nodes, regional accounts are read-only, invalid parent levels fail, duplicate sibling names fail, and used structure cannot be hard-deleted.
- [ ] Implement validated level and node CRUD with transactions, locks, audit records, and safe delete blockers.
- [ ] Delegate Level 1–3 node creation to legacy models and synchronize the result; create Level 4+ directly in generic nodes.
- [ ] Expose the category page GET route to authenticated active regional users while keeping taxonomy mutations behind Pusat authorization.
- [ ] Run focused authorization and route tests.

### Task 4: Add region-scoped assets and subtree archive

**Files:**
- Create: `app/Http/Controllers/AssetTaxonomyAssetController.php`
- Create: `app/Http/Controllers/ArchiveAssetTaxonomyBranchController.php`
- Create: `app/Http/Requests/StoreTaxonomyAssetRequest.php`
- Modify: `app/Models/Asset.php`
- Modify historical asset relationships where required to include soft-deleted assets.
- Modify: `routes/web.php`
- Test: `tests/Feature/AssetTaxonomyRegionalAssetTest.php`

- [ ] Write failing tests for Pusat-selected units, regional unit locking, assets attached at Level 1 and deep levels, snapshot field compatibility, and rejection of cross-unit payloads.
- [ ] Write failing tests for preview counts and one-confirmation subtree archive that affects only the active unit and leaves all reports plus other-unit assets intact.
- [ ] Implement asset creation using the existing asset field rules, derive legacy snapshot fields from the node path, and retain the nearest Level 3 ancestor when available.
- [ ] Implement preview and archive endpoints using the same authorized scope and one transaction. Record a batch audit entry.
- [ ] Ensure historical readers can resolve soft-deleted assets.
- [ ] Run focused regional isolation and archive tests.

### Task 5: Return dynamic page data

**Files:**
- Rewrite: `app/Http/Controllers/Admin/AssetCategoryController.php`
- Modify: `resources/js/layouts/MainLayout.vue`
- Test: `tests/Feature/Admin/AssetCategoryManagementTest.php`

- [ ] Write failing response tests for dynamic levels, flat nodes, active path, unit selection, regional lock, node/subtree asset counts, paginated assets, and capability flags.
- [ ] Synchronize the compatibility tree before reading, eager-load required relations, and return only the selected/authorized unit's assets.
- [ ] Show the Kategori Aset navigation entry to regional users while preserving read-only taxonomy capability.
- [ ] Run controller and navigation tests.

### Task 6: Build unlimited columns and regional asset panel

**Files:**
- Rewrite: `resources/js/pages/Admin/AssetCategories/Index.vue`
- Modify: `resources/js/pages/Admin/AssetCategories/Partials/CategoryPanel.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/LevelDialog.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/TaxonomyAssetPanel.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/TaxonomyAssetDialog.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/ArchiveBranchAssetsDialog.vue`
- Modify: `tests/js/AssetCategories.test.js`

- [ ] Write failing Vitest cases for more than three columns, dynamic drill-down, mobile breadcrumb at arbitrary depth, Pusat level controls, regional read-only taxonomy, unit locking, asset panel filtering, asset creation, and archive confirmation counts.
- [ ] Render columns from level definitions and selected node path, with minimum width and horizontal overflow instead of compressing cards.
- [ ] Add Pusat area selector and regional locked-unit context.
- [ ] Put assets in a separate panel below the hierarchy; reuse existing asset terminology and status values.
- [ ] Label the destructive action `Hapus aset wilayah`, display server counts, and never call it `bulk delete` in user-facing text.
- [ ] Preserve keyboard focus, loading, empty, validation, and reduced-motion states.
- [ ] Run focused and full Vitest suites.

### Task 7: Regression, migration, and browser dogfood

**Files:**
- Update documentation only if implementation differs from the approved design.

- [ ] Run `php artisan migrate:fresh --seed` in the test environment or an isolated database and verify backfill counts.
- [ ] Run `php artisan test` serially; isolate and investigate every failure before changing code.
- [ ] Run `npm run test:js`, `npm run build`, and `vendor/bin/pint --test`.
- [ ] Run the Impeccable detector once over changed Vue targets.
- [ ] Dogfood as Admin Pusat, one DAOP account, and one DIVRE account: add Level 4 and Level 5, create nodes, create assets at different depths, archive one regional subtree, verify other regions and historical reports, and test mobile navigation.
- [ ] Commit only after the full verification evidence is green.
