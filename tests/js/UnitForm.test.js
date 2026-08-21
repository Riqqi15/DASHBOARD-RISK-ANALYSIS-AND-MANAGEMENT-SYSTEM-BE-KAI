import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import UnitForm from '@/pages/Admin/Units/Partials/UnitForm.vue'

const state = vi.hoisted(() => ({ put: vi.fn(), form: null }))

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  useForm: values => {
    state.form = reactive({ ...values, errors: {}, processing: false, put: state.put, post: vi.fn() })
    return state.form
  },
}))

const mountForm = () => mount(UnitForm, {
  props: {
    unit: { id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1', type: 'daop', is_active: true, operating_start_date: '' },
    typeOptions: [{ value: 'daop', label: 'Daerah Operasi (Daop)' }],
    importedBaselineDate: '2020-01-01',
    submitLabel: 'Simpan perubahan',
  },
  global: { stubs: { BaseButton: { template: '<button type="submit"><slot /></button>' } } },
})

describe('UnitForm baseline', () => {
  it('shows imported baseline and keeps confirmation hidden before a change', () => {
    const wrapper = mountForm()

    expect(wrapper.text()).toContain('Baseline Operating Days sesuai Excel')
    expect(wrapper.text()).toContain('1 Januari 2020')
    expect(wrapper.text()).toContain('Mengikuti hasil import Excel')
    expect(wrapper.find('#baseline-change-reason').exists()).toBe(false)
  })

  it('requires a reason and confirmation UI after changing the override', async () => {
    const wrapper = mountForm()

    await wrapper.get('#operating_start_date').setValue('2019-01-01')

    expect(wrapper.text()).toContain('Override manual aktif')
    expect(wrapper.get('#baseline-change-reason').attributes('required')).toBeDefined()
    expect(wrapper.get('input[type="checkbox"][required]').exists()).toBe(true)
  })
})
