import { enableAutoUnmount, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import MovementDialog from '@/pages/master-data/inventory/Partials/MovementDialog.vue'

enableAutoUnmount(afterEach)

const inertia = vi.hoisted(() => ({ post: vi.fn(), forms: [] }))

vi.mock('@inertiajs/vue3', () => ({
  useForm: (values) => {
    const form = reactive({
      ...values,
      errors: {},
      processing: false,
      clearErrors: vi.fn(() => { form.errors = {} }),
      reset: vi.fn(),
      post: inertia.post,
    })
    inertia.forms.push(form)
    return form
  },
}))

const unit = { id: 7, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' }
const part = {
  id: 21,
  code: 'SP-TC-001',
  detail_equipment: 'Relay 24 VDC',
  equipment: 'Track circuit',
  unit_of_measure: 'buah',
  is_active: true,
  category: { subsystem: { id: 101, name: 'Track Circuit' } },
}
const offPagePart = { ...part, id: 22, code: 'SP-OFF-022', detail_equipment: 'Modul off page' }
const props = {
  open: true,
  spareParts: [part],
  stocks: [{ id: 31, unit_kerja_id: 7, spare_part_id: 21, quantity: 5, status: 'available', spare_part: part, unit }],
  units: [unit],
  canChooseUnit: true,
  initialPart: part,
  correction: null,
}

const mountDialog = (overrides = {}, options = {}) => mount(MovementDialog, {
  props: { ...props, ...overrides },
  global: { stubs: { Teleport: true, transition: false } },
  ...options,
})

describe('MovementDialog', () => {
  beforeEach(() => {
    inertia.post.mockReset()
    inertia.forms.length = 0
    vi.stubGlobal('crypto', { randomUUID: vi.fn()
      .mockReturnValueOnce('11111111-1111-4111-8111-111111111111')
      .mockReturnValueOnce('22222222-2222-4222-8222-222222222222') })
  })

  it('blocks an OUT movement that would make stock negative', async () => {
    const wrapper = mountDialog({}, { attachTo: document.body })
    await wrapper.get('#movement-unit').setValue('7')
    await wrapper.get('#movement-part').setValue('21')
    await wrapper.get('#movement-type').setValue('out')
    await wrapper.get('#movement-quantity').setValue('6')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('[data-stock-error]').text()).toContain('Stok tidak mencukupi')
    expect(wrapper.text()).toContain('Stok setelah transaksi: -1 buah')
    expect(inertia.post).not.toHaveBeenCalled()
  })

  it('requires a second explicit OUT confirmation naming the item, quantity, and projected stock', async () => {
    const wrapper = mountDialog()
    await wrapper.get('#movement-unit').setValue('7')
    await wrapper.get('#movement-part').setValue('21')
    await wrapper.get('#movement-type').setValue('out')
    await wrapper.get('#movement-quantity').setValue('3')
    await wrapper.get('form').trigger('submit')

    const confirmation = wrapper.get('[data-out-confirmation]')
    expect(confirmation.text()).toContain('Relay 24 VDC')
    expect(confirmation.text()).toContain('3 buah')
    expect(confirmation.text()).toContain('2 buah')
    expect(inertia.post).not.toHaveBeenCalled()

    await confirmation.get('[data-confirm-out]').trigger('click')
    expect(inertia.post).toHaveBeenCalledWith('/inventory/movements', expect.objectContaining({
      preserveScroll: true,
      onSuccess: expect.any(Function),
    }))
  })

  it('keeps the UUID stable across retry and creates a new one after close and reopen', async () => {
    const wrapper = mountDialog()
    const firstForm = inertia.forms[0]
    expect(firstForm.idempotency_key).toBe('11111111-1111-4111-8111-111111111111')

    await wrapper.get('#movement-unit').setValue('7')
    await wrapper.get('#movement-part').setValue('21')
    await wrapper.get('#movement-quantity').setValue('2')
    await wrapper.get('form').trigger('submit')
    expect(firstForm.idempotency_key).toBe('11111111-1111-4111-8111-111111111111')

    await wrapper.setProps({ open: false })
    await wrapper.setProps({ open: true })
    expect(firstForm.idempotency_key).toBe('22222222-2222-4222-8222-222222222222')
  })

  it('offers saldo awal only after selecting a zero-stock unit and part', async () => {
    const wrapper = mountDialog({ initialPart: null })
    expect(wrapper.find('#movement-type option[value="opening"]').exists()).toBe(false)

    await wrapper.get('#movement-unit').setValue('7')
    await wrapper.get('#movement-part').setValue('21')
    expect(wrapper.find('#movement-type option[value="opening"]').exists()).toBe(false)

    const emptyStock = [{ ...props.stocks[0], quantity: 0 }]
    const empty = mountDialog({ initialPart: null, stocks: emptyStock })
    await empty.get('#movement-unit').setValue('7')
    await empty.get('#movement-part').setValue('21')
    expect(empty.find('#movement-type option[value="opening"]').exists()).toBe(true)
  })

  it('does not infer a zero balance or offer OUT for a stock row outside the current page', async () => {
    const wrapper = mountDialog({ initialPart: null, spareParts: [part, offPagePart] })
    await wrapper.get('#movement-unit').setValue('7')
    await wrapper.get('#movement-part').setValue('22')

    expect(wrapper.find('#movement-type option[value="out"]').exists()).toBe(false)
    expect(wrapper.find('#movement-type option[value="opening"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Saldo belum terverifikasi pada halaman ini')
    expect(wrapper.get('[data-stock-before]').text()).toContain('—')
  })

  it('never submits a hidden unit id for regional row transactions', () => {
    mountDialog({ canChooseUnit: false, units: [], initialStock: props.stocks[0] })
    expect(inertia.forms[0].unit_kerja_id).toBe('')
  })

  it('closes from Escape for keyboard users', async () => {
    const wrapper = mountDialog()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('returns focus to the movement dialog when an OUT confirmation closes', async () => {
    const wrapper = mountDialog({}, { attachTo: document.body })
    await wrapper.get('#movement-unit').setValue('7')
    await wrapper.get('#movement-part').setValue('21')
    await wrapper.get('#movement-type').setValue('out')
    await wrapper.get('#movement-quantity').setValue('1')
    await wrapper.get('form').trigger('submit')
    wrapper.get('[data-confirm-out]').element.focus()
    expect(document.activeElement).toBe(wrapper.get('[data-confirm-out]').element)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="dialog"]').element.contains(document.activeElement)).toBe(true)
    wrapper.unmount()
  })

  it('submits correction as a linked immutable movement without editable source fields', async () => {
    const source = {
      id: 41,
      quantity: 3,
      stock_before: 9,
      stock_after: 12,
      direction: 'in',
      type: 'in',
      movement_date: '2026-07-29',
      spare_part: part,
      unit,
    }
    const wrapper = mountDialog({ correction: source, initialPart: null })

    expect(wrapper.text()).toContain('Koreksi transaksi #41')
    expect(wrapper.find('#movement-unit').exists()).toBe(false)
    expect(wrapper.find('#movement-part').exists()).toBe(false)
    expect(wrapper.find('#movement-type').exists()).toBe(false)
    await wrapper.get('#movement-direction').setValue('in')
    await wrapper.get('#movement-quantity').setValue('1')
    await wrapper.get('form').trigger('submit')

    expect(inertia.post).toHaveBeenCalledWith('/inventory/movements/41/corrections', expect.objectContaining({
      preserveScroll: true,
    }))
  })

  it('validates an OUT correction against server current stock when the stock row is on another page', async () => {
    const source = {
      id: 41,
      quantity: 3,
      stock_before: 9,
      stock_after: 12,
      current_stock: 5,
      direction: 'in',
      type: 'in',
      movement_date: '2026-07-29',
      spare_part: part,
      unit,
    }
    const wrapper = mountDialog({ correction: source, initialPart: null, stocks: [] })
    await wrapper.get('#movement-direction').setValue('out')
    await wrapper.get('#movement-quantity').setValue('6')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('[data-stock-error]').text()).toContain('Maksimal 5 buah')
    expect(inertia.post).not.toHaveBeenCalled()
  })
})
