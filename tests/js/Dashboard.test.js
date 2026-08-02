import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  router: { get: routerGet },
}))

import Dashboard from '@/pages/dashboard/Dashboard.vue'

const mountPage = (overrides = {}) => mount(Dashboard, {
  props: {
    selected_area: 'DAOP-1',
    units: [],
    asset_categories: [],
    assets: [
      {
        id: 1,
        unit_kerja_id: 'DAOP1',
        aset_prasarana_sintel: '1234',
        system: '1',
        subsystem: '1',
        lokasi: 'a',
        jumlah_unit: 1,
        status: 'Aktif',
      },
    ],
    ...overrides,
  },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      AreaSelectorBanner: true,
    },
  },
})

describe('Dashboard', () => {
  beforeEach(() => {
    routerGet.mockReset()
  })

  it('renders subsystem groups from master assets', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('1234')
    expect(wrapper.text()).toContain('1 system')
    expect(wrapper.text()).toContain('1 aset')
    expect(wrapper.get('[data-subsystem-name="1"]').text()).toContain('1')
  })

  it('renders asset categories even when they do not have linked master assets yet', () => {
    const wrapper = mountPage({
      assets: [],
      asset_categories: [
        {
          id: 6,
          name: '1234',
          systems: [],
        },
      ],
    })

    expect(wrapper.text()).toContain('1234')
    expect(wrapper.text()).toContain('0 system')
    expect(wrapper.text()).toContain('Belum ada system atau subsystem aktif')
  })

  it('opens trouble report for the selected subsystem and area', async () => {
    const wrapper = mountPage()

    await wrapper.get('[data-subsystem-name="1"]').trigger('click')

    expect(routerGet).toHaveBeenCalledWith('/trouble-report', {
      subsystem: '1',
      area: 'DAOP-1',
    })
  })
})
