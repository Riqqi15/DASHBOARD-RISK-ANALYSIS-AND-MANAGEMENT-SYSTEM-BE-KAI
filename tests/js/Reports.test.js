import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Reports from '@/pages/reports/Index.vue'

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' } }))

describe('Reports', () => {
  it('renders four scoped xlsx and pdf downloads', () => {
    const wrapper = mount(Reports, {
      props: { selected_area: 'DAOP-1', units: [{ id: 1, code: 'DAOP-1', name: 'Daop 1' }] },
      global: { stubs: { MainLayout: { template: '<main><slot /></main>' }, AreaSelectorBanner: true } },
    })

    expect(wrapper.findAll('a[href*="/reports/"]')).toHaveLength(8)
    expect(wrapper.get('a[href="/reports/inventory/xlsx?area=DAOP-1"]').exists()).toBe(true)
    expect(wrapper.get('a[href="/reports/inventory/pdf?area=DAOP-1"]').exists()).toBe(true)
    expect(wrapper.get('a[href="/reports/reliability/xlsx?area=DAOP-1"]').text()).toContain('Excel Berformula')
    expect(wrapper.text()).toContain('Unduh PDF')
    expect(wrapper.text()).toContain('Reliability & Availability')
    expect(wrapper.text()).toContain('satu sheet untuk setiap subsystem')
    expect(wrapper.text()).toContain('mengikuti area yang dipilih')
  })
})
