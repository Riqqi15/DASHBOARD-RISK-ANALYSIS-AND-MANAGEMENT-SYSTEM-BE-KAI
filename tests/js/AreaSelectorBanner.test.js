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

  it('renders all areas and marks the current selection', () => {
    const wrapper = mountBanner('DAOP-1')

    expect(wrapper.text()).toContain('Wilayah data')
    expect(wrapper.text()).toContain('Wilayah kerja')
    expect(wrapper.get('#area-select').element.value).toBe('DAOP-1')
    expect(wrapper.get('[data-area-code="DAOP-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-area-code="national"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Nasional (Pusat)')
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

  it('collapses at the sticky threshold and expands when returning to the top', async () => {
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      callback()
      return 1
    })
    vi.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {})
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({
      bottom: 300,
      height: 200,
      left: 0,
      right: 1000,
      top: 100,
      width: 1000,
      x: 0,
      y: 100,
      toJSON: () => ({}),
    })

    const wrapper = mountBanner('DAOP-1', { collapsible: true })

    window.scrollY = 40
    window.dispatchEvent(new Event('scroll'))
    await nextTick()
    expect(wrapper.classes()).toContain('area-selector--compact')

    window.scrollY = 0
    window.dispatchEvent(new Event('scroll'))
    await nextTick()
    expect(wrapper.classes()).not.toContain('area-selector--compact')
  })
})
