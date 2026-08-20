import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Units from '@/pages/Admin/Units/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { get: vi.fn(), patch: vi.fn() },
}))

describe('Unit Kerja', () => {
  it('expands the unit table across the available card width', () => {
    const wrapper = mount(Units, {
      props: {
        filters: {},
        units: {
          data: [{
            id: 1,
            code: 'DAOP-1',
            name: 'Daerah Operasi 1',
            type: 'daop',
            is_active: true,
            accounts: [],
          }],
          links: [],
        },
      },
      global: {
        stubs: {
          MainLayout: { template: '<main><slot /></main>' },
        },
      },
    })

    expect(wrapper.get('table').classes()).toContain('w-full')
  })
})
