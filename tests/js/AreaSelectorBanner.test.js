import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  router: { get: routerGet },
  usePage: () => ({
    props: {
      auth: {
        user: {
          id: 1,
          name: 'Admin Pusat',
          username: 'admin.pusat',
          role: 'pusat',
          unit_kerja: null,
        },
      },
    },
  }),
}))

import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'

const units = [
  { id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
  { id: 2, code: 'DIVRE-I', name: 'Divisi Regional I' },
]

const mountBanner = (selectedArea = null, extraProps = {}) => mount(AreaSelectorBanner, {
  props: { units, selectedArea, ...extraProps },
})

describe('AreaSelectorBanner', () => {
  beforeEach(() => {
    routerGet.mockReset()
    window.history.replaceState({}, '', '/dashboard')
    Object.defineProperty(window, 'scrollY', { configurable: true, value: 0, writable: true })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders the compact dashboard command bar with the active area only', () => {
    const wrapper = mountBanner('DAOP-1')

    expect(wrapper.get('[data-dashboard-command-bar]').classes()).toContain('area-selector--sticky')
    expect(wrapper.text()).toContain('Dashboard Persinyalan')
    expect(wrapper.text()).toContain('DAOP-1 — Daerah Operasi 1 Jakarta')
    expect(wrapper.text()).not.toContain('gangguan tercatat')
    expect(wrapper.get('#area-select').element.value).toBe('DAOP-1')
    expect(wrapper.get('[data-area-code="DAOP-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-area-code="national"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Nasional (Pusat)')
    expect(wrapper.find('[data-dashboard-brand-badge]').exists()).toBe(false)
    expect(wrapper.find('.area-selector__icon-box').exists()).toBe(false)
  })

  it('navigates with the selected unit code', async () => {
    const wrapper = mountBanner()
    await wrapper.get('#area-select').setValue('DIVRE-I')

    expect(routerGet).toHaveBeenCalledWith('/dashboard', { area: 'DIVRE-I' }, {
      preserveScroll: true,
      preserveState: false,
      replace: true,
    })
  })

  it('keeps the command bar sticky without switching presentation while scrolling', async () => {
    const wrapper = mountBanner('DAOP-1')

    window.scrollY = 400
    window.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.classes()).toContain('area-selector--sticky')
    expect(wrapper.classes()).not.toContain('area-selector--compact')
    expect(wrapper.text()).toContain('Dashboard Persinyalan')
  })
})
