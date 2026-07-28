import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AssetForm from '@/pages/master-data/assets/Partials/AssetForm.vue'

const state = vi.hoisted(() => ({
  post: vi.fn(),
  put: vi.fn(),
  errors: {},
}))

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  useForm: (values) => ({
    ...values,
    errors: state.errors,
    processing: false,
    post: state.post,
    put: state.put,
  }),
}))

const units = [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }]
const statusOptions = [
  { value: 'aktif', label: 'Aktif' },
  { value: 'nonaktif', label: 'Nonaktif' },
  { value: 'dalam_perbaikan', label: 'Dalam perbaikan' },
]

const mountForm = (overrides = {}) => mount(AssetForm, {
  props: {
    asset: null,
    units,
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
        aset_prasarana_sintel: 'Peralatan Luar Sinyal Elektrik',
        system: 'Peraga Sinyal Elektrik',
        subsystem: 'Track Circuit',
        lokasi: 'Stasiun Gambir',
        jumlah_unit: 12,
        tanggal_pemasangan: '2012-01-01',
        status: 'aktif',
      },
      submitLabel: 'Simpan perubahan',
    })

    expect(wrapper.get('#nama-aset').element.value).toBe('Track Circuit Backend')
    expect(wrapper.get('#lokasi').element.value).toBe('Stasiun Gambir')
    await wrapper.get('form').trigger('submit')

    expect(state.put).toHaveBeenCalledWith('/master-asset/41', expect.objectContaining({ preserveScroll: true }))
  })
})
