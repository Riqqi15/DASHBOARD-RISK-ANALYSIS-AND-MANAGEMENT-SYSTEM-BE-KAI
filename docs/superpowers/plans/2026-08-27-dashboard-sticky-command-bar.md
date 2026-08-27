# Dashboard Sticky Command Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan.

**Goal:** Replace the dashboard's decorative upper stack with one compact, sticky command bar that keeps the active region selector visible while scrolling.

**Architecture:** Rework the existing `AreaSelectorBanner` into the command bar so its proven Inertia area-routing behavior remains the single source of truth. `Dashboard.vue` will pass the formatted failure count into that component and remove the redundant hero and failure-summary card. No controller, route, query, calculation, or payload shape changes.

**Tech Stack:** Vue 3 `<script setup>`, Inertia.js, scoped CSS, Vitest, Vue Test Utils.

---

### Task 1: Specify the command-bar behavior with failing tests

**Files:**
- Modify: `tests/js/AreaSelectorBanner.test.js`
- Modify: `tests/js/Dashboard.test.js`

**Step 1: Replace the banner presentation assertions**

Update the component test to require:

```js
expect(wrapper.get('[data-dashboard-command-bar]').classes()).toContain('area-selector--sticky')
expect(wrapper.text()).toContain('Dashboard Persinyalan')
expect(wrapper.text()).toContain('DAOP-1 — Daerah Operasi 1 Jakarta')
expect(wrapper.text()).toContain('13 gangguan tercatat')
expect(wrapper.find('[data-dashboard-brand-badge]').exists()).toBe(false)
expect(wrapper.find('.area-selector__icon-box').exists()).toBe(false)
```

Mount the component with `failureCount: '13'`. Keep the existing area-option and Inertia navigation assertions so routing behavior cannot regress.

**Step 2: Replace the dashboard's old hero/card assertion**

Update the dashboard test to require the failure count to be passed to the area selector, and assert the old presentation is absent:

```js
expect(wrapper.find('.dashboard-hero').exists()).toBe(false)
expect(wrapper.find('.failure-stat-card').exists()).toBe(false)
expect(wrapper.text()).not.toContain('KAI RAMS')
```

Use a lightweight `AreaSelectorBanner` test stub that renders its `failureCount` prop so the page-level data flow is observable.

**Step 3: Run the focused tests and verify RED**

Run:

```powershell
rtk npm run test:js -- tests/js/AreaSelectorBanner.test.js tests/js/Dashboard.test.js
```

Expected: FAIL because the current banner still renders the icon/decorative copy, does not accept `failureCount`, and the page still renders the old hero and failure card.

### Task 2: Implement the compact sticky command bar

**Files:**
- Modify: `resources/js/components/dashboard/AreaSelectorBanner.vue`
- Modify: `resources/js/pages/dashboard/Dashboard.vue`

**Step 1: Simplify `AreaSelectorBanner.vue`**

Keep `displayedArea` and `selectArea()` unchanged. Remove the scroll-threshold measurement code, `MapPinned`, decorative icon, subtitle, compact-state transition, and card shadow. Add:

```js
failureCount: { type: [String, Number], default: 0 }
```

Add a computed active unit label from `selectedArea` and `units`. Render one semantic toolbar containing:

- `Dashboard Persinyalan` as the heading;
- active unit code and name as plain supporting text;
- `<strong>{{ failureCount }}</strong> gangguan tercatat` as plain status text;
- the existing accessible area `<select>` for central users.

Make the root always sticky at `top: 76px`, with a white background, one thin bottom border, modest radius, no decorative badge/icon, and no large shadow. On narrow screens, wrap title/status and selector into two rows while preserving a visible keyboard focus state.

**Step 2: Simplify `Dashboard.vue`**

Pass `formattedFailureCount` into the banner:

```vue
<AreaSelectorBanner
  :units="units"
  :selected-area="selected_area"
  :failure-count="formatNumber(failureCountNumber)"
/>
```

Remove the decorative hero and standalone failure card. Remove the unused `FileSpreadsheet` import and all `.dashboard-hero` / `.failure-stat-card*` styles. Leave family metrics and equipment hierarchy unchanged.

**Step 3: Run focused tests and verify GREEN**

Run:

```powershell
rtk npm run test:js -- tests/js/AreaSelectorBanner.test.js tests/js/Dashboard.test.js
```

Expected: both test files pass with no Vue warnings.

**Step 4: Commit the focused implementation**

Stage only the two Vue files and two test files, then commit:

```powershell
rtk git add resources/js/components/dashboard/AreaSelectorBanner.vue resources/js/pages/dashboard/Dashboard.vue tests/js/AreaSelectorBanner.test.js tests/js/Dashboard.test.js
rtk git commit -m "refactor: simplify dashboard command bar"
```

### Task 3: Verify responsive integrity and regression safety

**Files:**
- Verify: `resources/js/components/dashboard/AreaSelectorBanner.vue`
- Verify: `resources/js/pages/dashboard/Dashboard.vue`

**Step 1: Run the complete frontend test suite**

Run:

```powershell
rtk npm run test:js
```

Expected: all frontend tests pass.

**Step 2: Build production assets**

Run:

```powershell
rtk npm run build
```

Expected: Vite completes successfully with exit code 0.

**Step 3: Check formatting and scope**

Run:

```powershell
rtk git diff --check HEAD~1..HEAD
rtk git show --stat --oneline HEAD
```

Expected: no whitespace errors, and the implementation commit contains only the dashboard command-bar files and their tests. Existing unrelated working-tree changes remain unstaged.

**Step 4: Review the requirement checklist**

Confirm from code and tests:

- one sticky top command bar remains visible below the 76px app header;
- title, active region, failure count, and region selector are present;
- no badges, large decorative icon, promotional hero copy, or separate failure card remain;
- mobile layout wraps without hiding the selector;
- area switching still calls the same Inertia route and options;
- no backend file was changed.
