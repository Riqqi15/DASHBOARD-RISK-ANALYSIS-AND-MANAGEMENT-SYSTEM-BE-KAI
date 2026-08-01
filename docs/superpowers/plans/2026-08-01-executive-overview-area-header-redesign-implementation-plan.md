# Executive Overview Area Header Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the shared Area Lintas banner in a responsive KAI corporate style while preserving its existing authorization and Inertia filtering behavior.

**Architecture:** Keep the change isolated to the shared Vue banner component used by dashboard pages. Add a focused component test that mocks authentication and Inertia navigation, then replace only the template styling and add derived active-area state; backend props and routes remain unchanged.

**Tech Stack:** Vue 3 Composition API, Inertia.js, Tailwind CSS v4, Lucide Vue, Vitest, Vue Test Utils

**Commit policy:** No commit steps are included because the user explicitly requested that the current work remain uncommitted.

---

### Task 1: Lock the component behavior with tests

**Files:**
- Create: `tests/js/AreaSelectorBanner.test.js`
- Test: `tests/js/AreaSelectorBanner.test.js`

- [ ] **Step 1: Add mocks and the component mounting helper**

```js
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  router: { get: routerGet },
  usePage: () => ({
    props: { auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin.pusat', role: 'pusat', unit_kerja: null } } },
  }),
}))

import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'

const units = [
  { id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
  { id: 2, code: 'DIVRE-I', name: 'Divisi Regional I' },
]

const mountBanner = (selectedArea = null) => mount(AreaSelectorBanner, {
  props: { units, selectedArea },
})
```

- [ ] **Step 2: Add rendering, accessibility, and navigation assertions**

```js
describe('AreaSelectorBanner', () => {
  beforeEach(() => {
    routerGet.mockReset()
    window.history.replaceState({}, '', '/overview')
  })

  it('renders all areas and marks the current selection', () => {
    const wrapper = mountBanner('DAOP-1')

    expect(wrapper.text()).toContain('Area Lintas')
    expect(wrapper.text()).toContain('Dashboard Risk Analysis and Management System')
    expect(wrapper.get('[data-area-code="DAOP-1"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('[data-area-code="national"]').attributes('aria-pressed')).toBe('false')
  })

  it('navigates with the selected unit code', async () => {
    const wrapper = mountBanner()
    await wrapper.get('[data-area-code="DIVRE-I"]').trigger('click')

    expect(routerGet).toHaveBeenCalledWith('/overview', { area: 'DIVRE-I' }, {
      preserveScroll: true,
      preserveState: false,
      replace: true,
    })
  })
})
```

- [ ] **Step 3: Run the focused test and confirm the new selectors fail before implementation**

Run: `rtk npm run test:js -- tests/js/AreaSelectorBanner.test.js`

Expected: FAIL because `data-area-code` and `aria-pressed` are not present yet.

### Task 2: Implement the corporate KAI area banner

**Files:**
- Modify: `resources/js/components/dashboard/AreaSelectorBanner.vue`
- Test: `tests/js/AreaSelectorBanner.test.js`

- [ ] **Step 1: Add presentation-only derived state and icons**

```js
import { computed } from 'vue'
import { Check, MapPinned } from 'lucide-vue-next'

const props = defineProps({
  units: {
    type: Array,
    default: () => [],
  },
  selectedArea: {
    type: String,
    default: null,
  },
})
const activeAreaLabel = computed(() => props.selectedArea || 'Nasional (Pusat)')
const isActive = (code) => code === props.selectedArea
```

- [ ] **Step 2: Replace the banner template with the approved responsive structure**

The new template must contain:

```vue
<section v-if="currentUser.isPusat()" aria-labelledby="area-lintas-title">
  <header>
    <MapPinned aria-hidden="true" />
    <h2 id="area-lintas-title">Area Lintas</h2>
    <span>Aktif: {{ activeAreaLabel }}</span>
  </header>
  <div role="group" aria-label="Pilih area lintas">
    <button data-area-code="national" :aria-pressed="selectedArea === null" @click="selectArea(null)">
      <Check v-if="selectedArea === null" aria-hidden="true" />
      Nasional (Pusat)
    </button>
    <button v-for="area in units" :key="area.id" :data-area-code="area.code" :aria-pressed="isActive(area.code)" @click="selectArea(area.code)">
      <Check v-if="isActive(area.code)" aria-hidden="true" />
      {{ area.code }}
    </button>
  </div>
  <footer>
    <p>Executive intelligence</p>
    <h1>Dashboard Risk Analysis and Management System</h1>
  </footer>
</section>
```

Apply Tailwind classes for a pale-blue panel, navy KAI active state, orange accent, soft borders and shadows, wrapping area chips, `focus-visible` rings, and mobile-safe typography. Do not change `selectArea()`.

- [ ] **Step 3: Run the focused component test**

Run: `rtk npm run test:js -- tests/js/AreaSelectorBanner.test.js`

Expected: both tests PASS.

### Task 3: Regression and production verification

**Files:**
- Verify: `resources/js/components/dashboard/AreaSelectorBanner.vue`
- Verify: `resources/js/pages/dashboard/Overview.vue`
- Verify: `tests/js/AreaSelectorBanner.test.js`
- Verify: `tests/js/Overview.test.js`

- [ ] **Step 1: Run the full JavaScript test suite**

Run: `rtk npm run test:js`

Expected: all JavaScript tests PASS.

- [ ] **Step 2: Build the production frontend**

Run: `rtk npm run build`

Expected: Vite exits successfully with generated production assets.

- [ ] **Step 3: Inspect the rendered page at desktop and mobile widths**

Open `/overview` using the existing local application, verify chip wrapping, selected-area contrast, focus states, and absence of horizontal page overflow. If browser automation is unavailable, report that limitation separately from the automated results.
