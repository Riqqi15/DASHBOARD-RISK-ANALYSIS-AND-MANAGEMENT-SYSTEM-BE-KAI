import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import Overview from '@/pages/dashboard/Overview.vue'

const mountPage = (overrides = {}) => mount(Overview, {
  props: {
    selected_area: null,
    units: [],
    summary: {
      totalAset: 85,
      risikoExtreme: 16,
      risikoHigh: 14,
      avgAvailability: 0.997,
      totalFailure: 289,
      totalProposalReorder: 0,
    },
    risk_registers: [],
    assets: [],
    failure_trend: [],
    ...overrides,
  },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      AreaSelectorBanner: true,
    },
  },
})

describe('Overview', () => {
  it('renders the national overview without relying on removed dummy auth state', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Total Master Aset')
    expect(wrapper.text()).toContain('Total failure event per bulan (Nasional)')
    expect(wrapper.text()).toContain('85')
  })

  it('renders the selected regional area in the trend description', () => {
    const wrapper = mountPage({ selected_area: 'DAOP-1' })

    expect(wrapper.text()).toContain('Total failure event per bulan (DAOP-1)')
  })
})
