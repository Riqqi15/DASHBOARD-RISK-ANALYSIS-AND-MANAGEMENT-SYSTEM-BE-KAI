# Dashboard Failure Summary Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the recorded-failure total out of the sticky dashboard toolbar into a prominent, non-sticky summary bar.

**Architecture:** Keep `AreaSelectorBanner.vue` responsible only for dashboard identity and regional navigation. Render the failure summary in `Dashboard.vue` from the existing `failureCountNumber` computed value, preserving all backend data contracts and Inertia navigation behavior.

**Tech Stack:** Laravel Inertia, Vue 3, scoped CSS, Vitest, Vue Test Utils, Vite

---

### Task 1: Define the toolbar and summary-bar behavior with tests

**Files:**
- Modify: `tests/js/AreaSelectorBanner.test.js`
- Modify: `tests/js/Dashboard.test.js`

- [ ] **Step 1: Remove the failure-count expectation from the toolbar test**

Mount the component without `failureCount`, assert the regional controls remain, and assert the toolbar does not render `gangguan tercatat`:

```js
const mountBanner = (selectedArea = null, extraProps = {}) => mount(AreaSelectorBanner, {
  props: { units, selectedArea, ...extraProps },
})

expect(wrapper.text()).not.toContain('gangguan tercatat')
```

- [ ] **Step 2: Make the dashboard stub match the toolbar's reduced responsibility**

Replace the stub with:

```js
AreaSelectorBanner: {
  template: '<div data-dashboard-command-bar>Dashboard Persinyalan</div>',
},
```

- [ ] **Step 3: Require a normal-flow failure summary bar**

Update the recorded-failure test with these assertions:

```js
const summaryBar = wrapper.get('[data-failure-summary-bar]')
expect(summaryBar.text()).toContain('Gangguan tercatat')
expect(summaryBar.text()).toContain('9 kejadian')
expect(summaryBar.classes()).not.toContain('failure-summary--sticky')
expect(wrapper.get('[data-dashboard-command-bar]').text()).not.toContain('gangguan tercatat')
```

- [ ] **Step 4: Run focused tests and verify RED**

Run:

```powershell
rtk npm run test:js -- tests/js/AreaSelectorBanner.test.js tests/js/Dashboard.test.js
```

Expected: FAIL because the toolbar still renders the count and `[data-failure-summary-bar]` does not exist.

### Task 2: Separate the sticky toolbar from the failure summary

**Files:**
- Modify: `resources/js/components/dashboard/AreaSelectorBanner.vue`
- Modify: `resources/js/pages/dashboard/Dashboard.vue`

- [ ] **Step 1: Remove failure status from `AreaSelectorBanner.vue`**

Delete the status paragraph and `failureCount` prop. Keep the existing selector, active-area label, and `router.get` call unchanged. The controls container becomes:

```vue
<div v-if="currentUser.isPusat()" class="area-selector__action">
  <label for="area-select" class="sr-only">Wilayah kerja</label>
  <div class="area-selector__select-wrap">
    <select
      id="area-select"
      :value="displayedArea || ''"
      class="area-selector__select"
      @change="selectArea($event.target.value)"
    >
      <option v-if="!units.length" value="">Belum ada wilayah</option>
      <option
        v-for="area in units"
        :key="area.id"
        :value="area.code"
        :data-area-code="area.code"
      >
        {{ areaLabel(area) }}
      </option>
    </select>
    <ChevronDown class="area-selector__select-chevron" :size="18" aria-hidden="true" />
  </div>
</div>
```

Remove `.area-selector__status` styles and keep the toolbar responsive styles focused on the regional selector.

- [ ] **Step 2: Stop passing failure data to the toolbar**

Use:

```vue
<AreaSelectorBanner
  :units="units"
  :selected-area="selected_area"
/>
```

- [ ] **Step 3: Add the normal-flow summary bar**

Place this as the first child of `.dashboard-shell`:

```vue
<section data-failure-summary-bar class="failure-summary" aria-labelledby="failure-summary-title">
  <div>
    <h2 id="failure-summary-title" class="failure-summary__title">Gangguan tercatat</h2>
    <p class="failure-summary__description">Total catatan kegagalan pada wilayah terpilih</p>
  </div>
  <p class="failure-summary__value" aria-live="polite">
    {{ formatNumber(failureCountNumber) }} kejadian
  </p>
</section>
```

- [ ] **Step 4: Style the summary as a plain bar**

Add scoped CSS:

```css
.failure-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  border: 1px solid #dbe3ee;
  background: #ffffff;
  padding: 1rem 1.25rem;
}

.failure-summary__title {
  color: #0f172a;
  font-size: 0.9375rem;
  font-weight: 750;
}

.failure-summary__description {
  margin-top: 0.125rem;
  color: #64748b;
  font-size: 0.8125rem;
}

.failure-summary__value {
  flex-shrink: 0;
  color: #b91c1c;
  font-size: clamp(1.25rem, 2vw, 1.75rem);
  font-weight: 800;
  letter-spacing: -0.025em;
}

@media (max-width: 520px) {
  .failure-summary {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.625rem;
  }
}
```

- [ ] **Step 5: Run focused tests and verify GREEN**

Run:

```powershell
rtk npm run test:js -- tests/js/AreaSelectorBanner.test.js tests/js/Dashboard.test.js
```

Expected: both files pass.

- [ ] **Step 6: Commit the UI change**

```powershell
rtk git add -- resources/js/components/dashboard/AreaSelectorBanner.vue resources/js/pages/dashboard/Dashboard.vue tests/js/AreaSelectorBanner.test.js tests/js/Dashboard.test.js
rtk git commit -m "refactor: separate dashboard failure summary"
```

### Task 3: Verify the frontend

**Files:**
- Verify only; do not modify backend files.

- [ ] **Step 1: Run the complete JavaScript test suite**

Run:

```powershell
rtk cmd /d /c "set NODE_OPTIONS=--localstorage-file=C:\Users\mjoha\AppData\Local\Temp\codex-dashboard-localstorage.json&& npm run test:js"
```

Expected: all Vitest files and tests pass.

- [ ] **Step 2: Build production assets**

Run:

```powershell
rtk npm run build
```

Expected: Vite exits with code 0 and writes production assets.

- [ ] **Step 3: Check diff scope and whitespace**

Run:

```powershell
rtk git diff --check HEAD~1..HEAD
rtk git show --stat --oneline --summary HEAD
rtk git status --short
```

Expected: no whitespace errors; the implementation commit contains only the two Vue files and their two tests; unrelated pre-existing workspace changes remain unstaged.
