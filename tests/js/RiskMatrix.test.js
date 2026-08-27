import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import RiskMatrix from '@/pages/dashboard/RiskMatrix.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

const mountPage = () => mount(RiskMatrix, {
  props: {
    selected_area: 'DAOP-1',
    units: [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1' }],
    risks: [
      { id: 1, system: 'Interlocking', subsystem: 'Interlocking Elektrik', likelihood: 4, consequence: 4, level: 'Extreme', last_update: '2026-08-27' },
      { id: 2, system: 'Persinyalan', subsystem: 'Track Circuit', likelihood: 1, consequence: 1, level: 'Low', last_update: '2026-08-27' },
    ],
  },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      AreaSelectorBanner: true,
    },
  },
})

describe('RiskMatrix', () => {
  it('links a new assessment to the selected area and create form', () => {
    const wrapper = mountPage()

    expect(wrapper.get('[data-testid="create-risk-assessment"]').attributes('href'))
      .toBe('/risk-register?area=DAOP-1&create=1')
  })

  it('uses a compact neutral shell while retaining semantic matrix colours', () => {
    const wrapper = mountPage()
    const pageHeader = wrapper.get('[data-testid="risk-page-header"]')
    const distributionRows = wrapper.findAll('[data-testid="risk-distribution-row"]')

    expect(pageHeader.classes()).toContain('bg-white')
    expect(pageHeader.classes().join(' ')).not.toMatch(/gradient|shadow-lg/)
    expect(distributionRows).toHaveLength(4)
    distributionRows.forEach(row => {
      expect(row.classes()).toContain('bg-white')
      expect(row.classes().join(' ')).not.toMatch(/bg-(rose|orange|yellow|emerald)-50/)
    })
    expect(wrapper.find('[data-risk-level="Extreme"]').exists()).toBe(true)
    expect(wrapper.find('[data-risk-level="Low"]').exists()).toBe(true)
  })
})
