import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import Inventory from '@/pages/master-data/inventory/Inventory.vue'

const inertia = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), delete: vi.fn(), handlers: {} }))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: {
    ...inertia,
    on: vi.fn((event, callback) => {
      inertia.handlers[event] = callback
      return vi.fn()
    }),
  },
  usePage: () => ({
    url: '/inventory',
    props: { auth: { user: { id: 1, name: 'Operator Pusat', role: 'pusat' } }, flash: {} },
  }),
}))

const part = {
  id: 21,
  asset_subsystem_id: 101,
  code: 'SP-TC-001',
  equipment: 'Track circuit',
  detail_equipment: 'Relay 24 VDC',
  max_yearly_failure: '4.00',
  average_yearly_failure: '1.50',
  max_lead_time_months: '3.00',
  average_lead_time_months: '1.25',
  safety_stock: 2,
  lead_time_demand: 4,
  reorder_point: 6,
  severity: 'Mayor',
  unit_of_measure: 'buah',
  is_active: true,
  category: {
    group: { id: 1, name: 'Peralatan Luar Sinyal Elektrik', is_active: true },
    system: { id: 11, name: 'Peraga Sinyal Elektrik', is_active: true },
    subsystem: { id: 101, name: 'Track Circuit', is_active: true },
  },
}

const unit = { id: 7, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }
const categories = [{
  id: 1,
  name: 'Peralatan Luar Sinyal Elektrik',
  systems: [{ id: 11, name: 'Peraga Sinyal Elektrik', subsystems: [{ id: 101, name: 'Track Circuit' }] }],
}]

const props = {
  stats: { total_parts: 1, total_quantity: 12, below_reorder: 0, movements_this_month: 3 },
  stocks: {
    data: [{ id: 31, unit_kerja_id: 7, spare_part_id: 21, quantity: 12, status: 'available', spare_part: part, unit }],
    links: [
      { label: '&laquo; Previous', url: null, active: false },
      { label: '1', url: '/inventory?page=1', active: true },
      { label: 'Next &raquo;', url: '/inventory?page=2', active: false },
    ],
    from: 1,
    to: 1,
    total: 1,
  },
  movements: {
    data: [{
      id: 41,
      unit_kerja_id: 7,
      spare_part_id: 21,
      actor_id: 1,
      quantity: 3,
      stock_before: 9,
      stock_after: 12,
      reference_number: 'BAST-17',
      notes: null,
      reverses_movement_id: null,
      type: 'in',
      direction: 'in',
      movement_date: '2026-07-29',
      posted_at: '2026-07-29T10:15:00+07:00',
      spare_part: part,
      unit,
      actor: { id: 1, name: 'Operator Pusat' },
    }],
    links: [],
    from: 1,
    to: 1,
    total: 1,
  },
  spareParts: [part],
  categories,
  units: [unit],
  filters: {
    search: '', asset_group_id: '', asset_subsystem_id: '', stock_status: 'all',
    unit_kerja_id: '', tab: 'stock', movement_type: '', date_from: '', date_to: '',
  },
  can: { choose_unit: true, manage_master: true, record_movement: true },
}

const mountPage = (overrides = {}) => mount(Inventory, {
  props: { ...props, ...overrides },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      MovementDialog: { props: ['open'], template: '<section v-if="open" role="dialog">Dialog transaksi</section>' },
      SparePartDialog: { props: ['open'], template: '<section v-if="open" role="dialog">Dialog suku cadang</section>' },
      Teleport: true,
      transition: false,
    },
  },
})

describe('Inventory', () => {
  beforeEach(() => {
    inertia.get.mockReset()
    inertia.post.mockReset()
    inertia.delete.mockReset()
    inertia.handlers = {}
    vi.useRealTimers()
  })

  it('renders only real inventory props in desktop and mobile stock structures', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Inventori Suku Cadang')
    expect(wrapper.text()).toContain('Relay 24 VDC')
    expect(wrapper.text()).toContain('DAOP-1')
    expect(wrapper.text()).toContain('12')
    expect(wrapper.text()).toContain('Total Jenis')
    expect(wrapper.text()).toContain('Total Unit Tersedia')
    expect(wrapper.text()).not.toMatch(/Generate Forecast|Prediksi Defisit|Dalam Pengiriman|Purchase Order/i)
    expect(wrapper.get('[data-inventory-desktop]').text()).toContain('Batas reorder')
    expect(wrapper.get('[data-inventory-mobile]').text()).toContain('Relay 24 VDC')
    expect(wrapper.get('[data-tab="stock"]').text()).toContain('Stok Saat Ini')
    expect(wrapper.get('[data-tab="history"]').text()).toContain('Riwayat Transaksi')
    expect(wrapper.get('[data-tab="master"]').text()).toContain('Master Suku Cadang')
    expect(wrapper.findAll('button').some((button) => button.text() === 'Catat IN/OUT')).toBe(true)
  })

  it('exposes master controls only to Pusat and normalizes a regional master URL', async () => {
    const pusat = mountPage()
    expect(pusat.get('[data-tab="master"]').text()).toContain('Master')
    expect(pusat.get('[data-add-part]').text()).toContain('Tambah suku cadang')

    const regional = mountPage({
      units: [],
      can: { choose_unit: false, manage_master: false, record_movement: true },
      filters: { ...props.filters, tab: 'master' },
    })
    expect(regional.find('[data-tab="master"]').exists()).toBe(false)
    expect(regional.find('[data-add-part]').exists()).toBe(false)
    expect(regional.find('[name="unit_kerja_id"]').exists()).toBe(false)
    expect(regional.get('[data-tab="stock"]').attributes('aria-current')).toBe('page')
    await regional.get('#inventory-status').setValue('critical')
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ tab: 'stock' }), expect.any(Object))
  })

  it('debounces URL-backed filters and resets only when a filter is active', async () => {
    vi.useFakeTimers()
    const wrapper = mountPage()
    expect(wrapper.find('[data-reset-filters]').exists()).toBe(false)

    await wrapper.get('#inventory-search').setValue('relay')
    expect(inertia.get).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(300)
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ search: 'relay' }), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })

    expect(wrapper.find('[data-reset-filters]').exists()).toBe(true)

    await wrapper.setProps({ filters: { ...props.filters, search: 'relay' } })
    expect(wrapper.find('[data-reset-filters]').exists()).toBe(true)
  })

  it('switches tabs through Inertia and keeps backend pagination links', async () => {
    const wrapper = mountPage()
    expect(wrapper.get('[aria-label="Paginasi stok"]').text()).toContain('Berikutnya')
    await wrapper.get('[data-tab="history"]').trigger('click')

    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ tab: 'history' }), expect.objectContaining({
      preserveState: true,
      replace: true,
    }))
  })

  it('shows stock errors without stale rows and provides a retry action', async () => {
    const wrapper = mountPage()

    inertia.handlers.invalid()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('[data-stock-error]').text()).toContain('Data stok tidak dapat dimuat')
    expect(wrapper.text()).not.toContain('Relay 24 VDC')
    await wrapper.get('[data-stock-retry]').trigger('click')
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ tab: 'stock' }), expect.any(Object))
  })

  it('gives stock empty states a filter or transaction recovery action', async () => {
    const emptyStocks = { data: [], links: [], from: null, to: null, total: 0 }
    const filtered = mountPage({
      stocks: emptyStocks,
      filters: { ...props.filters, search: 'tidak-ada' },
    })

    await filtered.get('[data-stock-reset]').trigger('click')
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ search: '' }), expect.any(Object))

    const empty = mountPage({ stocks: emptyStocks })
    await empty.get('[data-stock-record]').trigger('click')
    expect(empty.get('[role="dialog"]').text()).toContain('Dialog transaksi')
  })

  it('shows master loading and error recovery without stale rows', async () => {
    const wrapper = mountPage({ filters: { ...props.filters, tab: 'master' } })

    inertia.handlers.start()
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-master-loading]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Relay 24 VDC')

    inertia.handlers.invalid()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[data-master-error]').text()).toContain('Data master tidak dapat dimuat')
    expect(wrapper.get('[data-master-retry]').text()).toBe('Coba lagi')
    expect(wrapper.text()).not.toContain('SP-TC-001')
  })

  it('gives master empty states a filter or creation recovery action', async () => {
    const filtered = mountPage({
      spareParts: [],
      filters: { ...props.filters, tab: 'master', search: 'tidak-ada' },
    })
    await filtered.get('[data-master-reset]').trigger('click')
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ search: '' }), expect.any(Object))

    const empty = mountPage({ spareParts: [], filters: { ...props.filters, tab: 'master' } })
    await empty.get('[data-master-create]').trigger('click')
    expect(empty.find('[role="dialog"]').exists()).toBe(true)
  })

  it('keeps all operational inventory copy at a comfortable 14px minimum', () => {
    const files = [
      '../../resources/js/pages/master-data/inventory/Inventory.vue',
      '../../resources/js/pages/master-data/inventory/Partials/InventoryStats.vue',
      '../../resources/js/pages/master-data/inventory/Partials/InventoryFilters.vue',
      '../../resources/js/pages/master-data/inventory/Partials/InventoryTable.vue',
      '../../resources/js/pages/master-data/inventory/Partials/InventoryCard.vue',
      '../../resources/js/pages/master-data/inventory/Partials/MovementHistory.vue',
      '../../resources/js/pages/master-data/inventory/Partials/MovementDialog.vue',
      '../../resources/js/pages/master-data/inventory/Partials/SparePartDialog.vue',
    ]
    const source = files.map((file) => readFileSync(new URL(file, import.meta.url), 'utf8')).join('\n')

    expect(source).not.toMatch(/\btext-xs\b|text-\[(?:10|11|12|13)px\]/)
  })
})
