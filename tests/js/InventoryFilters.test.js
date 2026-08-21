import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import InventoryFilters from '@/pages/master-data/inventory/Partials/InventoryFilters.vue'

const categories = [
  { id: 1, name: 'Sinyal', systems: [{ id: 11, name: 'Elektrik', subsystems: [{ id: 101, name: 'Track Circuit' }] }] },
  { id: 2, name: 'Telekomunikasi', systems: [{ id: 22, name: 'Radio', subsystems: [{ id: 202, name: 'Radio Lokomotif' }] }] },
]
const filters = {
  search: '', asset_group_id: '', asset_subsystem_id: '', stock_status: 'all', unit_kerja_id: '7',
  movement_type: '', date_from: '', date_to: '',
}

const mountFilters = (overrides = {}) => mount(InventoryFilters, {
  props: { filters, categories, activeTab: 'stock', ...overrides },
})

describe('InventoryFilters', () => {
  it('filters subsystem choices by group and resets a stale subsystem when the group changes', async () => {
    const wrapper = mountFilters()
    expect(wrapper.get('#inventory-subsystem').attributes('disabled')).toBeUndefined()
    expect(wrapper.get('#inventory-subsystem').text()).toContain('Radio / Radio Lokomotif')

    await wrapper.setProps({ filters: { ...filters, asset_group_id: '1' } })
    expect(wrapper.get('#inventory-subsystem').findAll('option').map((option) => option.text())).toContain('Elektrik / Track Circuit')
    expect(wrapper.get('#inventory-subsystem').text()).not.toContain('Radio Lokomotif')

    await wrapper.setProps({ filters: { ...filters, asset_group_id: '1', asset_subsystem_id: '101' } })
    await wrapper.get('#inventory-group').setValue('2')
    expect(wrapper.emitted('change')).toEqual([
      [{ key: 'asset_group_id', value: '2' }],
      [{ key: 'asset_subsystem_id', value: '' }],
    ])
  })

  it('preserves a subsystem-only deep link until a group is explicitly selected', () => {
    const wrapper = mountFilters({ filters: { ...filters, asset_subsystem_id: '202' } })
    const subsystem = wrapper.get('#inventory-subsystem')

    expect(subsystem.attributes('disabled')).toBeUndefined()
    expect(subsystem.element.value).toBe('202')
    expect(subsystem.text()).toContain('Radio / Radio Lokomotif')
    expect(subsystem.text()).toContain('Elektrik / Track Circuit')
  })

  it('gives every filter control its backend query name', () => {
    const wrapper = mountFilters({ showUnit: true, activeTab: 'history' })

    expect(wrapper.findAll('input, select').map((field) => field.attributes('name'))).toEqual([
      'search', 'unit_kerja_id', 'asset_group_id', 'asset_subsystem_id',
      'movement_type', 'date_from', 'date_to',
    ])
  })

  it('requires an explicit unit when the selector is available', () => {
    const wrapper = mountFilters({ showUnit: true, units: [{ id: 7, code: 'DAOP-1', name: 'Daerah Operasi 1' }] })

    expect(wrapper.get('#inventory-unit').text()).not.toContain('Semua unit kerja')
    expect(wrapper.get('#inventory-unit').element.value).toBe('7')
  })
})
