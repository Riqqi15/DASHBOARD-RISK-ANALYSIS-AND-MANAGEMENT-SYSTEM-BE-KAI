import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AssetForm from '@/pages/master-data/assets/Partials/AssetForm.vue'
import Create from '@/pages/master-data/assets/Create.vue'
import Edit from '@/pages/master-data/assets/Edit.vue'

const state = vi.hoisted(() => ({
  post: vi.fn(),
  put: vi.fn(),
  errors: {},
  initialValues: null,
  form: null,
}))

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  Head: { template: '<div />' },
  useForm: (values) => {
    state.initialValues = values
    state.form = {
      ...values,
      errors: state.errors,
      processing: false,
      post: state.post,
      put: state.put,
    }
    return state.form
  },
}))

const units = [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }]
const statusOptions = [
  { value: 'aktif', label: 'Aktif' },
  { value: 'nonaktif', label: 'Nonaktif' },
  { value: 'dalam_perbaikan', label: 'Dalam perbaikan' },
]
const categories = [{
  id: 1,
  name: 'Peralatan Luar Sinyal Elektrik',
  systems: [{
    id: 11,
    name: 'Peraga Sinyal Elektrik',
    subsystems: [{ id: 101, name: 'Track Circuit' }],
  }],
}]

const mountForm = (overrides = {}) => mount(AssetForm, {
  props: {
    asset: null,
    units,
    categories,
    statusOptions,
    can: { choose_unit: true },
    submitLabel: 'Simpan aset',
    ...overrides,
  },
})

describe('AssetForm', () => {
  beforeEach(() => {
    state.post.mockReset()
    state.put.mockReset()
    state.errors = {}
  })

  it('shows the unit selector to pusat and submits a new asset', async () => {
    const wrapper = mountForm()

    expect(wrapper.find('#unit-kerja').exists()).toBe(true)
    await wrapper.get('#nama-aset').setValue('Track Circuit Baru')
    await wrapper.get('form').trigger('submit')

    expect(state.post).toHaveBeenCalledWith('/master-asset', expect.objectContaining({ preserveScroll: true }))
  })

  it('submits only the subsystem foreign key while preserving nama_aset', async () => {
    const wrapper = mountForm()

    expect(state.initialValues).toHaveProperty('asset_subsystem_id', null)
    expect(state.initialValues).toHaveProperty('nama_aset', '')
    expect(state.initialValues).not.toHaveProperty('aset_prasarana_sintel')
    expect(state.initialValues).not.toHaveProperty('system')
    expect(state.initialValues).not.toHaveProperty('subsystem')
    await wrapper.get('[name="asset_group_id"]').setValue('1')
    await wrapper.get('[name="asset_system_id"]').setValue('11')
    await wrapper.get('[name="asset_subsystem_id"]').setValue('101')
    expect(state.form.asset_subsystem_id).toBe(101)
  })

  it('hides the unit selector from a regional account', () => {
    const wrapper = mountForm({ can: { choose_unit: false }, units: [] })

    expect(wrapper.find('#unit-kerja').exists()).toBe(false)
    expect(wrapper.text()).toContain('Unit kerja mengikuti akun Anda')
  })

  it('pre-fills and updates an existing asset', async () => {
    const wrapper = mountForm({
      asset: {
        id: 41,
        unit_kerja_id: 1,
        nama_aset: 'Track Circuit Backend',
        asset_subsystem_id: 101,
        lokasi: 'Stasiun Gambir',
        jumlah_unit: 12,
        tanggal_pemasangan: '2012-01-01',
        status: 'aktif',
      },
      submitLabel: 'Simpan perubahan',
    })

    expect(wrapper.get('#nama-aset').element.value).toBe('Track Circuit Backend')
    expect(wrapper.get('#lokasi').element.value).toBe('Stasiun Gambir')
    expect(wrapper.get('[name="asset_subsystem_id"]').element.value).toBe('101')
    await wrapper.get('form').trigger('submit')

    expect(state.put).toHaveBeenCalledWith('/master-asset/41', expect.objectContaining({ preserveScroll: true }))
  })

  it('forwards backend categories through create and edit pages', () => {
    const shared = {
      units,
      categories,
      statusOptions,
      can: { choose_unit: true },
    }
    const global = {
      stubs: {
        MainLayout: { template: '<main><slot /></main>' },
        AssetForm: { name: 'AssetForm', props: ['asset', 'categories'], template: '<div data-form />' },
      },
    }

    const create = mount(Create, { props: shared, global })
    expect(create.getComponent({ name: 'AssetForm' }).props('categories')).toEqual(categories)

    const asset = { id: 41, nama_aset: 'Track Circuit', asset_subsystem_id: 101 }
    const edit = mount(Edit, { props: { ...shared, asset }, global })
    expect(edit.getComponent({ name: 'AssetForm' }).props('categories')).toEqual(categories)
  })
})
