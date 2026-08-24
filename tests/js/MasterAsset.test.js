import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MasterAsset from '@/pages/master-data/assets/MasterAsset.vue'
import AssetHierarchyTable from '@/pages/master-data/assets/Partials/AssetHierarchyTable.vue'
import AssetHierarchyCard from '@/pages/master-data/assets/Partials/AssetHierarchyCard.vue'

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
      asset_subsystem_id: 101,
      unit_kerja: { id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
      nama_aset: 'Track Circuit Backend',
      aset_prasarana_sintel: 'Peralatan Luar Sinyal Elektrik',
      system: 'Peraga Sinyal Elektrik',
      subsystem: 'Track Circuit',
      jumlah_unit: 12,
      tanggal_pemasangan: '2012-01-01',
      status: 'aktif',
    }],
    links: [],
    from: 1,
    to: 1,
    total: 1,
  },
  hierarchy: [{
    id: 101,
    name: 'Track Circuit',
    total: 81,
    sparepart_in: 7,
    sparepart_out: 2,
    asset_system: {
      id: 11,
      name: 'Peraga Sinyal Elektrik',
      asset_group: { id: 1, name: 'Peralatan Luar Sinyal Elektrik' },
    },
  }, {
    id: 102,
    name: 'Axle Counter',
    total: 19,
    sparepart_in: 3,
    sparepart_out: 1,
    asset_system: {
      id: 11,
      name: 'Peraga Sinyal Elektrik',
      asset_group: { id: 1, name: 'Peralatan Luar Sinyal Elektrik' },
    },
  }],
  assetCategories: [{
    id: 1,
    name: 'Peralatan Luar Sinyal Elektrik',
    systems: [{
      id: 11,
      name: 'Peraga Sinyal Elektrik',
      subsystems: [
        { id: 101, name: 'Track Circuit' },
        { id: 102, name: 'Axle Counter' },
      ],
    }],
  }],
  legacySummary: null,
  stats: { total_assets: 1, total_units: 12, active_assets: 1, unique_subsystems: 1 },
  filters: { search: '', status: '', unit_kerja_id: '1' },
  units: [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }],
  statusOptions: [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
    { value: 'dalam_perbaikan', label: 'Dalam perbaikan' },
  ],
  can: { choose_unit: true },
}

const mountPage = (overrides = {}) => mount(MasterAsset, {
  props: { ...props, ...overrides },
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

  it('renders backend assets and statistics without a master-asset location', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Track Circuit Backend')
    expect(wrapper.text()).toContain('DAOP-1')
    expect(wrapper.text()).not.toContain('Lokasi')
    expect(wrapper.text()).toContain('12')
    const desktop = wrapper.getComponent(AssetHierarchyTable)
    expect(desktop.text()).toContain('Sparepart IN')
    expect(desktop.text()).toContain('Track Circuit Backend')
    expect(desktop.text()).toContain('Aktif')
    expect(desktop.text()).toContain('100')
    expect(desktop.text()).toContain('Axle Counter')
    expect(desktop.props('showUnit')).toBe(true)
    expect(desktop.text()).toContain('DAOP-1 — Daerah Operasi 1 Jakarta')
    const mobile = wrapper.getComponent(AssetHierarchyCard)
    expect(mobile.props('showUnit')).toBe(true)
    expect(mobile.text()).toContain('DAOP-1 — Daerah Operasi 1 Jakarta')
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

  it('requires one unit and preserves it when clearing secondary filters', async () => {
    const wrapper = mountPage()

    expect(wrapper.get('#asset-unit').text()).not.toContain('Semua unit kerja')
    await wrapper.get('[aria-label="Hapus semua filter"]').trigger('click')

    expect(inertia.get).toHaveBeenCalledWith('/master-asset', {
      search: '', status: '', unit_kerja_id: '1',
    }, expect.objectContaining({ preserveState: true, replace: true }))
  })

  it('switches unit immediately and clears filters from the previous unit', async () => {
    const wrapper = mountPage({
      filters: { search: 'track', status: 'aktif', unit_kerja_id: '1' },
      units: [
        ...props.units,
        { id: 2, code: 'DAOP-2', name: 'Daerah Operasi 2 Bandung' },
      ],
    })

    await wrapper.get('#asset-unit').setValue('2')

    expect(inertia.get).toHaveBeenCalledWith('/master-asset', {
      search: '', status: '', unit_kerja_id: '2',
    }, { preserveState: false, replace: true })
  })

  it('requires confirmation before deleting an asset', async () => {
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Hapus aset Track Circuit Backend"]').trigger('click')

    expect(wrapper.get('dialog').text()).toContain('Track Circuit Backend')
    const confirmButton = wrapper.findAll('button').find((button) => button.text() === 'Hapus aset')
    await confirmButton.trigger('click')

    expect(inertia.delete).toHaveBeenCalledWith('/master-asset/41', expect.objectContaining({ preserveScroll: true }))
  })

  it('distinguishes filtered empty results and clears filters as recovery', async () => {
    const wrapper = mountPage({
      assets: { data: [], links: [], from: null, to: null, total: 0 },
      hierarchy: [],
      legacySummary: null,
      filters: { search: 'track', status: 'aktif', unit_kerja_id: '1' },
    })

    expect(wrapper.text()).toContain('Tidak ada aset sesuai filter')
    expect(wrapper.text()).toContain('Hapus filter')
    await wrapper.get('[data-clear-empty-filters]').trigger('click')
    expect(inertia.get).toHaveBeenCalledWith('/master-asset', {
      search: '', status: '', unit_kerja_id: '1',
    }, expect.objectContaining({ preserveState: true, replace: true }))
  })

  it('offers asset creation when the database is truly empty', () => {
    const wrapper = mountPage({
      assets: { data: [], links: [], from: null, to: null, total: 0 },
      hierarchy: [],
      assetCategories: [],
      legacySummary: null,
      stats: { total_assets: 0, total_units: 0, active_assets: 0, unique_subsystems: 0 },
    })

    expect(wrapper.text()).toContain('Belum ada aset')
    expect(wrapper.findAll('a[href="/master-asset/create"]').some((link) => link.text().includes('Tambah aset pertama'))).toBe(true)
  })

  it('does not render empty global categories as regional operational assets', () => {
    const wrapper = mountPage({
      assets: { data: [], links: [], from: null, to: null, total: 0 },
      hierarchy: [],
      assetCategories: [{ id: 1234, name: '1234', systems: [] }],
      legacySummary: null,
      stats: { total_assets: 0, total_units: 0, active_assets: 0, unique_subsystems: 0 },
    })

    expect(wrapper.text()).not.toContain('1234')
    expect(wrapper.text()).toContain('Belum ada aset')
    expect(wrapper.findComponent(AssetHierarchyTable).exists()).toBe(false)
  })

  it('preserves backend pagination links', () => {
    const wrapper = mountPage({
      assets: {
        ...props.assets,
        links: [
          { label: '&laquo; Previous', url: null, active: false },
          { label: '1', url: '/master-asset?page=1', active: true },
          { label: '2', url: '/master-asset?page=2', active: false },
          { label: 'Next &raquo;', url: '/master-asset?page=2', active: false },
        ],
      },
    })

    expect(wrapper.get('[aria-label="Paginasi Master Aset"]').text()).toContain('Sebelumnya')
    expect(wrapper.get('[aria-label="Paginasi Master Aset"]').text()).toContain('Berikutnya')
    const hierarchy = wrapper.getComponent(AssetHierarchyTable)
    expect(hierarchy.props('rows')).toHaveLength(2)
    expect(hierarchy.text()).toContain('100')
    expect(hierarchy.text()).toContain('Axle Counter')
  })
})
