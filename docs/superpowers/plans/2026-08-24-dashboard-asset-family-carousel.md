# Dashboard Asset Family Carousel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the wrapping asset-family grid with a responsive, accessible horizontal carousel that shows seven redesigned cards on wide desktops.

**Architecture:** Keep the feature inside the existing dashboard component. Use native overflow scrolling, CSS scroll snap, Vue refs for boundary state, and the installed Lucide arrows; add no dependency and preserve the current backend contract.

**Tech Stack:** Vue 3 Composition API, Inertia.js, Tailwind CSS, scoped CSS, Lucide Vue, Vitest, Vue Test Utils.

---

### Task 1: Specify carousel behavior with component tests

**Files:**
- Modify: `tests/js/Dashboard.test.js`

- [ ] **Step 1: Add failing tests for navigation semantics and scrolling**

Add a test with eight `reliabilityGroups`. Assert that the dashboard renders a focusable `[data-family-track]`, previous and next controls with Indonesian accessible labels, and all eight cards in one track. Stub the track geometry and `scrollBy`:

```js
const track = wrapper.get('[data-family-track]').element
Object.defineProperties(track, {
  clientWidth: { configurable: true, value: 700 },
  scrollWidth: { configurable: true, value: 1000 },
  scrollLeft: { configurable: true, writable: true, value: 0 },
})
track.scrollBy = vi.fn()
window.dispatchEvent(new Event('resize'))
await nextTick()

expect(wrapper.get('[aria-label="Geser kelompok aset ke kiri"]').attributes('disabled')).toBeDefined()
expect(wrapper.get('[aria-label="Geser kelompok aset ke kanan"]').attributes('disabled')).toBeUndefined()
await wrapper.get('[aria-label="Geser kelompok aset ke kanan"]').trigger('click')
expect(track.scrollBy).toHaveBeenCalledWith(expect.objectContaining({ left: expect.any(Number) }))
```

- [ ] **Step 2: Update the visual-contract assertions**

Replace the old fluid-grid assertion with carousel and card-anatomy assertions:

```js
expect(wrapper.get('[data-family-track]').classes()).toContain('family-metrics__track')
expect(wrapper.get('[data-family-code="PDSE"] .family-metric__code').text()).toBe('PDSE')
expect(wrapper.get('[data-family-code="PDSE"] header').attributes('style')).toContain('border-top-color')
```

Keep the existing empty-state, imported-color, dynamic-card, and latest-import assertions.

- [ ] **Step 3: Run the focused test and confirm failure**

Run:

```bash
npm run test:js -- tests/js/Dashboard.test.js
```

Expected: FAIL because `[data-family-track]`, arrow buttons, and the redesigned code element do not exist.

### Task 2: Implement the native carousel and card redesign

**Files:**
- Modify: `resources/js/pages/dashboard/Dashboard.vue`
- Test: `tests/js/Dashboard.test.js`

- [ ] **Step 1: Add the carousel structure**

Replace the wrapping grid with a `data-family-track` horizontal track. Add previous and next buttons to the section heading, using `ChevronLeft` and the existing `ChevronRight`. Keep every family article inside the same track and preserve `data-family-code`.

The track must use:

```vue
ref="familyTrack"
data-family-track
tabindex="0"
aria-label="Daftar kinerja kelompok aset"
@scroll="updateFamilyScrollState"
```

Buttons must use `type="button"`, Indonesian `aria-label` values, and `:disabled` bindings.

- [ ] **Step 2: Add minimal scroll state**

Import `nextTick`, `onBeforeUnmount`, `onMounted`, `ref`, and `watch`. Add:

```js
const familyTrack = ref(null)
const canScrollFamilyLeft = ref(false)
const canScrollFamilyRight = ref(false)
const hasFamilyOverflow = computed(() => canScrollFamilyLeft.value || canScrollFamilyRight.value)

const updateFamilyScrollState = () => {
  const track = familyTrack.value
  if (!track) return
  const maxScroll = Math.max(track.scrollWidth - track.clientWidth, 0)
  canScrollFamilyLeft.value = track.scrollLeft > 2
  canScrollFamilyRight.value = track.scrollLeft < maxScroll - 2
}

const scrollFamilyTrack = (direction) => {
  const track = familyTrack.value
  if (!track) return
  track.scrollBy({
    left: direction * Math.max(track.clientWidth - 32, 1),
    behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
  })
}
```

Register one resize listener, remove it on unmount, and refresh the boundary state after `reliabilityGroups.length` changes.

- [ ] **Step 3: Apply the operational card visual**

Use a neutral card with a dynamic `borderTopColor`, compact `.family-metric__code` badge, two-line clamped family name, aligned metric rows, small radius, thin border, and nearly flat shadow. Keep the asset color visible on the accent and badge while preserving readable contrast.

Replace the grid CSS with a non-wrapping track:

```css
.family-metrics__track {
  --family-gap: 0.625rem;
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: min(82%, 13rem);
  gap: var(--family-gap);
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  overscroll-behavior-inline: contain;
}

@media (min-width: 640px) {
  .family-metrics__track {
    grid-auto-columns: calc((100% - 3 * var(--family-gap)) / 4);
  }
}

@media (min-width: 1280px) {
  .family-metrics__track {
    grid-auto-columns: calc((100% - 6 * var(--family-gap)) / 7);
  }
}
```

Add visible focus treatment, 44-pixel arrow targets, disabled states, scroll snapping, hidden visual scrollbar, and reduced-motion handling.

- [ ] **Step 4: Run focused tests**

Run:

```bash
npm run test:js -- tests/js/Dashboard.test.js
```

Expected: all dashboard tests pass.

### Task 3: Verify build and rendered layout

**Files:**
- Verify: `resources/js/pages/dashboard/Dashboard.vue`
- Verify: `tests/js/Dashboard.test.js`

- [ ] **Step 1: Run frontend verification**

Run:

```bash
npm run test:js
npm run build
git diff --check
```

Expected: 0 failed tests, successful Vite build, and no whitespace errors.

- [ ] **Step 2: Run the Impeccable detector once**

Run:

```bash
node C:/Users/riyadh/.agents/skills/impeccable/scripts/detect.mjs --json --scope layout resources/js/pages/dashboard/Dashboard.vue
```

Expected: no unexplained layout or accessibility findings.

- [ ] **Step 3: Inspect desktop and mobile renders**

Use the local application in the browser. Confirm desktop shows seven cards, mobile shows a horizontal continuation, cards never wrap, arrow states match the scroll position, long names remain within two lines, and all existing dashboard sections remain unchanged.

- [ ] **Step 4: Commit only the scoped files**

```bash
git add resources/js/pages/dashboard/Dashboard.vue tests/js/Dashboard.test.js docs/superpowers/plans/2026-08-24-dashboard-asset-family-carousel.md
git commit -m "Refine dashboard asset family carousel"
```

Expected: unrelated existing working-tree changes remain unstaged.
