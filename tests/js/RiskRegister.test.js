import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import RiskRegister from '@/pages/risk-register/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router: { delete: vi.fn() },
  useForm: values => ({ ...values, errors: {}, processing: false, defaults: vi.fn(), reset: vi.fn(), clearErrors: vi.fn(), post: vi.fn(), put: vi.fn() }),
}))

const register = {
  id: 1, asset_id: 3, part_number: 'RLY-01', risk_event: 'Relay gagal', risk_cause: 'Kontak aus',
  likelihood: 2, consequence: 3, rating: 6, status: 'open', source: 'excel',
  asset: { name: 'Interlocking', location: 'Jakarta', unit: { code: 'DAOP-1' } },
}

describe('RiskRegister', () => {
  it('shows register data and opens the editor', async () => {
    const wrapper = mount(RiskRegister, {
      props: { assets: [{ id: 3, name: 'Interlocking', unit: { code: 'DAOP-1' } }], registers: [register] },
      global: { stubs: { MainLayout: { template: '<main><slot /></main>' }, AreaSelectorBanner: true } },
    })

    expect(wrapper.text()).toContain('Relay gagal')
    expect(wrapper.text()).toContain('2 × 3 = 6')
    expect(wrapper.text()).toContain('Excel LxC')
    await wrapper.get('[aria-label="Edit risk register"]').trigger('click')
    expect(wrapper.text()).toContain('Edit Risk Register')
  })

  it('submits and deletes within the selected area', async () => {
    const wrapper = mount(RiskRegister, {
      props: {
        selected_area: 'DAOP-1',
        can_choose_unit: true,
        units: [{ id: 7, code: 'DAOP-1', name: 'Daerah Operasi 1' }],
        assets: [{ id: 3, name: 'Interlocking', unit: { code: 'DAOP-1' } }],
        registers: [register],
      },
      global: { stubs: { MainLayout: { template: '<main><slot /></main>' }, AreaSelectorBanner: true } },
    })

    await wrapper.get('button').trigger('click')
    expect(wrapper.vm.form?.unit_kerja_id ?? '').not.toBe('')
  })
})
