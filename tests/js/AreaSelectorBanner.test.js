import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

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

const mountBanner = (selectedArea = null) => mount(AreaSelectorBanner, {
  props: { units, selectedArea },
})

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
    expect(wrapper.find('[data-area-code="national"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Nasional (Pusat)')
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
