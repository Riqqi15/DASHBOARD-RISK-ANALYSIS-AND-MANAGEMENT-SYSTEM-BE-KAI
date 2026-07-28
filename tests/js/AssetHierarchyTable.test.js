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
}]

const assets = [{
  id: 41,
  asset_subsystem_id: 101,
  nama_aset: 'Track Circuit Gambir',
  lokasi: 'Stasiun Gambir',
  jumlah_unit: 81,
  status: 'aktif',
  category: {
    group: { id: 1, name: 'Peralatan Luar Sinyal Elektrik' },
    system: { id: 11, name: 'Peraga Sinyal Elektrik' },
    subsystem: { id: 101, name: 'Track Circuit' },
  },
}]

describe('AssetHierarchyTable', () => {
  it('renders exactly seven columns with scoped subsystem values and parent subtotals', () => {
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets },
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
    expect(wrapper.get('[data-group-id="1"]').text()).toContain('81')
    expect(wrapper.get('[data-system-id="11"]').text()).toContain('7')
  })

  it('collapses and expands a group with accessible state', async () => {
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets },
      global: { stubs: { Link: true } },
    })
    const button = wrapper.get('[data-group-id="1"] button')

    expect(button.attributes('aria-expanded')).toBe('true')
    expect(button.attributes('aria-controls')).toBe('asset-group-1-rows')
    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('[data-subsystem-id="101"]').exists()).toBe(false)
    await button.trigger('click')
    expect(wrapper.get('[data-subsystem-id="101"]').exists()).toBe(true)
  })

  it('keeps edit/delete actions and presents a clear legacy fallback', async () => {
    const legacy = {
      ...assets[0],
      id: 52,
      asset_subsystem_id: null,
      nama_aset: 'Aset Warisan',
      lokasi: null,
      category: null,
      jumlah_unit: 3,
    }
    const wrapper = mount(AssetHierarchyTable, {
      props: { rows, assets: [...assets, legacy] },
      global: { stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } },
    })

    expect(wrapper.text()).toContain('Belum diklasifikasikan')
    expect(wrapper.get('a[href="/master-asset/41/edit"]').exists()).toBe(true)
    await wrapper.get('[aria-label="Hapus aset Track Circuit Gambir"]').trigger('click')
    expect(wrapper.emitted('delete')).toEqual([[assets[0]]])
  })
})

describe('AssetHierarchyCard', () => {
  it('shows breadcrumb, scoped values, asset details, status, and actions', async () => {
    const wrapper = mount(AssetHierarchyCard, {
      props: { rows, assets, statusOptions: [{ value: 'aktif', label: 'Aktif' }] },
      global: { stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } } },
    })

    expect(wrapper.text()).toContain('Peralatan Luar Sinyal Elektrik / Peraga Sinyal Elektrik / Track Circuit')
    expect(wrapper.text()).toContain('Track Circuit Gambir')
    expect(wrapper.text()).toContain('Stasiun Gambir')
    expect(wrapper.text()).toContain('Aktif')
    expect(wrapper.text()).toContain('81')
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.get('a[href="/master-asset/41/edit"]').exists()).toBe(true)
    await wrapper.get('[aria-label="Hapus aset Track Circuit Gambir"]').trigger('click')
    expect(wrapper.emitted('delete')).toEqual([[assets[0]]])
  })
})
