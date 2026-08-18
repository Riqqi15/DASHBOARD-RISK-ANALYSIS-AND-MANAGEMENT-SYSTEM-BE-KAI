# Collapsible Sticky Area Selector Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the area selector collapse into a compact sticky bar while scrolling without hiding dashboard content.

**Architecture:** Keep scroll behavior inside the shared Vue component and opt in only from Dashboard, Overview, and Risk Matrix. Use passive browser listeners and scoped CSS; add no dependency and leave backend navigation unchanged.

**Tech Stack:** Vue 3 Composition API, Inertia.js, scoped CSS, Vitest, Vue Test Utils.

---

### Task 1: Add tested sticky-collapse state

**Files:**
- Modify: `tests/js/AreaSelectorBanner.test.js`
- Modify: `resources/js/components/dashboard/AreaSelectorBanner.vue`

- [ ] **Step 1: Write the failing behavior test**

Add a test that mounts with `collapsible: true`, stubs the component top position and `requestAnimationFrame`, dispatches scroll at `window.scrollY = 40`, and expects `area-selector--compact`; return to `window.scrollY = 0` and expect the class to be removed.

- [ ] **Step 2: Run the focused test and verify failure**

Run: `npm run test:js -- tests/js/AreaSelectorBanner.test.js`

Expected: FAIL because the `collapsible` state and compact class do not exist yet.

- [ ] **Step 3: Implement minimal component behavior**

Add the Boolean `collapsible` prop, a section ref, and `isCompact` state. On mount, calculate the section's document position, then use passive `scroll` and `resize` listeners to toggle compact mode when the section reaches the 76px application header. Throttle repeated browser events through `requestAnimationFrame` and remove every listener on unmount.

Bind the state to the section:

```vue
<section
  ref="selector"
  :class="[
    'area-selector',
    { 'area-selector--collapsible': collapsible, 'area-selector--compact': collapsible && isCompact },
  ]"
>
```

Add a compact label beside the existing native select and CSS that:

- makes only opted-in selectors sticky at `top: 76px` with `z-index: 20`;
- compresses the heading and help text into a single 52px control row;
- keeps the native select usable and truncates long labels;
- uses a 200ms decelerating transition;
- disables transition under `prefers-reduced-motion`.

- [ ] **Step 4: Run the focused test and verify success**

Run: `npm run test:js -- tests/js/AreaSelectorBanner.test.js`

Expected: all `AreaSelectorBanner` tests PASS.

### Task 2: Enable behavior on the agreed dashboard pages

**Files:**
- Modify: `resources/js/pages/dashboard/Dashboard.vue`
- Modify: `resources/js/pages/dashboard/Overview.vue`
- Modify: `resources/js/pages/dashboard/RiskMatrix.vue`

- [ ] **Step 1: Opt in from each dashboard page**

Change each agreed usage to:

```vue
<AreaSelectorBanner collapsible :units="units" :selected-area="selected_area" />
```

Do not opt in from Reports or Risk Register so their existing layout remains unchanged.

- [ ] **Step 2: Run frontend verification**

Run: `npm run test:js`

Expected: all Vitest suites PASS.

Run: `npm run build`

Expected: Vite production build exits successfully.

- [ ] **Step 3: Verify visually**

At desktop and mobile viewport sizes, confirm the complete panel at page top, compact bar below the application header after scrolling, restoration after scrolling up, visible keyboard focus, and correct area navigation.

- [ ] **Step 4: Run Impeccable detector**

Run: `node C:/Users/riyadh/.agents/skills/impeccable/scripts/detect.mjs --json resources/js/components/dashboard/AreaSelectorBanner.vue resources/js/pages/dashboard/Dashboard.vue resources/js/pages/dashboard/Overview.vue resources/js/pages/dashboard/RiskMatrix.vue`

Expected: no blocking UI quality finding.
