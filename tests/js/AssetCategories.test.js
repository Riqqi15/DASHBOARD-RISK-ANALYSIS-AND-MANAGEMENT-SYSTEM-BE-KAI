import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import AssetCategories from '@/pages/Admin/AssetCategories/Index.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import CategoryPanel from '@/pages/Admin/AssetCategories/Partials/CategoryPanel.vue'
import CategoryDialog from '@/pages/Admin/AssetCategories/Partials/CategoryDialog.vue'
import DeactivateCategoryDialog from '@/pages/Admin/AssetCategories/Partials/DeactivateCategoryDialog.vue'
import DeleteCategoryDialog from '@/pages/Admin/AssetCategories/Partials/DeleteCategoryDialog.vue'

const inertia = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  errors: {},
  responseErrors: {},
  deferResponse: false,
  pendingResponse: null,
  page: {
    url: '/admin/asset-categories',
    props: { auth: { user: { id: 1, name: 'Administrator', username: 'admin', role: 'pusat' } }, flash: {} },
  },
}))

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await vi.importActual('vue')
  return {
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { get: inertia.get },
    usePage: () => inertia.page,
    useForm: (values) => {
    const keys = Object.keys(values)
    const form = reactive({
      ...values,
      errors: inertia.errors,
      processing: false,
      clearErrors: vi.fn(),
      reset: vi.fn(),
    })
    const payload = () => Object.fromEntries(keys.map((key) => [key, form[key]]))
    const submit = (method, url, options) => {
      form.processing = true
      options?.onStart?.()
      inertia[method](url, payload(), options)
      const complete = () => {
        if (Object.keys(inertia.responseErrors).length) {
          Object.assign(form.errors, inertia.responseErrors)
          options?.onError?.(inertia.responseErrors)
        } else {
          options?.onSuccess?.()
        }
        form.processing = false
        options?.onFinish?.()
        inertia.pendingResponse = null
      }
      if (inertia.deferResponse) inertia.pendingResponse = complete
      else complete()
    }
    form.post = (url, options) => submit('post', url, options)
    form.put = (url, options) => submit('put', url, options)
    form.patch = (url, options) => submit('patch', url, options)
    form.delete = (url, options) => submit('delete', url, options)
    return form
    },
  }
})

const groups = [
  {
    id: 1,
    name: 'Peralatan Luar Sinyal Elektrik',
    sort_order: 10,
    is_active: true,
    systems_count: 2,
    aliases_count: 1,
    systems: [
      {
        id: 11,
        asset_group_id: 1,
        name: 'Peraga Sinyal Elektrik',
        sort_order: 10,
        is_active: true,
        subsystems_count: 1,
        aliases_count: 0,
        subsystems: [
          { id: 111, asset_system_id: 11, name: 'Track Circuit', sort_order: 10, is_active: true, assets_count: 8, aliases_count: 2 },
        ],
      },
      {
        id: 12,
        asset_group_id: 1,
        name: 'Penggerak Wesel',
        sort_order: 20,
        is_active: true,
        subsystems_count: 1,
        aliases_count: 0,
        subsystems: [
          { id: 121, asset_system_id: 12, name: 'Motor Point', sort_order: 10, is_active: false, assets_count: 3, aliases_count: 0 },
        ],
      },
    ],
  },
  {
    id: 2,
    name: 'Telekomunikasi',
    sort_order: 20,
    is_active: true,
    systems_count: 1,
    aliases_count: 0,
    systems: [
      {
        id: 21,
        asset_group_id: 2,
        name: 'Radio Kereta',
        sort_order: 10,
        is_active: true,
        subsystems_count: 1,
        aliases_count: 0,
        subsystems: [
          { id: 211, asset_system_id: 21, name: 'Radio Lokomotif', sort_order: 10, is_active: true, assets_count: 2, aliases_count: 0 },
        ],
      },
    ],
  },
]

const mountedPages = []
const mountPage = (overrides = {}, options = {}) => {
  const wrapper = mount(AssetCategories, {
    attachTo: options.attachTo,
    props: {
      groups,
      selectedGroupId: 1,
      selectedSystemId: 11,
      capabilities: { manage: true },
      ...overrides,
    },
    global: {
      stubs: {
        MainLayout: { template: '<main><slot /></main>' },
        Teleport: true,
      },
    },
  })
  mountedPages.push(wrapper)
  return wrapper
}

describe('AssetCategories', () => {
  beforeEach(() => {
    inertia.get.mockReset()
    inertia.post.mockReset()
    inertia.put.mockReset()
    inertia.patch.mockReset()
    inertia.delete.mockReset()
    inertia.errors = {}
    inertia.responseErrors = {}
    inertia.deferResponse = false
    inertia.pendingResponse = null
  })

  afterEach(() => {
    for (const wrapper of mountedPages.splice(0)) wrapper.unmount()
  })

  it('renders backend data and drills down from group to system to subsystem', async () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Peralatan Luar Sinyal Elektrik')
    expect(wrapper.text()).toContain('Peraga Sinyal Elektrik')
    expect(wrapper.text()).toContain('Track Circuit')

    await wrapper.get('[aria-label="Pilih kategori Telekomunikasi"]').trigger('click')
    expect(wrapper.text()).toContain('Radio Kereta')

    await wrapper.get('[aria-label="Pilih system Radio Kereta"]').trigger('click')
    expect(wrapper.text()).toContain('Radio Lokomotif')
    expect(inertia.get).toHaveBeenLastCalledWith('/admin/asset-categories', { group: 2, system: 21 }, expect.objectContaining({ preserveState: true }))
  })

  it('filters each panel locally without removing inactive categories', async () => {
    const wrapper = mountPage({ selectedSystemId: 12 })

    expect(wrapper.text()).toContain('Motor Point')
    expect(wrapper.text()).toContain('Nonaktif')
    await wrapper.get('[aria-label="Cari kategori"]').setValue('tele')

    expect(wrapper.text()).toContain('Telekomunikasi')
    expect(wrapper.text()).not.toContain('Peralatan Luar Sinyal Elektrik')
  })

  it('opens an accessible subsystem create dialog and closes it with Escape', async () => {
    const wrapper = mountPage()

    await wrapper.get('button[aria-label="Tambah subsystem"]').trigger('click')
    const dialog = wrapper.get('[role="dialog"]')

    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.text()).toContain('Tambah subsystem')
    expect(dialog.get('label[for="category-name"]').text()).toContain('Nama subsystem')
    expect(dialog.get('#category-name').attributes('autofocus')).toBeDefined()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it.each([
    ['kategori', '/admin/asset-groups', null, null],
    ['system', '/admin/asset-systems', 1, null],
    ['subsystem', '/admin/asset-subsystems', null, 11],
  ])('creates a %s through its actual endpoint and parent payload', async (level, endpoint, groupId, systemId) => {
    const wrapper = mountPage()
    await wrapper.get(`button[aria-label="Tambah ${level}"]`).trigger('click')
    await wrapper.get('#category-name').setValue(`Kategori ${level}`)
    await wrapper.get('#category-sort-order').setValue(35)
    await wrapper.get('[role="dialog"] form').trigger('submit')

    const expected = { name: `Kategori ${level}`, sort_order: 35 }
    if (groupId) expected.asset_group_id = groupId
    if (systemId) expected.asset_system_id = systemId
    expect(inertia.post).toHaveBeenCalledWith(endpoint, expected, expect.objectContaining({ preserveScroll: true }))
  })

  it('edits a category name and sort order through the level endpoint', async () => {
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Ubah nama Peraga Sinyal Elektrik"]').trigger('click')

    expect(wrapper.get('#category-name').element.value).toBe('Peraga Sinyal Elektrik')
    expect(wrapper.get('#category-sort-order').element.value).toBe('10')
    await wrapper.get('#category-name').setValue('Peraga Sinyal Utama')
    await wrapper.get('#category-sort-order').setValue(15)
    await wrapper.get('[role="dialog"] form').trigger('submit')

    expect(inertia.put).toHaveBeenCalledWith('/admin/asset-systems/11', {
      name: 'Peraga Sinyal Utama',
      sort_order: 15,
    }, expect.objectContaining({ preserveScroll: true }))
  })

  it('sends the requested active status and keeps inactive categories actionable', async () => {
    const wrapper = mountPage({ selectedSystemId: 12 })
    expect(wrapper.text()).toContain('Motor Point')
    expect(wrapper.get('[aria-label="Aktifkan Motor Point"]').exists()).toBe(true)

    await wrapper.get('[aria-label="Aktifkan Motor Point"]').trigger('click')
    expect(wrapper.get('[role="dialog"]').text()).toContain('tetap terlihat')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Aktifkan subsystem?')
    await wrapper.get('[aria-label="Konfirmasi aktifkan subsystem"]').trigger('click')

    expect(inertia.patch).toHaveBeenCalledWith('/admin/asset-subsystems/121/status', {
      is_active: true,
    }, expect.objectContaining({ preserveScroll: true }))
  })

  it('confirms safe deletion and shows a concrete dependency error from the server', async () => {
    inertia.responseErrors = {
      category: 'Kategori tidak dapat dihapus karena masih digunakan oleh 8 aset. Silakan nonaktifkan kategori ini.',
    }
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Hapus Track Circuit"]').trigger('click')

    expect(wrapper.get('[role="dialog"]').text()).toContain('Hapus subsystem?')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Track Circuit')
    expect(wrapper.get('[role="dialog"]').text()).toContain('hanya dapat dihapus jika belum digunakan')
    await wrapper.get('[aria-label="Konfirmasi hapus subsystem"]').trigger('click')

    expect(inertia.delete).toHaveBeenCalledWith('/admin/asset-subsystems/111', {}, expect.objectContaining({ preserveScroll: true }))
    expect(wrapper.get('[role="alert"]').text()).toContain('8 aset')
    expect(wrapper.get('[role="alert"]').text()).toContain('nonaktifkan kategori')
  })

  it('disables child creation when its parent is missing or inactive', () => {
    const empty = mountPage({ groups: [], selectedGroupId: null, selectedSystemId: null })
    expect(empty.get('[aria-label="Tambah system"]').attributes('disabled')).toBeDefined()
    expect(empty.get('[aria-label="Tambah subsystem"]').attributes('disabled')).toBeDefined()
    expect(empty.text()).toContain('Pilih kategori terlebih dahulu')

    const inactiveGroups = structuredClone(groups)
    inactiveGroups[0].is_active = false
    inactiveGroups[0].systems[0].is_active = false
    const inactive = mountPage({ groups: inactiveGroups })
    expect(inactive.get('[aria-label="Tambah system"]').attributes('disabled')).toBeDefined()
    expect(inactive.get('[aria-label="Tambah subsystem"]').attributes('disabled')).toBeDefined()
  })

  it('uses progressive disclosure with a clear breadcrumb on mobile', async () => {
    const wrapper = mountPage()
    expect(wrapper.get('[data-level="group"]').attributes('data-mobile-active')).toBe('true')

    await wrapper.get('[aria-label="Pilih kategori Telekomunikasi"]').trigger('click')
    expect(wrapper.get('[data-level="system"]').attributes('data-mobile-active')).toBe('true')
    expect(wrapper.get('[aria-label="Jalur kategori"]').text()).toContain('Telekomunikasi')

    await wrapper.get('[aria-label="Pilih system Radio Kereta"]').trigger('click')
    expect(wrapper.get('[data-level="subsystem"]').attributes('data-mobile-active')).toBe('true')
    await wrapper.get('[aria-label="Kembali ke system"]').trigger('click')
    expect(wrapper.get('[data-level="system"]').attributes('data-mobile-active')).toBe('true')
  })

  it('shows the category menu only to Pusat accounts and places it before Unit Kerja', () => {
    inertia.page = {
      url: '/admin/asset-categories',
      props: { auth: { user: { id: 1, name: 'Administrator', username: 'admin', role: 'pusat' } }, flash: {} },
    }
    const pusat = mount(MainLayout, { global: { stubs: { Teleport: true } } })
    const adminLinks = pusat.findAll('a').filter((link) => link.attributes('href')?.startsWith('/admin/'))
    expect(adminLinks.map((link) => link.text())).toEqual(expect.arrayContaining(['Kategori Aset', 'Unit Kerja']))
    expect(adminLinks.map((link) => link.text())).not.toEqual(expect.arrayContaining(['Akun Wilayah', 'Audit Log']))
    expect(adminLinks.findIndex((link) => link.text().includes('Kategori Aset'))).toBeLessThan(adminLinks.findIndex((link) => link.text().includes('Unit Kerja')))

    inertia.page = {
      url: '/dashboard',
      props: { auth: { user: { id: 2, name: 'Operator', username: 'wilayah', role: 'wilayah', unit_kerja: { code: 'DAOP-1' } } }, flash: {} },
    }
    const regional = mount(MainLayout, { global: { stubs: { Teleport: true } } })
    expect(regional.text()).not.toContain('Kategori Aset')
  })

  it('directs the user from an empty category hierarchy', () => {
    const wrapper = mountPage({ groups: [], selectedGroupId: null, selectedSystemId: null })

    expect(wrapper.text()).toContain('Belum ada kategori aset')
    expect(wrapper.text()).toContain('Tambahkan kategori pertama')
  })

  it('shows a parent validation error with a concrete recovery direction', async () => {
    inertia.responseErrors = { asset_group_id: 'Kategori induk sudah tidak tersedia.' }
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Tambah system"]').trigger('click')
    await wrapper.get('#category-name').setValue('System baru')
    await wrapper.get('[role="dialog"] form').trigger('submit')

    expect(wrapper.get('[role="alert"]').text()).toContain('Kategori induk sudah tidak tersedia')
    expect(wrapper.get('[role="alert"]').text()).toContain('Pilih kategori aktif')
  })

  it('keeps drill-down and search read-only when category management is unavailable', async () => {
    const wrapper = mountPage({ capabilities: { manage: false } })

    expect(wrapper.find('[aria-label="Tambah kategori"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label^="Ubah nama"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label^="Aktifkan"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label^="Nonaktifkan"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label^="Hapus"]').exists()).toBe(false)

    await wrapper.get('[aria-label="Pilih kategori Telekomunikasi"]').trigger('click')
    expect(wrapper.text()).toContain('Radio Kereta')
    expect(wrapper.get('[aria-label="Cari system"]').exists()).toBe(true)

    wrapper.findAllComponents(CategoryPanel)[0].vm.$emit('add')
    wrapper.findAllComponents(CategoryPanel)[0].vm.$emit('edit', groups[0])
    wrapper.findAllComponents(CategoryPanel)[0].vm.$emit('toggle', groups[0])
    wrapper.findAllComponents(CategoryPanel)[0].vm.$emit('delete', groups[0])
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(inertia.post).not.toHaveBeenCalled()
    expect(inertia.put).not.toHaveBeenCalled()
    expect(inertia.patch).not.toHaveBeenCalled()
    expect(inertia.delete).not.toHaveBeenCalled()
  })

  it('removes an open mutation dialog if management capability is revoked', async () => {
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Tambah kategori"]').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    await wrapper.setProps({ capabilities: { manage: false } })

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(inertia.post).not.toHaveBeenCalled()
    await wrapper.setProps({ capabilities: { manage: true } })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('explains how to restore child creation when the selected parent is inactive', () => {
    const inactiveGroupData = structuredClone(groups)
    inactiveGroupData[0].is_active = false
    const inactiveGroup = mountPage({ groups: inactiveGroupData })

    expect(inactiveGroup.get('[aria-label="Tambah system"]').attributes('disabled')).toBeDefined()
    expect(inactiveGroup.text()).toContain('Kategori ini nonaktif')
    expect(inactiveGroup.text()).toContain('Aktifkan kategori untuk menambah system')

    const inactiveSystemData = structuredClone(groups)
    inactiveSystemData[0].systems[0].is_active = false
    const inactiveSystem = mountPage({ groups: inactiveSystemData })

    expect(inactiveSystem.get('[aria-label="Tambah subsystem"]').attributes('disabled')).toBeDefined()
    expect(inactiveSystem.text()).toContain('System ini nonaktif')
    expect(inactiveSystem.text()).toContain('Aktifkan system untuk menambah subsystem')
  })

  it('focuses the status dialog and closes it when the focused dialog receives Escape', async () => {
    const wrapper = mount(DeactivateCategoryDialog, {
      attachTo: document.body,
      props: { category: groups[0], levelLabel: 'kategori', activate: false, processing: false },
      global: { stubs: { Teleport: true } },
    })
    await nextTick()

    expect(document.activeElement).toBe(wrapper.get('[data-dialog-initial-focus]').element)
    document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('focuses the delete dialog and closes it when the focused dialog receives Escape', async () => {
    const wrapper = mount(DeleteCategoryDialog, {
      attachTo: document.body,
      props: { category: groups[0], levelLabel: 'kategori', form: { processing: false, errors: {} } },
      global: { stubs: { Teleport: true } },
    })
    await nextTick()

    expect(document.activeElement).toBe(wrapper.get('[data-dialog-initial-focus]').element)
    document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('keeps status and delete dialogs locked while a request is processing', async () => {
    const status = mount(DeactivateCategoryDialog, {
      attachTo: document.body,
      props: { category: groups[0], levelLabel: 'kategori', activate: false, processing: true },
      global: { stubs: { Teleport: true } },
    })
    await nextTick()
    expect(document.activeElement).toBe(status.get('[role="dialog"]').element)
    expect(status.get('[role="dialog"]').attributes('aria-busy')).toBe('true')
    expect(status.get('[aria-label="Konfirmasi nonaktifkan kategori"]').text()).toBe('Memproses…')
    expect(status.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true)
    document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(status.emitted('close')).toBeUndefined()
    status.unmount()

    const deletion = mount(DeleteCategoryDialog, {
      attachTo: document.body,
      props: { category: groups[0], levelLabel: 'kategori', form: { processing: true, errors: {} } },
      global: { stubs: { Teleport: true } },
    })
    await nextTick()
    expect(deletion.get('[role="dialog"]').attributes('aria-busy')).toBe('true')
    expect(deletion.get('[aria-label="Konfirmasi hapus kategori"]').text()).toBe('Menghapus…')
    expect(deletion.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true)
    document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(deletion.emitted('close')).toBeUndefined()
    deletion.unmount()
  })

  it('closes a category dialog on success even while Inertia still marks the form processing', async () => {
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Tambah kategori"]').trigger('click')
    await wrapper.get('#category-name').setValue('Kategori baru')
    await wrapper.get('[role="dialog"] form').trigger('submit')

    expect(inertia.post).toHaveBeenCalled()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('keeps the form open for validation errors and blocks user close until processing finishes', async () => {
    inertia.deferResponse = true
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Tambah kategori"]').trigger('click')
    await wrapper.get('#category-name').setValue('Kategori baru')
    await wrapper.get('[role="dialog"] form').trigger('submit')

    expect(wrapper.get('[role="dialog"]').attributes('aria-busy')).toBe('true')
    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
    await wrapper.get('[role="dialog"] button[aria-label="Tutup dialog"]').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    inertia.responseErrors = { normalized_name: 'Nama kategori sudah digunakan.' }
    inertia.pendingResponse()
    await nextTick()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(wrapper.get('[role="alert"]').text()).toContain('Nama kategori sudah digunakan')
  })

  it('traps forward and reverse Tab navigation inside the shared dialog', async () => {
    const outside = document.createElement('button')
    outside.textContent = 'Pemicu dialog'
    document.body.append(outside)
    outside.focus()
    const wrapper = mount(CategoryDialog, {
      attachTo: document.body,
      props: {
        title: 'Tambah kategori',
        levelLabel: 'kategori',
        description: 'Tambah kategori global.',
        form: { name: '', sort_order: 0, errors: {}, processing: false },
      },
      global: { stubs: { Teleport: true } },
    })
    await nextTick()

    const first = wrapper.get('[aria-label="Tutup dialog"]').element
    const last = wrapper.get('button[type="submit"]').element
    last.focus()
    last.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
    const forwardWrapped = document.activeElement === first
    first.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }))
    const reverseWrapped = document.activeElement === last

    outside.focus()
    const outsideRedirected = document.activeElement === wrapper.get('[data-dialog-initial-focus]').element

    wrapper.unmount()
    outside.remove()
    expect(forwardWrapped).toBe(true)
    expect(reverseWrapped).toBe(true)
    expect(outsideRedirected).toBe(true)
  })

  it('uses document Escape, isolates the background, then restores focus and cleanup', async () => {
    const trigger = document.createElement('button')
    trigger.textContent = 'Buka kategori'
    document.body.append(trigger)
    trigger.focus()
    const closeSpy = vi.fn()
    const wrapper = mount(CategoryDialog, {
      attachTo: document.body,
      props: {
        title: 'Tambah kategori',
        levelLabel: 'kategori',
        description: 'Tambah kategori global.',
        form: { name: '', sort_order: 0, errors: {}, processing: false },
      },
      attrs: { onClose: closeSpy },
      global: { stubs: { Teleport: true } },
    })
    await nextTick()

    const isolated = trigger.hasAttribute('inert') && trigger.getAttribute('aria-hidden') === 'true'
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    const closeCount = closeSpy.mock.calls.length

    wrapper.unmount()
    const restoredFocus = document.activeElement === trigger
    const backgroundRestored = !trigger.hasAttribute('inert') && !trigger.hasAttribute('aria-hidden')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    const cleanedListener = closeSpy.mock.calls.length === closeCount
    trigger.remove()
    expect(isolated).toBe(true)
    expect(closeCount).toBe(1)
    expect(restoredFocus).toBe(true)
    expect(backgroundRestored).toBe(true)
    expect(cleanedListener).toBe(true)
  })

  it('restores focus to the stable page fallback when the dialog trigger was removed', async () => {
    const wrapper = mountPage({}, { attachTo: document.body })
    const fallback = wrapper.get('h2')
    const trigger = wrapper.get('[aria-label="Hapus Track Circuit"]')
    trigger.element.focus()
    await trigger.trigger('click')
    await nextTick()
    expect(wrapper.get('[data-dialog-initial-focus]').element).toBe(document.activeElement)

    await wrapper.setProps({ groups: [] })
    expect(trigger.element.isConnected).toBe(false)
    await wrapper.get('[role="dialog"] button[aria-label="Tutup dialog"]').trigger('click')
    await nextTick()

    expect(fallback.attributes('data-dialog-focus-fallback')).toBe('')
    expect(document.activeElement).toBe(fallback.element)
  })

  it('renders subsystem leaves as content instead of a useless selector control', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Track Circuit')
    expect(wrapper.find('[aria-label="Pilih subsystem Track Circuit"]').exists()).toBe(false)
    expect(wrapper.get('[aria-label="Ubah nama Track Circuit"]').exists()).toBe(true)
  })

  it('clears child searches whenever their parent scope changes', async () => {
    const groupChange = mountPage()
    await groupChange.get('[aria-label="Cari system"]').setValue('peraga')
    await groupChange.get('[aria-label="Cari subsystem"]').setValue('track')
    await groupChange.get('[aria-label="Pilih kategori Telekomunikasi"]').trigger('click')

    expect(groupChange.get('[aria-label="Cari system"]').element.value).toBe('')
    expect(groupChange.get('[aria-label="Cari subsystem"]').element.value).toBe('')
    expect(groupChange.text()).toContain('Radio Kereta')
    expect(groupChange.text()).toContain('Radio Lokomotif')

    const systemChange = mountPage()
    await systemChange.get('[aria-label="Cari subsystem"]').setValue('track')
    await systemChange.get('[aria-label="Pilih system Penggerak Wesel"]').trigger('click')

    expect(systemChange.get('[aria-label="Cari subsystem"]').element.value).toBe('')
    expect(systemChange.text()).toContain('Motor Point')
  })
})
