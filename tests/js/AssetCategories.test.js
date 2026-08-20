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
  router: { get: inertia.get },
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
    lokasi: 'Stasiun A', jumlah_unit: 4, tanggal_pemasangan: '2020-01-01', status: 'aktif',
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
    await wrapper.get('[role="dialog"] form').trigger('submit')
    expect(inertia.post).toHaveBeenCalledWith('/admin/asset-category-levels', { name: 'Lokasi Teknis' }, expect.objectContaining({ preserveScroll: true }))
  })

  it('deletes only the deepest custom level after confirmation', async () => {
    const wrapper = mountPage()
    expect(wrapper.find('[aria-label="Hapus level Jenis Perangkat"]').exists()).toBe(false)
    await wrapper.get('[aria-label="Hapus level Model"]').trigger('click')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Hapus level?')
    await wrapper.get('[aria-label="Konfirmasi hapus level"]').trigger('click')
    expect(inertia.delete).toHaveBeenCalledWith('/admin/asset-category-levels/5', {}, expect.objectContaining({ preserveScroll: true }))
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('creates an asset for the selected region and selected depth', async () => {
    const wrapper = mountPage()
    await wrapper.findAll('button').find((button) => button.text().includes('Tambah aset')).trigger('click')
    expect(wrapper.find('#taxonomy-asset-name').exists()).toBe(false)
    expect(wrapper.find('#taxonomy-asset-status').exists()).toBe(false)
    await wrapper.get('#taxonomy-asset-units').setValue(5)
    await wrapper.get('#taxonomy-asset-date').setValue('2020-02-01')
    await wrapper.get('[role="dialog"] form').trigger('submit')
    expect(inertia.post).toHaveBeenCalledWith('/admin/asset-category-assets', expect.objectContaining({
      unit_kerja_id: 10, asset_category_node_id: 11111, nama_aset: 'TC-900',
      status: 'aktif', jumlah_unit: 5, tanggal_pemasangan: '2020-02-01',
    }), expect.objectContaining({ preserveScroll: true }))
  })

  it('previews counts before archiving only the selected region branch', async () => {
    const wrapper = mountPage()
    await wrapper.findAll('button').find((button) => button.text().includes('Hapus aset wilayah')).trigger('click')
    await Promise.resolve()
    expect(fetch).toHaveBeenCalledWith('/admin/asset-category-nodes/11111/archive-preview?unit_kerja_id=10', expect.any(Object))
    expect(wrapper.get('[role="dialog"]').text()).toContain('Riwayat dipertahankan')
    await wrapper.get('[role="dialog"]').findAll('button').find((button) => button.text().includes('Hapus 2 aset wilayah')).trigger('click')
    expect(inertia.delete).toHaveBeenCalledWith('/admin/asset-category-nodes/11111/assets', {
      unit_kerja_id: 10, confirmation: 'HAPUS ASET WILAYAH',
    }, expect.objectContaining({ preserveScroll: true }))
  })

  it('keeps global taxonomy read-only for regional users while allowing regional assets', () => {
    inertia.page.props.auth.user = { id: 2, name: 'Operator', username: 'daop1', role: 'wilayah', unit_kerja: units[0] }
    const wrapper = mountPage({
      units: [],
      capabilities: { manage_taxonomy: false, manage_assets: true, choose_unit: false },
    })
    expect(wrapper.find('[aria-label="Tambah level kategori"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label^="Tambah Aset Prasarana"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Tambah aset')
    expect(wrapper.text()).toContain('DAOP-1')
  })
})

describe('MainLayout administration navigation', () => {
  it('shows Kategori Aset to regional users but keeps Unit & Akun hidden', () => {
    inertia.page = {
      url: '/admin/asset-categories',
      props: { auth: { user: { id: 2, name: 'Operator', username: 'daop1', role: 'wilayah', unit_kerja: units[0] } }, flash: {} },
    }
    const wrapper = mount(MainLayout, { global: { stubs: { Teleport: true } } })
    expect(wrapper.text()).toContain('Kategori Aset')
    expect(wrapper.text()).not.toContain('Unit & Akun')
    wrapper.unmount()
  })

  it('shows Kategori Aset and Unit & Akun to pusat users', () => {
    inertia.page = {
      url: '/admin/asset-categories',
      props: { auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat', unit_kerja: null } }, flash: {} },
    }
    const wrapper = mount(MainLayout, { global: { stubs: { Teleport: true } } })
    expect(wrapper.text()).toContain('Kategori Aset')
    expect(wrapper.text()).toContain('Unit & Akun')
    wrapper.unmount()
  })
})
