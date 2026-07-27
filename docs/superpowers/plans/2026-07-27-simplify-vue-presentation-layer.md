# Simplify Vue Presentation Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the redundant `presentation/views` wrapper layer so each Inertia Page contains its actual page implementation.

**Architecture:** Inertia Pages remain grouped by feature under `resources/js/pages`. Reusable UI stays separate under top-level `components` and `layouts`, static frontend assets move to `resources/js/assets`, and page-specific route names remain unchanged.

**Tech Stack:** Laravel 13, Inertia.js 3, Vue 3, Vite 8, Tailwind CSS 4

---

### Task 1: Move reusable presentation infrastructure

**Files:**
- Move: `resources/js/presentation/layouts/MainLayout.vue` → `resources/js/layouts/MainLayout.vue`
- Move: `resources/js/presentation/components/base/BaseButton.vue` → `resources/js/components/base/BaseButton.vue`
- Move: `resources/js/presentation/components/dashboard/AreaSelectorBanner.vue` → `resources/js/components/dashboard/AreaSelectorBanner.vue`
- Move: `resources/js/presentation/views/input-data/trouble-report/TroubleReportModal.vue` → `resources/js/components/trouble-report/TroubleReportModal.vue`
- Move: `resources/js/presentation/assets/logo-kai.png` → `resources/js/assets/logo-kai.png`
- Delete: `resources/js/presentation/assets/main.css`

- [x] **Step 1: Move layout, components, modal, and logo**

Use `apply_patch` moves so Git can preserve file history where possible.

- [x] **Step 2: Update the layout logo import**

Change:

```js
import logoKai from '../assets/logo-kai.png'
```

The relative import remains valid after both files move to top-level `layouts` and `assets`.

- [x] **Step 3: Verify reusable files exist**

Run:

```powershell
rg --files resources/js/layouts resources/js/components resources/js/assets
```

Expected: `MainLayout.vue`, both reusable components, `TroubleReportModal.vue`, and `logo-kai.png` are listed.

### Task 2: Merge full view implementations into Inertia Pages

**Files:**
- Replace: `resources/js/pages/auth/Login.vue`
- Replace: `resources/js/pages/dashboard/Dashboard.vue`
- Replace: `resources/js/pages/dashboard/Overview.vue`
- Replace: `resources/js/pages/dashboard/RiskMatrix.vue`
- Replace: `resources/js/pages/input-data/TroubleReport.vue`
- Replace: `resources/js/pages/master-data/assets/MasterAsset.vue`
- Replace: `resources/js/pages/master-data/inventory/Inventory.vue`
- Replace: `resources/js/pages/master-data/inventory/ReorderStock.vue`

- [x] **Step 1: Remove the eight thin wrapper pages**

Delete the page files that only render a single `*View` component.

- [x] **Step 2: Move each full view to its matching page path**

Map the implementations as follows:

```text
LoginView.vue         -> pages/auth/Login.vue
AssetSelectorView.vue -> pages/dashboard/Dashboard.vue
OverviewView.vue      -> pages/dashboard/Overview.vue
RiskMatrixView.vue    -> pages/dashboard/RiskMatrix.vue
TroubleReportView.vue -> pages/input-data/TroubleReport.vue
MasterAssetView.vue   -> pages/master-data/assets/MasterAsset.vue
InventoryView.vue     -> pages/master-data/inventory/Inventory.vue
ReorderStockView.vue  -> pages/master-data/inventory/ReorderStock.vue
```

- [x] **Step 3: Preserve Trouble Report props**

Keep the existing page contract:

```js
const props = defineProps({
  subsystem: {
    type: String,
    default: 'Subsystem Tidak Diketahui',
  },
})
```

### Task 3: Rewrite imports for the simplified structure

**Files:**
- Modify: all eight Inertia Page files
- Modify: `resources/js/layouts/MainLayout.vue`
- Modify: `resources/js/components/trouble-report/TroubleReportModal.vue`

- [x] **Step 1: Replace presentation layout imports**

Use:

```js
import MainLayout from '@/layouts/MainLayout.vue'
```

- [x] **Step 2: Replace reusable component imports**

Use:

```js
import BaseButton from '@/components/base/BaseButton.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'
import TroubleReportModal from '@/components/trouble-report/TroubleReportModal.vue'
```

- [x] **Step 3: Replace the Dashboard style reference**

Use the canonical Tailwind entrypoint:

```css
@reference "../../../css/app.css";
```

- [x] **Step 4: Confirm no source reference uses `presentation`**

Run:

```powershell
rg -n "presentation" resources/js
```

Expected: no matches.

### Task 4: Synchronize project documentation

**Files:**
- Modify: `README.md`
- Modify: `ai-docs/PRD_RAMS_Dashboard.md`
- Modify: `ai-docs/task.md`
- Modify: `ai-docs/UI_UX_Design_System.md`
- Modify: `ai-docs/walkthrough.md`

- [x] **Step 1: Replace the documented frontend tree**

Document top-level `pages`, `components`, `layouts`, and `assets`; remove `presentation`.

- [x] **Step 2: Update architecture explanations**

State that Inertia Pages contain page UI directly and reusable units live under `components` or `layouts`.

- [x] **Step 3: Scan for stale paths**

Run:

```powershell
rg -n "presentation/views|presentation/layouts|presentation/components|presentation/assets" README.md ai-docs
```

Expected: no matches.

### Task 5: Validate the refactor

**Files:**
- Verify: `resources/js/app.js`
- Verify: `routes/web.php`
- Verify: all moved Vue files

- [x] **Step 1: Confirm every Inertia route maps to an existing page**

Resolve every `Inertia::render('domain/Page')` value to `resources/js/pages/domain/Page.vue`.

- [x] **Step 2: Check formatting**

Run:

```powershell
git diff --check
```

Expected: no output.

- [x] **Step 3: Build the frontend**

Run:

```powershell
npm.cmd run build
```

Expected: Vite completes with `built` and exit code `0`.
