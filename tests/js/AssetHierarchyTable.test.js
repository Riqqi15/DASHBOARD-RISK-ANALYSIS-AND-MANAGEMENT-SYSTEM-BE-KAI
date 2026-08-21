import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AssetHierarchyCard from '@/pages/master-data/assets/Partials/AssetHierarchyCard.vue'
import AssetHierarchyTable from '@/pages/master-data/assets/Partials/AssetHierarchyTable.vue'

const rows = [{
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
}]

const assets = [{
  id: 41,
  asset_subsystem_id: 101,
  nama_aset: 'Track Circuit Gambir',
  jumlah_unit: 81,
  status: 'aktif',
  unit_kerja: { id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
  category: {
    group: { id: 1, name: 'Peralatan Luar Sinyal Elektrik' },
    system: { id: 11, name: 'Peraga Sinyal Elektrik' },
    subsystem: { id: 101, name: 'Track Circuit' },
  },
}]
const secondAsset = {
  ...assets[0],
  id: 42,
  nama_aset: 'Track Circuit Manggarai',
  status: 'nonaktif',
}
const statusOptions = [
  { value: 'aktif', label: 'Aktif' },
  { value: 'nonaktif', label: 'Nonaktif' },
]
const legacySummary = { asset_count: 2, total: 20, sparepart_in: 0, sparepart_out: 0 }

describe('AssetHierarchyTable', () => {
  it('renders exactly seven columns with scoped subsystem values and parent subtotals', () => {
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets, legacySummary: null, statusOptions },
      global: { stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } },
    })

    expect(wrapper.findAll('thead th').map((cell) => cell.text())).toEqual([
      'Aset Prasarana Sintel',
      'System',
      'Subsystem',
      'TOTAL',
      'Sparepart IN',
      'Sparepart OUT',
      'Aksi',
    ])
    const subsystem = wrapper.get('[data-subsystem-id="101"]')
    expect(subsystem.text()).toContain('81')
    expect(subsystem.text()).toContain('7')
    expect(subsystem.text()).toContain('2')
    expect(wrapper.get('[data-group-id="1"]').text()).toContain('100')
    expect(wrapper.get('[data-system-id="11"]').text()).toContain('10')
    expect(wrapper.get('[data-subsystem-id="102"]').text()).toContain('19')
    expect(wrapper.get('[data-subsystem-id="102"]').text()).toContain('Detail aset tersedia di halaman lain')
  })

  it('renders seven explicit cells for parent rows without merged cells', () => {
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets, legacySummary: null, statusOptions },
      global: { stubs: { Link: true } },
    })

    expect(wrapper.find('[colspan]').exists()).toBe(false)
    expect(wrapper.get('[data-group-id="1"]').findAll('td')).toHaveLength(7)
    expect(wrapper.get('[data-system-id="11"]').findAll('td')).toHaveLength(7)
  })

  it('shows current-page asset name and status in the desktop subsystem detail', () => {
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets, legacySummary: null, statusOptions },
      global: { stubs: { Link: true } },
    })
    const subsystem = wrapper.get('[data-subsystem-id="101"]')

    expect(subsystem.text()).toContain('Track Circuit Gambir')
    expect(subsystem.text()).toContain('Aktif')
  })

  it('shows the unit inside desktop asset details for pusat and handles missing unit data', () => {
    const pusat = mount(AssetHierarchyTable, {
      props: { rows, assets, legacySummary: null, statusOptions, showUnit: true },
      global: { stubs: { Link: true } },
    })
    const missing = mount(AssetHierarchyTable, {
      props: { rows, assets: [{ ...assets[0], unit_kerja: null }], legacySummary: null, statusOptions, showUnit: true },
      global: { stubs: { Link: true } },
    })

    expect(pusat.get('[data-subsystem-id="101"]').text()).toContain('DAOP-1 — Daerah Operasi 1 Jakarta')
    expect(missing.get('[data-subsystem-id="101"]').text()).toContain('Unit tidak tersedia')
  })

  it('collapses and expands a group with accessible state', async () => {
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets, legacySummary: null, statusOptions },
      global: { stubs: { Link: true } },
    })
    const button = wrapper.get('[data-group-id="1"] button')

    expect(button.attributes('aria-expanded')).toBe('true')
    const groupTargetIds = button.attributes('aria-controls').split(' ')
    expect(groupTargetIds.length).toBeGreaterThan(1)
    for (const id of groupTargetIds) {
      expect(wrapper.get(`#${id}`).isVisible()).toBe(true)
    }
    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('false')
    for (const id of groupTargetIds) {
      expect(wrapper.get(`#${id}`).attributes('style'), `${id} should be hidden`).toContain('display: none')
    }
    await button.trigger('click')
    const systemButton = wrapper.get('[data-system-id="11"] button')
    const systemTargetIds = systemButton.attributes('aria-controls').split(' ')
    await systemButton.trigger('click')
    expect(systemButton.attributes('aria-expanded')).toBe('false')
    for (const id of systemTargetIds) {
      expect(wrapper.get(`#${id}`).attributes('style'), `${id} should be hidden`).toContain('display: none')
    }
  })

  it('keeps edit/delete actions and presents a clear legacy fallback', async () => {
    const legacy = {
      ...assets[0],
      id: 52,
      asset_subsystem_id: null,
      nama_aset: 'Aset Warisan',
      category: null,
      jumlah_unit: 3,
    }
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets: [...assets, legacy], legacySummary, statusOptions },
      global: { stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } },
    })

    expect(wrapper.text()).toContain('Belum diklasifikasikan')
    expect(wrapper.get('[data-subsystem-id="legacy"]').text()).toContain('20')
    expect(wrapper.get('a[href="/master-asset/41/edit"]').exists()).toBe(true)
    await wrapper.get('[aria-label="Hapus aset Track Circuit Gambir"]').trigger('click')
    expect(wrapper.emitted('delete')).toEqual([[assets[0]]])
  })
})

describe('AssetHierarchyCard', () => {
  it('shows breadcrumb, scoped values, asset details, status, and actions', async () => {
    const wrapper = mount(AssetHierarchyCard, {
      props: { rows, assets, legacySummary: null, statusOptions },
      global: { stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } },
    })

    expect(wrapper.text()).toContain('Peralatan Luar Sinyal Elektrik / Peraga Sinyal Elektrik / Track Circuit')
    expect(wrapper.text()).toContain('Track Circuit Gambir')
    expect(wrapper.text()).toContain('Aktif')
    expect(wrapper.text()).toContain('81')
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.get('a[href="/master-asset/41/edit"]').exists()).toBe(true)
    await wrapper.get('[aria-label="Hapus aset Track Circuit Gambir"]').trigger('click')
    expect(wrapper.emitted('delete')).toEqual([[assets[0]]])
  })

  it('groups current-page assets into one aggregate subsystem card', async () => {
    const wrapper = mount(AssetHierarchyCard, {
      props: { rows, assets: [assets[0], secondAsset], legacySummary: null, statusOptions, showUnit: true },
      global: { stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } },
    })
    const card = wrapper.get('[data-subsystem-card="101"]')

    expect(wrapper.findAll('[data-subsystem-card="101"]')).toHaveLength(1)
    expect(card.findAll('dt').filter((item) => item.text() === 'TOTAL')).toHaveLength(1)
    expect(card.findAll('[data-asset-detail]')).toHaveLength(2)
    expect(card.text()).toContain('Track Circuit Gambir')
    expect(card.text()).toContain('Track Circuit Manggarai')
    expect(card.text()).toContain('Aktif')
    expect(card.text()).toContain('Nonaktif')
    expect(card.text()).toContain('DAOP-1 — Daerah Operasi 1 Jakarta')
    expect(card.get('a[href="/master-asset/41/edit"]').exists()).toBe(true)
    expect(card.get('a[href="/master-asset/42/edit"]').exists()).toBe(true)
    await card.get('[aria-label="Hapus aset Track Circuit Manggarai"]').trigger('click')
    expect(wrapper.emitted('delete').at(-1)).toEqual([secondAsset])
  })

  it('shows a graceful unit fallback in a pusat card when relation data is missing', () => {
    const wrapper = mount(AssetHierarchyCard, {
      props: {
        rows,
        assets: [{ ...assets[0], unit_kerja: null }],
        legacySummary: null,
        statusOptions,
        showUnit: true,
      },
      global: { stubs: { Link: true } },
    })

    expect(wrapper.get('[data-subsystem-card="101"]').text()).toContain('Unit tidak tersedia')
  })

  it('uses the full filtered legacy summary instead of the current-page quantity', () => {
    const legacy = {
      ...assets[0],
      id: 52,
      asset_subsystem_id: null,
      nama_aset: 'Aset Warisan',
      category: null,
      jumlah_unit: 3,
    }
    const secondLegacy = { ...legacy, id: 53, nama_aset: 'Aset Warisan Kedua' }
    const wrapper = mount(AssetHierarchyCard, {
      props: { rows, assets: [legacy, secondLegacy], legacySummary, statusOptions },
      global: { stubs: { Link: true } },
    })

    const card = wrapper.get('[data-subsystem-card="legacy"]')
    expect(wrapper.findAll('[data-subsystem-card="legacy"]')).toHaveLength(1)
    expect(card.text()).toContain('20')
    expect(card.findAll('[data-asset-detail]')).toHaveLength(2)
  })
})
