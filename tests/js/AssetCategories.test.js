import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AssetCategories from '@/pages/Admin/AssetCategories/Index.vue'
import MainLayout from '@/layouts/MainLayout.vue'

const inertia = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  errors: {},
  responseErrors: {},
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
      inertia[method](url, payload(), options)
      if (Object.keys(inertia.responseErrors).length) {
        Object.assign(form.errors, inertia.responseErrors)
        options?.onError?.(inertia.responseErrors)
      }
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

const mountPage = (overrides = {}) => mount(AssetCategories, {
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

describe('AssetCategories', () => {
  beforeEach(() => {
    inertia.get.mockReset()
    inertia.post.mockReset()
    inertia.put.mockReset()
    inertia.patch.mockReset()
    inertia.delete.mockReset()
    inertia.errors = {}
    inertia.responseErrors = {}
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

    await dialog.trigger('keydown', { key: 'Escape' })
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
    await wrapper.get('[aria-label="Konfirmasi aktifkan kategori"]').trigger('click')

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

    expect(wrapper.get('[role="dialog"]').text()).toContain('Track Circuit')
    expect(wrapper.get('[role="dialog"]').text()).toContain('hanya dapat dihapus jika belum digunakan')
    await wrapper.get('[aria-label="Konfirmasi hapus kategori"]').trigger('click')

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
})
