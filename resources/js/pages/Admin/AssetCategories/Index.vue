<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ArrowLeft, Boxes, ChevronRight, FolderTree, Layers3, MapPin, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import CategoryDialog from './Partials/CategoryDialog.vue'
import DeleteCategoryDialog from './Partials/DeleteCategoryDialog.vue'
import LevelDialog from './Partials/LevelDialog.vue'
import TaxonomyAssetDialog from './Partials/TaxonomyAssetDialog.vue'
import ArchiveBranchAssetsDialog from './Partials/ArchiveBranchAssetsDialog.vue'

const props = defineProps({
  levels: { type: Array, required: true },
  nodes: { type: Array, required: true },
  assets: { type: Object, required: true },
  units: { type: Array, default: () => [] },
  selectedUnitId: { type: Number, default: null },
  selectedNodeId: { type: Number, default: null },
  capabilities: { type: Object, required: true },
})

const page = usePage()
const selectedUnit = ref(props.selectedUnitId)
const showEmptyCategories = ref(false)
const categoryQuery = ref('')
const nodeById = computed(() => new Map(props.nodes.map((node) => [node.id, node])))
const pathFor = (nodeId) => {
  const path = []
  let current = nodeById.value.get(nodeId)
  while (current) {
    path.unshift(current.id)
    current = nodeById.value.get(current.parent_id)
  }
  return path
}
const activePath = ref(pathFor(props.selectedNodeId))
const currentLevelIndex = ref(Math.max(activePath.value.length - 1, 0))

watch(() => props.selectedNodeId, (value) => { activePath.value = pathFor(value) })
watch(() => props.selectedUnitId, (value) => {
  selectedUnit.value = value
  activePath.value = pathFor(props.selectedNodeId)
  currentLevelIndex.value = Math.max(activePath.value.length - 1, 0)
  categoryQuery.value = ''
})

const selectedNode = computed(() => nodeById.value.get(activePath.value.at(-1)) ?? null)
const activeUnit = computed(() => props.units.find((unit) => unit.id === selectedUnit.value) ?? page.props.auth.user.unit_kerja ?? null)
const canManageTaxonomy = computed(() => props.capabilities.manage_taxonomy === true)
const canChooseUnit = computed(() => props.capabilities.choose_unit === true)
const nodeDisplayName = (node) => {
  const name = String(node?.name ?? '')
  const order = Number(node?.sort_order)

  if (node?.level_position !== 1 || /^\s*\d+\s*[.\-]/.test(name) || !Number.isInteger(order) || order <= 0) {
    return name
  }

  return `${order}. ${name}`
}
const visibleNodes = computed(() => showEmptyCategories.value
  ? props.nodes
  : props.nodes.filter((node) => node.subtree_assets_count > 0 || activePath.value.includes(node.id)))
const selectedAt = (index) => activePath.value[index] ?? null
const currentLevel = computed(() => props.levels[currentLevelIndex.value] ?? props.levels[0] ?? null)
const currentParent = computed(() => currentLevelIndex.value === 0
  ? null
  : nodeById.value.get(activePath.value[currentLevelIndex.value - 1]) ?? null)
const currentItems = computed(() => {
  if (!currentLevel.value || (currentLevelIndex.value > 0 && !currentParent.value)) return []
  const parentId = currentParent.value?.id ?? null
  const needle = categoryQuery.value.trim().toLocaleLowerCase('id')

  return visibleNodes.value.filter((node) => (
    node.asset_category_level_id === currentLevel.value.id
      && node.parent_id === parentId
      && (!needle || node.name.toLocaleLowerCase('id').includes(needle))
  ))
})
const selectedPath = computed(() => activePath.value.map((id) => nodeById.value.get(id)).filter(Boolean))
const lastLevel = computed(() => props.levels.at(-1) ?? null)
const hasNextLevel = (node, index = currentLevelIndex.value) => {
  const nextLevel = props.levels[index + 1]
  return Boolean(nextLevel)
}

const visit = (nodeId = selectedNode.value?.id) => router.get('/admin/asset-categories', {
  unit_kerja_id: selectedUnit.value,
  node: nodeId,
}, { preserveState: true, preserveScroll: true, replace: true })

const selectNode = (node, index) => {
  activePath.value = [...activePath.value.slice(0, index), node.id]
  visit(node.id)
}

const selectExplorerNode = (node) => selectNode(node, currentLevelIndex.value)
const openChildren = (node) => {
  const index = currentLevelIndex.value
  selectNode(node, index)
  if (props.levels[index + 1]) {
    currentLevelIndex.value = index + 1
    categoryQuery.value = ''
  }
}
const goBack = () => {
  if (currentLevelIndex.value > 0) {
    currentLevelIndex.value -= 1
    categoryQuery.value = ''
  }
}
const goToRoot = () => {
  currentLevelIndex.value = 0
  categoryQuery.value = ''
}
const openBreadcrumb = (node, index) => {
  selectNode(node, index)
  currentLevelIndex.value = Math.min(index + 1, props.levels.length - 1)
  categoryQuery.value = ''
}

const changeUnit = () => {
  showEmptyCategories.value = false
  activePath.value = []
  visit(null)
}

const openCreateCurrentNode = () => {
  if (currentLevel.value) openCreateNode(currentLevel.value, currentLevelIndex.value)
}

const levelForm = ref(null)
const openLevel = () => { levelForm.value = useForm({ name: '' }) }
const submitLevel = () => levelForm.value.post('/admin/asset-category-levels', {
  preserveScroll: true,
  onSuccess: () => { levelForm.value = null },
})

const deleteLevel = ref(null)
const deleteLevelForm = ref(null)
const openDeleteLevel = (level) => {
  deleteLevel.value = level
  deleteLevelForm.value = useForm({})
}
const clearDeleteLevel = () => {
  deleteLevel.value = null
  deleteLevelForm.value = null
}
const closeDeleteLevel = () => {
  if (!deleteLevelForm.value?.processing) clearDeleteLevel()
}
const submitDeleteLevel = () => deleteLevelForm.value.delete(`/admin/asset-category-levels/${deleteLevel.value.id}`, {
  preserveScroll: true,
  onSuccess: clearDeleteLevel,
})

const nodeDialog = ref(null)
const nodeForm = ref(null)
const openCreateNode = (level, index) => {
  nodeDialog.value = { mode: 'create', level, parent: nodeById.value.get(activePath.value[index - 1]) ?? null }
  nodeForm.value = useForm({
    asset_category_level_id: level.id,
    unit_kerja_id: selectedUnit.value,
    parent_id: index === 0 ? null : activePath.value[index - 1],
    name: '',
    sort_order: 0,
    dashboard_color: null,
  })
}
const openEditNode = (node) => {
  nodeDialog.value = { mode: 'edit', level: props.levels.find((level) => level.id === node.asset_category_level_id), node }
  nodeForm.value = useForm({
    name: node.name,
    sort_order: node.sort_order,
    dashboard_color: node.dashboard_color,
    is_active: node.is_active,
  })
}
const closeNodeDialog = () => {
  if (!nodeForm.value?.processing) {
    nodeDialog.value = null
    nodeForm.value = null
  }
}
const completeNodeDialog = () => {
  nodeDialog.value = null
  nodeForm.value = null
}
const submitNode = () => {
  const method = nodeDialog.value.mode === 'create' ? 'post' : 'put'
  const endpoint = nodeDialog.value.mode === 'create'
    ? '/admin/asset-category-nodes'
    : `/admin/asset-category-nodes/${nodeDialog.value.node.id}`
  nodeForm.value[method](endpoint, { preserveScroll: true, onSuccess: completeNodeDialog })
}

const deleteNode = ref(null)
const deleteNodeForm = ref(null)
const openDeleteNode = (node) => {
  deleteNode.value = node
  deleteNodeForm.value = useForm({})
}
const submitDeleteNode = () => deleteNodeForm.value.delete(`/admin/asset-category-nodes/${deleteNode.value.id}`, {
  preserveScroll: true,
  onSuccess: () => { deleteNode.value = null; deleteNodeForm.value = null },
})

const assetForm = ref(null)
const openAsset = () => {
  if (!selectedNode.value) return
  assetForm.value = useForm({
    unit_kerja_id: canChooseUnit.value ? selectedUnit.value : undefined,
    asset_category_node_id: selectedNode.value.id,
    nama_aset: selectedNode.value.name,
    jumlah_unit: 1,
    tanggal_pemasangan: '',
    status: 'aktif',
  })
}
const submitAsset = () => assetForm.value.post('/admin/asset-category-assets', {
  preserveScroll: true,
  onSuccess: () => { assetForm.value = null },
})

const archiveDialog = ref(false)
const archiveLoading = ref(false)
const archivePreview = ref(null)
const archiveForm = ref(null)
const openArchive = async () => {
  if (!selectedNode.value) return
  archiveDialog.value = true
  archiveLoading.value = true
  archivePreview.value = null
  archiveForm.value = useForm({
    unit_kerja_id: canChooseUnit.value ? selectedUnit.value : undefined,
    confirmation: 'HAPUS ASET WILAYAH',
  })
  const query = canChooseUnit.value ? `?unit_kerja_id=${selectedUnit.value}` : ''
  try {
    const response = await fetch(`/admin/asset-category-nodes/${selectedNode.value.id}/archive-preview${query}`, {
      headers: { Accept: 'application/json' }, credentials: 'same-origin',
    })
    archivePreview.value = response.ok ? await response.json() : { assets_count: 0, historical_records_count: 0 }
  } finally {
    archiveLoading.value = false
  }
}
const submitArchive = () => archiveForm.value.delete(`/admin/asset-category-nodes/${selectedNode.value.id}/assets`, {
  preserveScroll: true,
  onSuccess: () => { archiveDialog.value = false; archivePreview.value = null; archiveForm.value = null },
})

const formatDate = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value)) : '—'
const statusLabel = (value) => ({ aktif: 'Aktif', nonaktif: 'Nonaktif', dalam_perbaikan: 'Dalam perbaikan' }[value] ?? value)
</script>

<template>
  <Head title="Kategori Aset" />
  <MainLayout>
    <header class="flex flex-col gap-5 border-b border-slate-200 pb-6 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 data-dialog-focus-fallback tabindex="-1" class="rounded text-2xl font-semibold tracking-tight text-slate-950 outline-none focus-visible:ring-2 focus-visible:ring-[#171650]">Kategori Aset</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Susun level kategori global, lalu kelola aset yang hanya berlaku pada Daop atau Divre terpilih.</p>
      </div>
      <div class="w-full rounded-xl bg-[#171650] p-4 text-white lg:w-[25rem]">
        <label for="taxonomy-unit" class="flex items-center gap-2 text-xs font-medium text-indigo-100"><MapPin :size="15" aria-hidden="true" /> Wilayah aset</label>
        <select v-if="canChooseUnit" id="taxonomy-unit" v-model="selectedUnit" class="mt-2 h-11 w-full rounded-lg border border-white/20 bg-white px-3 text-sm font-semibold text-[#171650] outline-none focus:ring-4 focus:ring-white/20" @change="changeUnit">
          <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }} — {{ unit.name }}</option>
        </select>
        <p v-else class="mt-2 text-sm font-semibold">{{ activeUnit?.code }} — {{ activeUnit?.name }}</p>
      </div>
    </header>

    <section class="mt-7" aria-labelledby="taxonomy-heading">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h2 id="taxonomy-heading" class="text-lg font-semibold text-slate-950">Jelajahi kategori {{ activeUnit?.code }}</h2>
          <p class="mt-1 text-sm text-slate-600">Buka kategori seperti folder. Aset wilayah muncul di panel kanan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-3 text-sm font-medium text-slate-600 hover:bg-slate-100">
            <input v-model="showEmptyCategories" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#171650] focus:ring-[#171650]" />
            Tampilkan kategori kosong
          </label>
          <button v-if="canManageTaxonomy && lastLevel?.position > 3" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus-visible:ring-2 focus-visible:ring-[#171650]" :aria-label="`Hapus level ${lastLevel.name}`" :title="`Hapus level ${lastLevel.name}`" @click="openDeleteLevel(lastLevel)"><Trash2 :size="17" aria-hidden="true" /></button>
          <button v-if="canManageTaxonomy" type="button" class="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-[#171650] hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#171650]" aria-label="Tambah level kategori" @click="openLevel"><Layers3 :size="18" aria-hidden="true" /> Tambah level</button>
        </div>
      </div>

      <nav aria-label="Jalur kategori terpilih" class="mt-4 flex min-h-12 items-center gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-600 shadow-sm">
        <button type="button" class="h-9 shrink-0 rounded-lg px-3 font-semibold text-[#171650] hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" @click="goToRoot">Semua kategori</button>
        <template v-for="(node, index) in selectedPath" :key="node.id">
          <ChevronRight :size="14" class="shrink-0 text-slate-400" aria-hidden="true" />
          <button type="button" class="h-9 max-w-52 shrink-0 truncate rounded-lg px-2 font-medium text-slate-700 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" @click="openBreadcrumb(node, index)">{{ nodeDisplayName(node) }}</button>
        </template>
      </nav>

      <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(19rem,0.8fr)_minmax(0,1.6fr)]">
        <section class="flex min-h-[38rem] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" :aria-label="currentLevel?.name || 'Kategori'">
          <header class="border-b border-slate-200 p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex min-w-0 items-start gap-3">
                <button v-if="currentLevelIndex > 0" type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" aria-label="Kembali ke level sebelumnya" @click="goBack"><ArrowLeft :size="18" aria-hidden="true" /></button>
                <div class="min-w-0 pt-0.5">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Level {{ currentLevel?.position }}</p>
                  <h3 class="mt-1 truncate text-base font-semibold text-[#171650]">{{ currentLevel?.name }}</h3>
                  <p v-if="currentParent" class="mt-1 truncate text-xs text-slate-500">Di dalam {{ currentParent.name }}</p>
                </div>
              </div>
              <button v-if="canManageTaxonomy" type="button" class="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-lg bg-orange-50 px-3 text-xs font-semibold text-orange-700 hover:bg-orange-100 focus-visible:ring-2 focus-visible:ring-[#171650] disabled:cursor-not-allowed disabled:opacity-50" :aria-label="`Tambah ${currentLevel?.name}`" :disabled="currentLevelIndex > 0 && !currentParent" @click="openCreateCurrentNode"><Plus :size="16" aria-hidden="true" /> Tambah</button>
            </div>
            <label class="relative mt-4 block">
              <span class="sr-only">Cari pada {{ currentLevel?.name }}</span>
              <Search :size="17" class="pointer-events-none absolute left-3 top-3 text-slate-400" aria-hidden="true" />
              <input v-model="categoryQuery" type="search" :aria-label="`Cari pada ${currentLevel?.name}`" class="h-11 w-full rounded-lg border border-slate-300 bg-slate-50 pl-10 pr-3 text-sm outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10" placeholder="Cari kategori…" />
            </label>
          </header>

          <div class="flex-1 space-y-2 overflow-y-auto p-3">
            <article v-for="item in currentItems" :key="item.id" class="flex overflow-hidden rounded-lg border transition" :class="item.id === selectedAt(currentLevelIndex) ? 'border-orange-300 bg-orange-50' : 'border-slate-200 hover:border-slate-300'">
              <button type="button" class="flex min-h-16 min-w-0 flex-1 items-center gap-3 px-3 py-3 text-left outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#171650]" :aria-label="`Pilih ${currentLevel?.name} ${item.name}`" :aria-pressed="item.id === selectedAt(currentLevelIndex)" @click="selectExplorerNode(item)">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[#171650]" :style="item.dashboard_color ? { backgroundColor: `${item.dashboard_color}20`, color: item.dashboard_color } : {}"><FolderTree :size="19" aria-hidden="true" /></span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900">{{ nodeDisplayName(item) }}</span><span class="mt-1 block text-xs text-slate-500">{{ item.subtree_assets_count ?? 0 }} aset · {{ item.subtree_units_count ?? 0 }} unit</span></span>
              </button>
              <button v-if="hasNextLevel(item)" type="button" class="flex min-h-16 shrink-0 items-center gap-1 border-l border-slate-200 px-3 text-xs font-semibold text-[#171650] hover:bg-white focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#171650]" :aria-label="`Buka ${item.name}`" @click="openChildren(item)">Buka <ChevronRight :size="15" aria-hidden="true" /></button>
            </article>
            <div v-if="!currentItems.length" class="flex min-h-64 items-center justify-center px-6 text-center"><div><FolderTree :size="28" class="mx-auto text-slate-300" aria-hidden="true" /><p class="mt-3 text-sm font-semibold text-slate-700">{{ categoryQuery ? 'Kategori tidak ditemukan' : 'Belum ada kategori pada wilayah ini' }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ categoryQuery ? 'Coba kata pencarian lain.' : 'Aktifkan kategori kosong atau tambahkan kategori baru.' }}</p></div></div>
          </div>
        </section>

        <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="regional-assets-heading">
          <div v-if="selectedNode">
            <header class="border-b border-slate-200 p-5">
              <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Kategori terpilih</p><h3 class="mt-1 truncate text-xl font-semibold text-slate-950">{{ nodeDisplayName(selectedNode) }}</h3><p class="mt-2 text-sm text-slate-600">{{ selectedNode.subtree_assets_count ?? 0 }} aset · {{ selectedNode.subtree_units_count ?? 0 }} unit di {{ activeUnit?.code }}</p></div>
                <div class="flex flex-wrap gap-2">
                  <button v-if="canManageTaxonomy" type="button" class="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#171650]" @click="openEditNode(selectedNode)"><Pencil :size="16" aria-hidden="true" /> Ubah</button>
                  <button v-if="canManageTaxonomy" type="button" class="inline-flex h-11 items-center gap-2 rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-700" @click="openDeleteNode(selectedNode)"><Trash2 :size="16" aria-hidden="true" /> Hapus kategori</button>
                  <button type="button" class="inline-flex h-11 items-center gap-2 rounded-lg bg-[#F15A24] px-4 text-sm font-semibold text-white hover:bg-orange-700 focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2" @click="openAsset"><Plus :size="17" aria-hidden="true" /> Tambah aset</button>
                </div>
              </div>
            </header>
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div><h3 id="regional-assets-heading" class="flex items-center gap-2 text-base font-semibold text-slate-950"><Boxes :size="18" aria-hidden="true" /> Aset wilayah</h3><p class="mt-1 text-xs text-slate-500">Peralatan nyata pada kategori ini dan seluruh turunannya.</p></div>
              <button type="button" class="inline-flex h-10 items-center gap-2 self-start rounded-lg px-3 text-xs font-semibold text-red-700 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-700" @click="openArchive"><Trash2 :size="15" aria-hidden="true" /> Hapus aset wilayah</button>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Nama aset</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3 text-right">Unit</th><th class="px-4 py-3">Pemasangan</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="asset in assets.data" :key="asset.id" class="text-slate-700"><td class="px-4 py-3 font-semibold text-slate-950">{{ asset.nama_aset }}</td><td class="px-4 py-3">{{ asset.category_node?.name || asset.subsystem || '—' }}</td><td class="px-4 py-3 text-right tabular-nums">{{ asset.jumlah_unit }}</td><td class="whitespace-nowrap px-4 py-3">{{ formatDate(asset.tanggal_pemasangan) }}</td><td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium" :class="asset.status === 'aktif' ? 'bg-emerald-50 text-emerald-700' : asset.status === 'dalam_perbaikan' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600'">{{ statusLabel(asset.status) }}</span></td></tr>
                  <tr v-if="!assets.data.length"><td colspan="5" class="px-6 py-16 text-center"><p class="font-semibold text-slate-700">Belum ada aset pada kategori ini</p><p class="mt-1 text-xs text-slate-500">Gunakan tombol Tambah aset untuk mulai mengisi data {{ activeUnit?.code }}.</p></td></tr>
                </tbody>
              </table>
            </div>
            <nav v-if="assets.links?.length > 3" aria-label="Halaman aset" class="flex flex-wrap justify-end gap-1 border-t border-slate-200 p-3"><Link v-for="link in assets.links" :key="link.label" :href="link.url || '#'" class="flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm" :class="link.active ? 'bg-[#171650] text-white' : link.url ? 'text-slate-700 hover:bg-slate-100' : 'pointer-events-none text-slate-400'" preserve-scroll v-html="link.label" /></nav>
          </div>
          <div v-else class="flex min-h-[38rem] items-center justify-center px-8 text-center"><div><Boxes :size="34" class="mx-auto text-slate-300" aria-hidden="true" /><h3 class="mt-4 text-base font-semibold text-slate-800">Pilih kategori terlebih dahulu</h3><p class="mt-2 max-w-sm text-sm leading-6 text-slate-500">Aset, jumlah unit, tanggal pemasangan, dan status akan muncul di panel ini.</p></div></div>
        </section>
      </div>
    </section>

    <section v-if="false" class="mt-8 border-t border-slate-200 pt-7" aria-labelledby="legacy-regional-assets-heading">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 id="legacy-regional-assets-heading" class="flex items-center gap-2 text-lg font-semibold text-slate-950"><Boxes :size="20" aria-hidden="true" /> Aset wilayah</h2>
          <p class="mt-1 text-sm text-slate-600">{{ selectedNode ? `Menampilkan ${selectedNode.name} beserta kategori turunannya.` : 'Pilih kategori untuk melihat aset.' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button v-if="selectedNode" type="button" class="inline-flex h-11 items-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-700" @click="openArchive"><Trash2 :size="17" aria-hidden="true" /> Hapus aset wilayah</button>
          <button v-if="selectedNode" type="button" class="inline-flex h-11 items-center gap-2 rounded-lg bg-[#F15A24] px-4 text-sm font-semibold text-white hover:bg-orange-700 focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2" @click="openAsset"><Plus :size="18" aria-hidden="true" /> Tambah aset</button>
        </div>
      </div>

      <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
              <tr><th class="px-4 py-3">Nama aset</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3 text-right">Jumlah unit</th><th class="px-4 py-3">Pemasangan</th><th class="px-4 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="asset in assets.data" :key="asset.id" class="text-slate-700">
                <td class="px-4 py-3 font-semibold text-slate-950">{{ asset.nama_aset }}</td>
                <td class="px-4 py-3">{{ asset.category_node?.name || asset.subsystem || '—' }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ asset.jumlah_unit }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(asset.tanggal_pemasangan) }}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium" :class="asset.status === 'aktif' ? 'bg-emerald-50 text-emerald-700' : asset.status === 'dalam_perbaikan' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600'">{{ statusLabel(asset.status) }}</span></td>
              </tr>
              <tr v-if="!assets.data.length"><td colspan="6" class="px-6 py-12 text-center"><p class="font-semibold text-slate-700">Belum ada aset pada pilihan ini</p><p class="mt-1 text-xs text-slate-500">Tambahkan aset atau pilih kategori lain.</p></td></tr>
            </tbody>
          </table>
        </div>
        <nav v-if="assets.links?.length > 3" aria-label="Halaman aset" class="flex flex-wrap justify-end gap-1 border-t border-slate-200 p-3">
          <Link v-for="link in assets.links" :key="link.label" :href="link.url || '#'" class="flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm" :class="link.active ? 'bg-[#171650] text-white' : link.url ? 'text-slate-700 hover:bg-slate-100' : 'pointer-events-none text-slate-400'" preserve-scroll v-html="link.label" />
        </nav>
      </div>
    </section>

    <LevelDialog v-if="levelForm" :form="levelForm" @close="levelForm = null" @submit="submitLevel" />
    <DeleteCategoryDialog
      v-if="deleteLevel && deleteLevelForm"
      :category="deleteLevel"
      level-label="level"
      error-key="level"
      :form="deleteLevelForm"
      @close="closeDeleteLevel"
      @confirm="submitDeleteLevel"
    />
    <CategoryDialog
      v-if="nodeDialog && nodeForm"
      :title="`${nodeDialog.mode === 'create' ? 'Tambah' : 'Ubah'} ${nodeDialog.level.name}`"
      :level-label="nodeDialog.level.name"
      :description="nodeDialog.mode === 'create' ? nodeDialog.parent ? `Ditempatkan di bawah ${nodeDialog.parent.name}.` : 'Kategori ini menjadi awal jalur global.' : 'Perubahan nama berlaku untuk semua wilayah.'"
      :form="nodeForm"
      :show-sort-order="nodeDialog.mode === 'edit' || nodeDialog.level.position !== 1"
      @close="closeNodeDialog"
      @submit="submitNode"
    />
    <DeleteCategoryDialog v-if="deleteNode && deleteNodeForm" :category="deleteNode" level-label="kategori" :form="deleteNodeForm" @close="deleteNode = null" @confirm="submitDeleteNode" />
    <TaxonomyAssetDialog v-if="assetForm && selectedNode" :form="assetForm" :node-name="selectedNode.name" @close="assetForm = null" @submit="submitAsset" />
    <ArchiveBranchAssetsDialog v-if="archiveDialog && archiveForm && selectedNode" :node-name="selectedNode.name" :preview="archivePreview" :loading="archiveLoading" :form="archiveForm" @close="archiveDialog = false" @confirm="submitArchive" />
  </MainLayout>
</template>
