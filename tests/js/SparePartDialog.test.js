import { enableAutoUnmount, mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SparePartDialog from '@/pages/master-data/inventory/Partials/SparePartDialog.vue'

enableAutoUnmount(afterEach)

const inertia = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn(), delete: vi.fn(), forms: [], handlers: {}, unregisterBefore: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  router: {
    on: vi.fn((event, callback) => {
      inertia.handlers[event] = callback
      return inertia.unregisterBefore
    }),
  },
  useForm: (values) => {
    const form = reactive({
      ...values,
      errors: {},
      processing: false,
      clearErrors: vi.fn(() => { form.errors = {} }),
      post: inertia.post,
      put: inertia.put,
      delete: inertia.delete,
    })
    inertia.forms.push(form)
    return form
  },
}))

const categories = [{
  id: 1,
  name: 'Peralatan Luar Sinyal Elektrik',
  systems: [{ id: 11, name: 'Peraga Sinyal Elektrik', subsystems: [{ id: 101, name: 'Track Circuit' }] }],
}]
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
}

const mountDialog = (overrides = {}, options = {}) => mount(SparePartDialog, {
  props: { open: true, part: null, categories, ...overrides },
  global: { stubs: { Teleport: true, transition: false } },
  ...options,
})

describe('SparePartDialog', () => {
  beforeEach(() => {
    inertia.post.mockReset()
    inertia.put.mockReset()
    inertia.delete.mockReset()
    inertia.forms.length = 0
    inertia.handlers = {}
    inertia.unregisterBefore.mockReset()
  })

  it('reuses the three-level hierarchy and groups the real master fields', () => {
    const wrapper = mountDialog()

    expect(wrapper.get('[name="asset_group_id"]').exists()).toBe(true)
    expect(wrapper.get('[name="asset_system_id"]').exists()).toBe(true)
    expect(wrapper.get('[name="asset_subsystem_id"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Identitas')
    expect(wrapper.text()).toContain('Kegagalan')
    expect(wrapper.text()).toContain('Lead time')
    expect(wrapper.text()).toContain('Reorder')
    expect(wrapper.get('label[for="part-unit"]').text()).toContain('Satuan')
    expect(wrapper.text()).toContain('contoh: buah, set, meter')
  })

  it('submits a new spare part to the named admin contract', async () => {
    const wrapper = mountDialog()
    await wrapper.get('[name="asset_group_id"]').setValue('1')
    await wrapper.get('[name="asset_system_id"]').setValue('11')
    await wrapper.get('[name="asset_subsystem_id"]').setValue('101')
    await wrapper.get('#part-code').setValue('SP-NEW-001')
    await wrapper.get('#part-name').setValue('Relay baru')
    await wrapper.get('#part-unit').setValue('buah')
    await wrapper.get('form').trigger('submit')

    expect(inertia.post).toHaveBeenCalledWith('/admin/spare-parts', expect.objectContaining({
      preserveScroll: true,
      onSuccess: expect.any(Function),
    }))
  })

  it('prefills, validates inline, and updates an existing spare part', async () => {
    const wrapper = mountDialog({ part }, { attachTo: document.body })
    const form = inertia.forms[0]
    form.errors = {
      code: 'Kode suku cadang sudah digunakan.',
      asset_subsystem_id: 'Pilih subsystem aset.',
      reorder_point: 'Reorder point harus lebih besar atau sama dengan safety stock.',
    }
    await wrapper.vm.$nextTick()

    expect(wrapper.get('#part-code').element.value).toBe('SP-TC-001')
    expect(wrapper.get('[name="asset_subsystem_id"]').element.value).toBe('101')
    expect(wrapper.get('[role="alert"]').text()).toContain('Pilih subsystem aset.')
    expect(wrapper.text()).toContain('Kode suku cadang sudah digunakan.')
    expect(wrapper.text()).toContain('Reorder point harus lebih besar')
    await wrapper.get('form').trigger('submit')

    expect(inertia.put).toHaveBeenCalledWith('/admin/spare-parts/21', expect.objectContaining({ preserveScroll: true }))
  })

  it('deactivates instead of deleting and requires explicit confirmation', async () => {
    const wrapper = mountDialog({ part })

    await wrapper.get('[data-deactivate-part]').trigger('click')
    expect(inertia.delete).not.toHaveBeenCalled()
    expect(wrapper.get('[data-deactivate-confirmation]').text()).toContain('Relay 24 VDC')
    await wrapper.get('[data-confirm-deactivate]').trigger('click')

    expect(inertia.delete).toHaveBeenCalledWith('/admin/spare-parts/21', expect.objectContaining({
      preserveScroll: true,
    }))
  })

  it('disables deactivation while processing and exposes progress text to prevent repeats', async () => {
    const wrapper = mountDialog({ part })
    await wrapper.get('[data-deactivate-part]').trigger('click')
    const deactivateForm = inertia.forms[1]
    deactivateForm.processing = true
    await wrapper.vm.$nextTick()

    expect(wrapper.get('[data-confirm-deactivate]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-confirm-deactivate]').text()).toBe('Menonaktifkan…')
    await wrapper.get('[data-confirm-deactivate]').trigger('click')
    expect(inertia.delete).not.toHaveBeenCalled()
  })

  it('closes from Escape for keyboard users', async () => {
    const wrapper = mountDialog()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('requires discard confirmation for dirty close, backdrop, and Escape attempts', async () => {
    const wrapper = mountDialog()
    await wrapper.get('#part-code').setValue('SP-DIRTY')

    await wrapper.get('[data-close-dialog]').trigger('click')
    expect(wrapper.emitted('close')).toBeUndefined()
    expect(wrapper.find('[data-discard-confirmation]').exists()).toBe(true)
    await wrapper.get('[data-discard-cancel]').trigger('click')

    await wrapper.get('[data-dialog-backdrop]').trigger('click')
    expect(wrapper.find('[data-discard-confirmation]').exists()).toBe(true)
    await wrapper.get('[data-discard-cancel]').trigger('click')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-discard-confirmation]').exists()).toBe(true)
    await wrapper.get('[data-confirm-discard]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('links all server errors to controls and focuses the first invalid field', async () => {
    const wrapper = mountDialog({ part }, { attachTo: document.body })
    const form = inertia.forms[0]
    form.errors = Object.fromEntries([
      'asset_subsystem_id', 'code', 'unit_of_measure', 'equipment', 'detail_equipment', 'severity',
      'max_yearly_failure', 'average_yearly_failure', 'max_lead_time_months', 'average_lead_time_months',
      'safety_stock', 'lead_time_demand', 'reorder_point',
    ].map((field) => [field, `${field} salah`]))
    await wrapper.get('form').trigger('submit')
    const options = inertia.put.mock.calls.at(-1)[1]
    options.onError()
    await wrapper.vm.$nextTick()

    for (const id of ['asset-subsystem-id', 'part-code', 'part-unit', 'part-equipment', 'part-name', 'part-severity', 'part-failure-max', 'part-failure-average', 'part-lead-max', 'part-lead-average', 'part-safety', 'part-demand', 'part-reorder']) {
      expect(wrapper.get(`#${id}`).attributes('aria-invalid')).toBe('true')
      expect(wrapper.get(`#${id}`).attributes('aria-describedby')).toBeTruthy()
    }
    expect(document.activeElement).toBe(wrapper.get('#asset-subsystem-id').element)
    wrapper.unmount()
  })

  it('adds modal scrolling and form metadata without ASCII ellipsis placeholders', () => {
    const wrapper = mountDialog()
    expect(wrapper.get('[role="dialog"]').classes()).toContain('overscroll-contain')
    expect(wrapper.get('form').attributes('name')).toBe('spare-part')
    expect(wrapper.get('form').attributes('autocomplete')).toBe('off')
    expect(wrapper.findAll('[placeholder]').every((field) => !field.attributes('placeholder').includes('...'))).toBe(true)
    expect(wrapper.findAll('input, select, textarea').map((field) => field.attributes('name')).every(Boolean)).toBe(true)
    expect(wrapper.findAll('input').map((field) => field.attributes('name'))).toEqual([
      'code', 'unit_of_measure', 'equipment', 'detail_equipment', 'severity',
      'max_yearly_failure', 'average_yearly_failure', 'max_lead_time_months',
      'average_lead_time_months', 'safety_stock', 'lead_time_demand', 'reorder_point',
    ])
  })

  it('protects dirty drafts from unload and GET navigation and cleans up the Inertia guard', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = mountDialog()
    await wrapper.get('#part-code').setValue('SP-DIRTY')

    const unload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(unload)
    expect(unload.defaultPrevented).toBe(true)

    const navigation = { detail: { visit: { method: 'get' } }, preventDefault: vi.fn() }
    inertia.handlers.before(navigation)
    expect(confirm).toHaveBeenCalledOnce()
    expect(navigation.preventDefault).toHaveBeenCalledOnce()

    confirm.mockClear()
    const mutation = { detail: { visit: { method: 'delete' } }, preventDefault: vi.fn() }
    inertia.handlers.before(mutation)
    expect(confirm).not.toHaveBeenCalled()
    expect(mutation.preventDefault).not.toHaveBeenCalled()

    wrapper.unmount()
    expect(inertia.unregisterBefore).toHaveBeenCalledOnce()
    const afterCleanup = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(afterCleanup)
    expect(afterCleanup.defaultPrevented).toBe(false)
    confirm.mockRestore()
  })

  it('returns focus to the master dialog when deactivate confirmation closes', async () => {
    const wrapper = mountDialog({ part }, { attachTo: document.body })
    await wrapper.get('[data-deactivate-part]').trigger('click')
    wrapper.get('[data-confirm-deactivate]').element.focus()
    expect(document.activeElement).toBe(wrapper.get('[data-confirm-deactivate]').element)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="dialog"]').element.contains(document.activeElement)).toBe(true)
    wrapper.unmount()
  })
})
