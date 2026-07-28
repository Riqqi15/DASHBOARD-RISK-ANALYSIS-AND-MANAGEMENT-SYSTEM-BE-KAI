import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MasterAsset from '@/pages/master-data/assets/MasterAsset.vue'

const inertia = vi.hoisted(() => ({
  get: vi.fn(),
  delete: vi.fn(),
}))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: inertia,
  usePage: () => ({
    url: '/master-asset',
    props: { auth: { user: { id: 1, name: 'Administrator', role: 'pusat' } } },
  }),
}))

const props = {
  assets: {
    data: [{
      id: 41,
      unit_kerja_id: 1,
      unit_kerja: { id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
      nama_aset: 'Track Circuit Backend',
      aset_prasarana_sintel: 'Peralatan Luar Sinyal Elektrik',
      system: 'Peraga Sinyal Elektrik',
      subsystem: 'Track Circuit',
      lokasi: null,
      jumlah_unit: 12,
      tanggal_pemasangan: '2012-01-01',
      status: 'aktif',
    }],
    links: [],
    from: 1,
    to: 1,
    total: 1,
  },
  stats: { total_assets: 1, total_units: 12, active_assets: 1, unique_subsystems: 1 },
  filters: { search: '', status: '', unit_kerja_id: '' },
  units: [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }],
  statusOptions: [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
    { value: 'dalam_perbaikan', label: 'Dalam perbaikan' },
  ],
  can: { choose_unit: true },
}

const mountPage = () => mount(MasterAsset, {
  props,
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      Teleport: true,
    },
  },
})

describe('MasterAsset', () => {
  beforeEach(() => {
    inertia.get.mockReset()
    inertia.delete.mockReset()
  })

  it('renders backend assets, statistics, and the empty location label', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Track Circuit Backend')
    expect(wrapper.text()).toContain('DAOP-1')
    expect(wrapper.text()).toContain('Belum dilengkapi')
    expect(wrapper.text()).toContain('12')
  })

  it('requests server-side filters', async () => {
    const wrapper = mountPage()
    await wrapper.get('#asset-search').setValue('track')
    await wrapper.get('#asset-status').setValue('aktif')
    await wrapper.get('form').trigger('submit')

    expect(inertia.get).toHaveBeenCalledWith('/master-asset', expect.objectContaining({
      search: 'track',
      status: 'aktif',
    }), expect.objectContaining({ preserveState: true, replace: true }))
  })

  it('requires confirmation before deleting an asset', async () => {
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Hapus aset Track Circuit Backend"]').trigger('click')

    expect(wrapper.get('[role="dialog"]').text()).toContain('Track Circuit Backend')
    const confirmButton = wrapper.findAll('button').find((button) => button.text() === 'Hapus aset')
    await confirmButton.trigger('click')

    expect(inertia.delete).toHaveBeenCalledWith('/master-asset/41', expect.objectContaining({ preserveScroll: true }))
  })
})
