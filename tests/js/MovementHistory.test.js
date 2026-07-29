import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MovementHistory from '@/pages/master-data/inventory/Partials/MovementHistory.vue'

const part = {
  id: 21,
  code: 'SP-TC-001',
  detail_equipment: 'Relay 24 VDC',
  unit_of_measure: 'buah',
  category: { subsystem: { id: 101, name: 'Track Circuit' } },
}
const unit = { id: 7, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }
const source = {
  id: 41,
  quantity: 3,
  stock_before: 9,
  stock_after: 12,
  reference_number: 'BAST-17',
  notes: 'Penerimaan relay',
  reverses_movement_id: null,
  type: 'in',
  direction: 'in',
  movement_date: '2026-07-29',
  posted_at: '2026-07-29T10:15:00+07:00',
  spare_part: part,
  unit,
  actor: { id: 1, name: 'Operator Pusat' },
}
const correction = {
  ...source,
  id: 42,
  type: 'correction',
  direction: 'out',
  quantity: 1,
  stock_before: 12,
  stock_after: 11,
  reference_number: null,
  reverses_movement_id: 41,
  notes: 'Koreksi salah jumlah',
}

const mountHistory = (data = [source, correction], overrides = {}) => mount(MovementHistory, {
  props: {
    movements: {
      data,
      links: [{ label: '1', url: '/inventory?tab=history&movement_page=1', active: true }],
      from: data.length ? 1 : null,
      to: data.length,
      total: data.length,
    },
    showUnit: true,
    error: '',
    canReset: false,
    ...overrides,
  },
  global: {
    stubs: { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } },
  },
})

describe('MovementHistory', () => {
  it('renders operational and posted time, unit, ledger transition, actor, and reference', () => {
    const wrapper = mountHistory([source])

    expect(wrapper.text()).toContain('29 Jul 2026')
    expect(wrapper.text()).toContain('10.15')
    expect(wrapper.text()).toContain('DAOP-1')
    expect(wrapper.text()).toContain('Relay 24 VDC')
    expect(wrapper.text()).toContain('9 → 12')
    expect(wrapper.text()).toContain('Operator Pusat')
    expect(wrapper.text()).toContain('BAST-17')
  })

  it('keeps history immutable and starts a linked correction flow', async () => {
    const wrapper = mountHistory([source])

    expect(wrapper.find('[data-edit-movement]').exists()).toBe(false)
    expect(wrapper.find('[data-delete-movement]').exists()).toBe(false)
    await wrapper.get('[aria-label="Koreksi transaksi 41"]').trigger('click')
    expect(wrapper.emitted('correct')).toEqual([[source]])

    const corrected = mountHistory()
    expect(corrected.text()).toContain('Koreksi #41')
    expect(corrected.find('[aria-label="Koreksi transaksi 41"]').exists()).toBe(true)
    expect(corrected.find('[aria-label="Koreksi transaksi 42"]').exists()).toBe(false)
  })

  it('shows a directed empty state without inventing history rows', () => {
    const wrapper = mountHistory([])

    expect(wrapper.text()).toContain('Belum ada transaksi stok')
    expect(wrapper.find('tbody tr').exists()).toBe(false)
  })

  it('renders independent loading, error, and recovery states without stale ledger rows', async () => {
    const loading = mountHistory([source], { loading: true })
    expect(loading.find('[data-history-loading]').exists()).toBe(true)
    expect(loading.text()).not.toContain('Relay 24 VDC')

    const error = mountHistory([source], { error: 'Koneksi terputus.' })
    expect(error.get('[data-history-error]').text()).toContain('Riwayat transaksi tidak dapat dimuat')
    expect(error.text()).not.toContain('BAST-17')
    await error.get('[data-history-retry]').trigger('click')
    expect(error.emitted('retry')).toHaveLength(1)

    const empty = mountHistory([], { canReset: true })
    await empty.get('[data-history-reset]').trigger('click')
    expect(empty.emitted('reset')).toHaveLength(1)
  })
})
