import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import AssetCategories from '@/pages/Admin/AssetCategories/Index.vue'
import MainLayout from '@/layouts/MainLayout.vue'

const inertia = vi.hoisted(() => ({
  get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(),
  page: {
    url: '/admin/asset-categories',
    props: { auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat', unit_kerja: null } }, flash: {} },
  },
}))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { get: inertia.get, delete: inertia.delete },
  usePage: () => inertia.page,
  useForm: (values) => {
    const keys = Object.keys(values)
    const form = reactive({ ...values, errors: {}, processing: false })
    const submit = (method, url, options) => {
      const payload = Object.fromEntries(keys.map((key) => [key, form[key]]))
      form.processing = true
      inertia[method](url, payload, options)
      options?.onSuccess?.()
      form.processing = false
    }
    form.post = (url, options) => submit('post', url, options)
    form.put = (url, options) => submit('put', url, options)
    form.delete = (url, options) => submit('delete', url, options)
    return form
  },
}))

const levels = [
  { id: 1, name: 'Aset Prasarana Sintel', position: 1, is_active: true },
  { id: 2, name: 'System', position: 2, is_active: true },
  { id: 3, name: 'Subsystem', position: 3, is_active: true },
  { id: 4, name: 'Jenis Perangkat', position: 4, is_active: true },
  { id: 5, name: 'Model', position: 5, is_active: true },
]

const node = (id, level, parent, name, color = null) => ({
  id, asset_category_level_id: level, level_position: level, parent_id: parent, name,
  sort_order: 0, dashboard_color: color, is_active: true,
  subtree_assets_count: 2, subtree_units_count: 7,
})
const nodes = [
  node(1, 1, null, 'Peralatan Luar Sinyal Elektrik', '#F2B500'),
  node(2, 1, null, 'Catu Daya Sintel', '#FF0000'),
  node(11, 2, 1, 'Peraga Sinyal Elektrik'),
  node(111, 3, 11, 'Track Circuit'),
  node(1111, 4, 111, 'Indoor'),
  node(11111, 5, 1111, 'TC-900'),
]
const assets = {
  data: [{
    id: 7, nama_aset: 'Track Circuit STA', category_node: { id: 11111, name: 'TC-900' },
    jumlah_unit: 4, tanggal_pemasangan: '2020-01-01', status: 'aktif',
  }],
  links: [],
}
const units = [{ id: 10, code: 'DAOP-1', name: 'Daerah Operasi 1' }, { id: 20, code: 'DIVRE-I', name: 'Divisi Regional I' }]
const wrappers = []
const mountPage = (overrides = {}) => {
  const wrapper = mount(AssetCategories, {
    props: {
      levels, nodes, assets, units, selectedUnitId: 10, selectedNodeId: 11111,
      capabilities: { manage_taxonomy: true, manage_assets: true, choose_unit: true },
      ...overrides,
    },
    global: { stubs: { MainLayout: { template: '<main><slot /></main>' }, Teleport: true } },
  })
  wrappers.push(wrapper)
  return wrapper
}

describe('AssetCategories unlimited hierarchy', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    inertia.page.props.auth.user = { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat', unit_kerja: null }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ assets_count: 2, historical_records_count: 5 }) }))
  })

  afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.unstubAllGlobals()
  })

  it('renders one explorer level while preserving the full unlimited path', () => {
    const wrapper = mountPage()
    expect(wrapper.findAll('section[aria-label]')).toHaveLength(1)
    expect(wrapper.get('section[aria-label="Model"]').exists()).toBe(true)
    expect(wrapper.get('[aria-label="Jalur kategori terpilih"]').text()).toContain('Peralatan Luar Sinyal Elektrik')
    expect(wrapper.get('[aria-label="Jalur kategori terpilih"]').text()).toContain('Indoor')
    expect(wrapper.text()).toContain('TC-900')
    expect(wrapper.get('[aria-label="Jalur kategori terpilih"]').text()).toContain('Track Circuit')
  })

  it('shows the automatic order before a clean root-category name', () => {
    const dayaSatu = {
      ...node(6, 1, null, 'DAYA SATU'),
      sort_order: 6,
    }
    const wrapper = mountPage({ nodes: [dayaSatu], selectedNodeId: dayaSatu.id })

    expect(wrapper.text()).toContain('6. DAYA SATU')
    expect(wrapper.text()).not.toContain('6. 6. DAYA SATU')
  })

  it('opens an empty next level so a child category can be created', async () => {
    const wrapper = mountPage({
      nodes: nodes.filter((item) => item.level_position <= 3),
      selectedNodeId: 111,
    })

    await wrapper.get('[aria-label="Buka Track Circuit"]').trigger('click')

    expect(wrapper.get('section[aria-label="Jenis Perangkat"]').exists()).toBe(true)
    await wrapper.get('[aria-label="Tambah Jenis Perangkat"]').trigger('click')
    await wrapper.get('#category-name').setValue('Indoor')
    await wrapper.get('dialog form').trigger('submit')

    expect(inertia.post).toHaveBeenCalledWith('/admin/asset-category-nodes', expect.objectContaining({
      asset_category_level_id: 4,
      parent_id: 111,
      name: 'Indoor',
    }), expect.objectContaining({ preserveScroll: true }))
  })

  it('changes a root selection and truncates deeper columns safely', async () => {
    const wrapper = mountPage()
    await wrapper.findAll('button').find((button) => button.text().includes('Semua kategori')).trigger('click')
    await wrapper.get('[aria-label="Pilih Aset Prasarana Sintel Catu Daya Sintel"]').trigger('click')
    expect(inertia.get).toHaveBeenCalledWith('/admin/asset-categories', {
      unit_kerja_id: 10, node: 2,
    }, expect.objectContaining({ preserveState: true, preserveScroll: true }))
    expect(wrapper.get('[aria-label="Jalur kategori terpilih"]').text()).toBe('Semua kategoriCatu Daya Sintel')
  })

  it('shows only categories populated in the selected region unless empty categories are requested', async () => {
    const emptyRoot = { ...nodes[1], subtree_assets_count: 0, subtree_units_count: 0 }
    const wrapper = mountPage({ nodes: [nodes[0], emptyRoot, ...nodes.slice(2)] })
    await wrapper.findAll('button').find((button) => button.text().includes('Semua kategori')).trigger('click')
    expect(wrapper.text()).not.toContain('Catu Daya Sintel')
    await wrapper.get('input[type="checkbox"]').setValue(true)
    expect(wrapper.text()).toContain('Catu Daya Sintel')
  })

  it('clears the previous category path when changing region', async () => {
    const wrapper = mountPage()
    await wrapper.get('#taxonomy-unit').setValue('20')
    expect(inertia.get).toHaveBeenCalledWith('/admin/asset-categories', {
      unit_kerja_id: 20, node: null,
    }, expect.objectContaining({ preserveState: true, preserveScroll: true }))
  })

  it('adds a global level through the generic endpoint', async () => {
    const wrapper = mountPage()
    await wrapper.get('[aria-label="Tambah level kategori"]').trigger('click')
    await wrapper.get('#level-name').setValue('Lokasi Teknis')
    await wrapper.get('dialog form').trigger('submit')
    expect(inertia.post).toHaveBeenCalledWith('/admin/asset-category-levels', { name: 'Lokasi Teknis' }, expect.objectContaining({ preserveScroll: true }))
  })

  it('deletes only the deepest custom level after confirmation', async () => {
    const wrapper = mountPage()
    expect(wrapper.find('[aria-label="Hapus level Jenis Perangkat"]').exists()).toBe(false)
    await wrapper.get('[aria-label="Hapus level Model"]').trigger('click')
    expect(wrapper.get('dialog').text()).toContain('Hapus level?')
    await wrapper.get('[aria-label="Konfirmasi hapus level"]').trigger('click')
    expect(inertia.delete).toHaveBeenCalledWith('/admin/asset-category-levels/5', {}, expect.objectContaining({ preserveScroll: true }))
    expect(wrapper.find('dialog').exists()).toBe(false)
  })

  it('keeps asset creation in Master Aset instead of duplicating it here', () => {
    const wrapper = mountPage()
    expect(wrapper.findAll('button').some((button) => button.text().includes('Tambah aset'))).toBe(false)
  })

  it('keeps long selected-category names clear of the action buttons', () => {
    const longNode = node(
      30,
      1,
      null,
      'PERALATAN DALAM SINYAL ELEKTRIK DENGAN NAMA KATEGORI OPERASIONAL YANG SANGAT PANJANG',
    )
    const wrapper = mountPage({ nodes: [longNode], selectedNodeId: longNode.id })

    expect(wrapper.get('[data-selected-category-name]').text()).toContain(longNode.name)
    expect(wrapper.get('[data-selected-category-name]').classes()).toContain('break-words')
    expect(wrapper.get('[data-selected-category-name]').classes()).not.toContain('truncate')
    expect(wrapper.get('[data-category-actions]').classes()).toContain('grid')
    expect(wrapper.get('[data-category-actions]').classes()).toContain('sm:grid-cols-2')
  })

  it('deletes only the selected regional asset after confirmation', async () => {
    const wrapper = mountPage()
    expect(wrapper.text()).not.toContain('Hapus aset wilayah')

    await wrapper.get('[aria-label="Hapus aset Track Circuit STA"]').trigger('click')

    expect(wrapper.get('dialog').text()).toContain('Track Circuit STA')
    await wrapper.get('dialog').findAll('button').find((button) => button.text() === 'Hapus aset').trigger('click')

    expect(inertia.delete).toHaveBeenCalledWith('/master-asset/7', expect.objectContaining({
      preserveScroll: true,
      onFinish: expect.any(Function),
    }))
  })

  it('keeps global taxonomy read-only for regional users while allowing regional assets', () => {
    inertia.page.props.auth.user = { id: 2, name: 'Operator', username: 'daop1', role: 'wilayah', unit_kerja: units[0] }
    const wrapper = mountPage({
      units: [],
      capabilities: { manage_taxonomy: false, manage_assets: true, choose_unit: false },
    })
    expect(wrapper.find('[aria-label="Tambah level kategori"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label^="Tambah Aset Prasarana"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Tambah aset')
    expect(wrapper.get('[aria-label="Hapus aset Track Circuit STA"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('DAOP-1')
  })
})

describe('MainLayout scoped navigation', () => {
  beforeEach(() => {
    vi.stubGlobal('localStorage', {
      getItem: vi.fn(() => null),
      setItem: vi.fn(),
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows Kategori Aset to regional users but keeps Unit & Akun hidden', () => {
    inertia.page = {
      url: '/admin/asset-categories',
      props: { auth: { user: { id: 2, name: 'Operator', username: 'daop1', role: 'wilayah', unit_kerja: units[0] } }, flash: {} },
    }
    const wrapper = mount(MainLayout, { global: { stubs: { Teleport: true } } })
    expect(wrapper.get('nav').text()).toContain('Kategori Aset')
    expect(wrapper.get('nav').text()).not.toContain('Unit & Akun')
    expect(wrapper.get('nav').text()).toContain('Import Data RAMS')
    wrapper.unmount()
  })

  it('shows Kategori Aset and Unit & Akun to pusat users', () => {
    inertia.page = {
      url: '/admin/asset-categories',
      props: { auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat', unit_kerja: null } }, flash: {} },
    }
    const wrapper = mount(MainLayout, { global: { stubs: { Teleport: true } } })
    expect(wrapper.get('nav').text()).toContain('Kategori Aset')
    expect(wrapper.get('nav').text()).toContain('Unit & Akun')
    expect(wrapper.get('nav').text()).toContain('Import Data RAMS')
    wrapper.unmount()
  })

  it('keeps Dashboard direct and places RAMS modules behind a disclosure control', async () => {
    inertia.page = {
      url: '/dashboard',
      props: { auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat', unit_kerja: null } }, flash: {} },
    }

    const wrapper = mount(MainLayout, { global: { stubs: { Teleport: true } } })

    const dashboardLink = wrapper.get('nav a[href="/dashboard"]')
    const moduleToggle = wrapper.get('button[aria-controls="rams-module-menu"]')

    expect(dashboardLink.element.closest('li').querySelector('button')).toBeNull()
    expect(dashboardLink.classes()).toContain('bg-[#171650]/[0.06]')
    expect(moduleToggle.attributes('aria-expanded')).toBe('false')
    expect(wrapper.get('#rams-module-menu').attributes('style')).toContain('display: none')
    expect(wrapper.get('nav a[href="/trouble-report/import"]').isVisible()).toBe(true)

    await moduleToggle.trigger('click')

    expect(moduleToggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.get('#rams-module-menu').attributes('style')).not.toContain('display: none')
    wrapper.unmount()
  })

  it('opens Modul RAMS automatically when one of its routes is active', () => {
    inertia.page = {
      url: '/risk-matrix',
      props: { auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat', unit_kerja: null } }, flash: {} },
    }

    const wrapper = mount(MainLayout, { global: { stubs: { Teleport: true } } })

    expect(wrapper.get('button[aria-controls="rams-module-menu"]').attributes('aria-expanded')).toBe('true')
    expect(wrapper.get('nav a[href="/risk-matrix"]').isVisible()).toBe(true)
    expect(wrapper.get('nav a[href="/risk-matrix"]').classes()).toContain('bg-[#171650]/[0.06]')
    wrapper.unmount()
  })
})
