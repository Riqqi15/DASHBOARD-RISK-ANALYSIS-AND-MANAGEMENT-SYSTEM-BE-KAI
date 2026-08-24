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
    unit_kerja_id: '7', tab: 'stock', movement_type: '', date_from: '', date_to: '', master_page: '1',
  },
  can: { choose_unit: true, manage_master: true, record_movement: true },
  predictiveAssets: [],
  reconciliation: { rows: [], stats: { total: 0, matched: 0, difference: 0, missing_ledger: 0, missing_excel: 0, ambiguous: 0 } },
}

const mountPage = (overrides = {}, options = {}) => mount(Inventory, {
  props: { ...props, ...overrides },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      MovementDialog: { props: ['open'], emits: ['close'], template: '<section v-if="open" role="dialog">Dialog transaksi<button data-close-movement @click="$emit(\'close\')">Tutup</button></section>' },
      SparePartDialog: { props: ['open'], emits: ['close'], template: '<section v-if="open" role="dialog">Dialog suku cadang<button data-close-part @click="$emit(\'close\')">Tutup</button></section>' },
      Teleport: true,
      transition: false,
    },
  },
  ...options,
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
    expect(wrapper.get('[data-inventory-desktop]').text()).toContain('Safety stock')
    expect(wrapper.get('[data-inventory-desktop]').text()).not.toContain('Batas reorder')
    expect(wrapper.get('[data-inventory-desktop]').text()).not.toContain('6 buah')
    expect(wrapper.get('[data-inventory-mobile]').text()).toContain('Relay 24 VDC')
    expect(wrapper.get('[data-inventory-mobile]').text()).toContain('Safety stock')
    expect(wrapper.get('[data-inventory-mobile]').text()).not.toContain('Batas reorder')
    expect(wrapper.get('[data-tab="stock"]').text()).toContain('Stok Saat Ini')
    expect(wrapper.get('[data-tab="history"]').text()).toContain('Riwayat Transaksi')
    expect(wrapper.get('[data-tab="master"]').text()).toContain('Master Suku Cadang')
    expect(wrapper.findAll('button').some((button) => button.text() === 'Catat IN/OUT')).toBe(true)
  })

  it('shows only safety stock in the master spare parts table and cards', () => {
    const wrapper = mountPage({ filters: { ...props.filters, tab: 'master' } })

    expect(wrapper.get('[data-master-desktop]').text()).toContain('Safety stock')
    expect(wrapper.get('[data-master-desktop]').text()).not.toContain('Safety / reorder')
    expect(wrapper.get('[data-master-desktop]').text()).not.toContain('2 / 6')
    expect(wrapper.get('[data-master-mobile]').text()).toContain('Safety stock')
    expect(wrapper.get('[data-master-mobile]').text()).not.toContain('Safety / reorder')
    expect(wrapper.get('[data-master-mobile]').text()).not.toContain('2 / 6')
  })

  it('shows signed predictive stock as a deficit with Excel parity status', () => {
    const wrapper = mountPage({
      filters: { ...props.filters, tab: 'predictive' },
      predictiveAssets: [{
        asset_id: 91,
        name: 'Interlocking Elektrik',
        unit,
        category: { group: 'Peralatan Dalam Sinyal Elektrik', system: 'Interlocking Elektrik', subsystem: 'Interlocking Elektrik' },
        current_stock: -7,
        needed_stock: 2,
        proposal_quantity: 9,
        inventory_policy: 'More Pieces in Stock',
        final_safety_stock: 7,
        age_condition: 'Menengah',
        lifetime_status: 'Melewati Umur Teknis',
        parity_status: 'corrected',
        parity_differences: { current_stock: { excel: -7, backend: -7 } },
      }],
    })

    expect(wrapper.text()).toContain('Defisit stok 7')
    expect(wrapper.text()).toContain('Dikoreksi backend')
    expect(wrapper.text()).toContain('9')
  })

  it('shows Excel versus ledger reconciliation without an automatic overwrite action', () => {
    const wrapper = mountPage({
      filters: { ...props.filters, tab: 'reconciliation', reconciliation_status: 'all' },
      reconciliation: {
        stats: { total: 2, matched: 0, difference: 1, missing_ledger: 1, missing_excel: 0, ambiguous: 0 },
        rows: [{
          id: 'asset-91', asset_id: 91, asset_name: 'Catu Daya Sinyal', part_code: 'SP-CD-01', part_name: 'Catu Daya Sinyal',
          unit, category: { group: 'Peralatan Dalam', system: 'Sinyal Elektrik', subsystem: 'Catu Daya' },
          excel_stock: 10, ledger_stock: 8, difference: 2, status: 'difference', match_strategy: 'exact', candidate_count: 1,
        }, {
          id: 'asset-92', asset_id: 92, asset_name: 'Rectifier', part_code: null, part_name: null,
          unit, category: { group: 'Peralatan Dalam', system: 'Sinyal Elektrik', subsystem: 'Catu Daya' },
          excel_stock: 3, ledger_stock: null, difference: null, status: 'missing_ledger', match_strategy: null, candidate_count: 0,
        }],
      },
    })

    expect(wrapper.get('[data-tab="reconciliation"]').text()).toContain('Rekonsiliasi Excel')
    expect(wrapper.get('[data-reconciliation]').text()).toContain('Catu Daya Sinyal')
    expect(wrapper.get('[data-reconciliation]').text()).toContain('Selisih 2')
    expect(wrapper.get('[data-reconciliation]').text()).toContain('Belum ada stok ledger')
    expect(wrapper.get('[data-reconciliation]').text()).toContain('Koreksi harus dicatat sebagai transaksi')
    expect(wrapper.get('[data-reconciliation]').text()).not.toMatch(/samakan otomatis|timpa stok/i)
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

  it('switches unit immediately without carrying dependent filters', async () => {
    const wrapper = mountPage({
      units: [unit, { id: 8, code: 'DIVRE-III', name: 'Divisi Regional III' }],
      filters: {
        ...props.filters,
        search: 'relay',
        asset_group_id: '1',
        asset_subsystem_id: '101',
        stock_status: 'critical',
      },
    })

    await wrapper.get('#inventory-unit').setValue('8')

    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({
      unit_kerja_id: '8',
      search: '',
      asset_group_id: '',
      asset_subsystem_id: '',
      stock_status: 'all',
    }), expect.any(Object))
  })

  it('switches tabs through Inertia and keeps backend pagination links', async () => {
    const wrapper = mountPage()
    expect(wrapper.get('[aria-label="Paginasi stok"]').text()).toContain('Berikutnya')
    expect(wrapper.get('[aria-label="Paginasi stok"] a').classes()).toEqual(expect.arrayContaining(['hover:bg-indigo-50', 'focus-visible:ring-2']))
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
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ search: '', unit_kerja_id: '7' }), expect.any(Object))

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

  it('renders responsive master cards and paginates more than 50 filtered parts locally', async () => {
    const manyParts = Array.from({ length: 51 }, (_, index) => ({
      ...part,
      id: index + 1,
      code: `SP-${String(index + 1).padStart(3, '0')}`,
      detail_equipment: `Suku cadang ${index + 1}`,
    }))
    const wrapper = mountPage({ spareParts: manyParts, filters: { ...props.filters, tab: 'master', search: 'suku' } })

    expect(wrapper.get('[data-master-desktop]').text()).toContain('SP-001')
    expect(wrapper.get('[data-master-mobile]').text()).toContain('Suku cadang 1')
    expect(wrapper.get('[data-master-mobile]').text()).not.toContain('Suku cadang 51')
    expect(wrapper.get('[data-master-next]').classes()).toEqual(expect.arrayContaining(['hover:bg-slate-50', 'focus-visible:ring-2']))
    await wrapper.get('[data-master-next]').trigger('click')
    expect(wrapper.get('[data-master-mobile]').text()).toContain('Suku cadang 51')
    expect(wrapper.get('#inventory-search').element.value).toBe('suku')
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ master_page: '2' }), expect.objectContaining({
      replace: false,
    }))
  })

  it('restores the master client page from URL props and resets it for real filter changes', async () => {
    const manyParts = Array.from({ length: 51 }, (_, index) => ({
      ...part,
      id: index + 1,
      code: `SP-${String(index + 1).padStart(3, '0')}`,
      detail_equipment: `Suku cadang ${index + 1}`,
    }))
    const wrapper = mountPage({ spareParts: manyParts, filters: { ...props.filters, tab: 'master', master_page: '2' } })

    expect(wrapper.get('[data-master-mobile]').text()).toContain('Suku cadang 51')
    await wrapper.setProps({ filters: { ...props.filters, tab: 'master', master_page: '1' } })
    expect(wrapper.get('[data-master-mobile]').text()).toContain('Suku cadang 1')

    inertia.get.mockClear()
    await wrapper.get('#inventory-search').setValue('relay')
    await vi.waitFor(() => expect(inertia.get).toHaveBeenCalled())
    expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({ search: 'relay', master_page: '1' }), expect.any(Object))
  })

  it('restores focus to the exact movement and master dialog openers after unmount', async () => {
    const wrapper = mountPage({}, { attachTo: document.body })
    const movementOpener = wrapper.get('[data-record-movement]')
    movementOpener.element.focus()
    await movementOpener.trigger('click')
    await wrapper.get('[data-close-movement]').trigger('click')
    await wrapper.vm.$nextTick()
    expect(document.activeElement).toBe(movementOpener.element)

    const partOpener = wrapper.get('[data-add-part]')
    partOpener.element.focus()
    await partOpener.trigger('click')
    await wrapper.get('[data-close-part]').trigger('click')
    await wrapper.vm.$nextTick()
    expect(document.activeElement).toBe(partOpener.element)
    wrapper.unmount()
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
    expect(source).not.toMatch(/placeholder="[^"]*\.\.\./)

    const layoutPath = '../../resources/js/layouts/MainLayout.vue'
    const layout = readFileSync(new URL(layoutPath, import.meta.url), 'utf8')
    expect(layout).not.toContain('aria-label="Cari data"')
  })
})
