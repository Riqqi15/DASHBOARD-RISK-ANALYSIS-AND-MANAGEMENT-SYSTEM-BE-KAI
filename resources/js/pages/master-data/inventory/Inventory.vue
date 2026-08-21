<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AlertCircle, ArrowRightLeft, Building2, CheckCircle2, PackagePlus, Pencil, RefreshCw, Scale, TriangleAlert, Warehouse } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import InventoryStats from './Partials/InventoryStats.vue'
import InventoryFilters from './Partials/InventoryFilters.vue'
import InventoryTable from './Partials/InventoryTable.vue'
import MovementDialog from './Partials/MovementDialog.vue'
import MovementHistory from './Partials/MovementHistory.vue'
import SparePartDialog from './Partials/SparePartDialog.vue'

const props = defineProps({
  stats: { type: Object, required: true },
  stocks: { type: Object, required: true },
  movements: { type: Object, required: true },
  predictiveAssets: { type: Array, default: () => [] },
  reconciliation: { type: Object, default: () => ({ rows: [], stats: {} }) },
  spareParts: { type: Array, required: true },
  categories: { type: Array, required: true },
  units: { type: Array, required: true },
  filters: { type: Object, required: true },
  can: { type: Object, required: true },
})

const normalizedFilters = (filters) => ({
  ...filters,
  tab: filters.tab === 'master' && !props.can.manage_master ? 'stock' : filters.tab,
  reconciliation_status: filters.reconciliation_status || 'all',
  master_page: String(filters.master_page || '1'),
})
const filterState = reactive(normalizedFilters(props.filters))
const loading = ref(false)
const loadError = ref('')
const movementOpen = ref(false)
const movementPart = ref(null)
const movementStock = ref(null)
const correctionSource = ref(null)
const movementDialogKey = ref(0)
const partOpen = ref(false)
const editedPart = ref(null)
const partDialogKey = ref(0)
const movementOpener = ref(null)
const partOpener = ref(null)
const masterPage = ref(1)
let searchTimer

const activeTab = computed(() => filterState.tab)
const tabs = computed(() => [
  { key: 'stock', label: 'Stok Saat Ini', count: props.stocks.total ?? props.stocks.data.length },
  { key: 'history', label: 'Riwayat Transaksi', count: props.movements.total ?? props.movements.data.length },
  { key: 'predictive', label: 'Predictive Data Asset', count: props.predictiveAssets.length },
  { key: 'reconciliation', label: 'Rekonsiliasi Excel', count: props.reconciliation.stats?.total ?? props.reconciliation.rows.length },
  ...(props.can.manage_master ? [{ key: 'master', label: 'Master Suku Cadang', count: props.spareParts.length }] : []),
])
const selectedUnit = computed(() => props.units.find((unit) => String(unit.id) === String(filterState.unit_kerja_id)))
const scopedUnit = computed(() => props.stocks.data[0]?.unit ?? props.movements.data[0]?.unit)
const unitContext = computed(() => props.can.choose_unit
  ? selectedUnit.value ? `${selectedUnit.value.code} — ${selectedUnit.value.name}` : 'Belum ada unit kerja aktif'
  : scopedUnit.value ? `${scopedUnit.value.code} — ${scopedUnit.value.name}` : 'Unit kerja akun Anda')
const hasActiveFilters = computed(() => Boolean(
  filterState.search || filterState.asset_group_id || filterState.asset_subsystem_id
  || (activeTab.value === 'stock' && filterState.stock_status !== 'all')
  || (activeTab.value === 'history' && (filterState.movement_type || filterState.date_from || filterState.date_to))
  || (activeTab.value === 'reconciliation' && filterState.reconciliation_status !== 'all')
))
const masterPageSize = 50
const masterPageCount = computed(() => Math.max(1, Math.ceil(props.spareParts.length / masterPageSize)))
const masterPageParts = computed(() => props.spareParts.slice((masterPage.value - 1) * masterPageSize, masterPage.value * masterPageSize))
const pageFromFilters = (filters) => Math.min(
  Math.max(Number.parseInt(filters.master_page, 10) || 1, 1),
  masterPageCount.value,
)
watch(() => props.filters, (filters) => {
  Object.assign(filterState, normalizedFilters(filters))
  masterPage.value = pageFromFilters(filters)
}, { deep: true, immediate: true })
watch(() => props.spareParts.length, () => {
  masterPage.value = Math.min(masterPage.value, masterPageCount.value)
  filterState.master_page = String(masterPage.value)
})

const requestFilters = (overrides = {}) => ({ ...filterState, ...overrides })
const visit = (overrides = {}, options = {}) => {
  loadError.value = ''
  router.get('/inventory', requestFilters(overrides), {
    preserveState: true,
    preserveScroll: true,
    replace: options.replace ?? true,
  })
}
const resetMasterPage = () => {
  masterPage.value = 1
  filterState.master_page = '1'
}
const changeFilter = ({ key, value }) => {
  filterState[key] = value
  if (key === 'asset_group_id') filterState.asset_subsystem_id = ''
  resetMasterPage()
  if (key === 'search') {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => visit(), 300)
  } else visit()
}
const resetFilters = () => {
  if (!hasActiveFilters.value) return
  Object.assign(filterState, {
    search: '', asset_group_id: '', asset_subsystem_id: '', stock_status: 'all', unit_kerja_id: '',
    movement_type: '', date_from: '', date_to: '',
    reconciliation_status: 'all',
    master_page: '1',
  })
  filterState.unit_kerja_id = props.filters.unit_kerja_id
  masterPage.value = 1
  visit()
}
const switchTab = (tab) => {
  if (tab === activeTab.value) return
  filterState.tab = tab
  resetMasterPage()
  visit({ tab, master_page: '1' })
}
const changeMasterPage = (page) => {
  const nextPage = Math.min(Math.max(page, 1), masterPageCount.value)
  if (nextPage === masterPage.value) return
  masterPage.value = nextPage
  filterState.master_page = String(nextPage)
  visit({ master_page: String(nextPage) }, { replace: false })
}

const openMovement = (stock = null) => {
  movementOpener.value = document.activeElement instanceof HTMLElement ? document.activeElement : null
  movementStock.value = stock
  movementPart.value = stock?.spare_part ?? null
  correctionSource.value = null
  movementDialogKey.value += 1
  movementOpen.value = true
}
const openCorrection = (movement) => {
  movementOpener.value = document.activeElement instanceof HTMLElement ? document.activeElement : null
  movementStock.value = props.stocks.data.find((stock) =>
    String(stock.unit_kerja_id) === String(movement.unit_kerja_id)
    && String(stock.spare_part_id) === String(movement.spare_part_id)) ?? null
  movementPart.value = movement.spare_part
  correctionSource.value = movement
  movementDialogKey.value += 1
  movementOpen.value = true
}
const openPart = (part = null) => {
  partOpener.value = document.activeElement instanceof HTMLElement ? document.activeElement : null
  editedPart.value = part
  partDialogKey.value += 1
  partOpen.value = true
}
const closeMovement = () => {
  movementOpen.value = false
  nextTick(() => movementOpener.value?.focus())
}
const closePart = () => {
  partOpen.value = false
  nextTick(() => partOpener.value?.focus())
}

const unregisterStart = router.on?.('start', () => { loading.value = true })
const unregisterFinish = router.on?.('finish', () => { loading.value = false })
const unregisterInvalid = router.on?.('invalid', () => {
  loading.value = false
  loadError.value = 'Periksa koneksi lalu muat ulang halaman.'
})
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  unregisterStart?.(); unregisterFinish?.(); unregisterInvalid?.()
})

const masterCategory = (part) => part.category
  ? `${part.category.group.name} / ${part.category.system.name} / ${part.category.subsystem.name}`
  : 'Tanpa kategori'

const predictiveStockLabel = (value) => Number(value) < 0 ? `Defisit stok ${Math.abs(Number(value))}` : String(value ?? '—')
const parityLabel = (status) => ({
  matched: 'Sesuai Excel', corrected: 'Dikoreksi backend', excel_data_missing: 'Data Excel kurang', not_compared: 'Belum dibandingkan',
})[status] ?? status ?? 'Belum dibandingkan'
const reconciliationLabel = (status) => ({
  matched: 'Sesuai', difference: 'Ada selisih', missing_ledger: 'Belum ada stok ledger', missing_excel: 'Tidak ada referensi Excel', ambiguous: 'Kecocokan ambigu',
})[status] ?? status
const reconciliationTone = (status) => ({
  matched: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  difference: 'border-orange-200 bg-orange-50 text-orange-800',
  missing_ledger: 'border-red-200 bg-red-50 text-red-700',
  missing_excel: 'border-blue-200 bg-blue-50 text-blue-700',
  ambiguous: 'border-amber-200 bg-amber-50 text-amber-800',
})[status] || 'border-slate-200 bg-slate-50 text-slate-700'
</script>

<template>
  <Head><title>Inventori Suku Cadang</title></Head>
  <MainLayout>
    <div class="min-w-0 space-y-5 pb-10">
      <header class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-l-4 border-[#f26522] px-5 py-5 sm:px-6">
          <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
              <p class="font-mono text-sm font-semibold uppercase tracking-[0.18em] text-[#2d2a70]">Operasional / ledger persediaan</p>
              <h1 class="mt-2 text-2xl font-semibold tracking-tight text-[#171650]">Inventori Suku Cadang</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Periksa kondisi stok dan catat setiap pergerakan dengan jejak audit yang tetap utuh.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
              <button v-if="can.manage_master" data-add-part type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-[#2d2a70] bg-white px-4 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus:ring-2 focus:ring-[#2d2a70] focus:ring-offset-2" @click="openPart()"><PackagePlus :size="18" aria-hidden="true" /> Tambah suku cadang</button>
              <button v-if="can.record_movement" data-record-movement type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#f26522] px-4 text-sm font-semibold text-white outline-none hover:bg-[#d95418] focus-visible:ring-2 focus-visible:ring-[#f26522] focus-visible:ring-offset-2" @click="openMovement()"><ArrowRightLeft :size="18" aria-hidden="true" /> Catat IN/OUT</button>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-6">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#171650] text-white"><Building2 v-if="can.choose_unit" :size="17" aria-hidden="true" /><Warehouse v-else :size="17" aria-hidden="true" /></span>
          <div class="min-w-0"><p class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">Konteks unit</p><p class="truncate text-sm font-semibold text-slate-800">{{ unitContext }}</p></div>
        </div>
      </header>

      <InventoryStats :stats="stats" />

      <nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm" aria-label="Bagian inventori">
        <button v-for="tab in tabs" :key="tab.key" :data-tab="tab.key" type="button" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-4 text-sm font-semibold outline-none transition focus:ring-2 focus:ring-[#2d2a70]" :class="activeTab === tab.key ? 'bg-[#171650] text-white' : 'text-slate-600 hover:bg-slate-50'" :aria-current="activeTab === tab.key ? 'page' : undefined" @click="switchTab(tab.key)">{{ tab.label }} <span class="rounded px-1.5 py-0.5 font-mono text-sm" :class="activeTab === tab.key ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'">{{ tab.count }}</span></button>
      </nav>

      <InventoryFilters :filters="filterState" :categories="categories" :units="units" :show-unit="can.choose_unit" :active-tab="activeTab" :can-reset="hasActiveFilters" @change="changeFilter" @reset="resetFilters" />

      <InventoryTable v-if="activeTab === 'stock'" :stocks="stocks" :show-unit="can.choose_unit" :loading="loading" :error="loadError" :can-reset="hasActiveFilters" :can-record="can.record_movement" @movement="openMovement" @retry="visit()" @reset="resetFilters" @record="openMovement()" />
      <MovementHistory v-else-if="activeTab === 'history'" :movements="movements" :show-unit="can.choose_unit" :loading="loading" :error="loadError" :can-reset="hasActiveFilters" @correct="openCorrection" @retry="visit()" @reset="resetFilters" />

      <section v-else-if="activeTab === 'predictive'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="predictive-assets-title">
        <div class="flex flex-col gap-1 border-b border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div><h2 id="predictive-assets-title" class="text-sm font-semibold text-slate-950">Predictive Data Asset</h2><p class="mt-0.5 text-sm text-slate-500">Nilai rekomendasi backend dengan bukti perbandingan Excel.</p></div>
          <span class="font-mono text-sm uppercase tracking-wider text-slate-500">{{ predictiveAssets.length }} aset</span>
        </div>
        <div v-if="predictiveAssets.length" class="overflow-x-auto">
          <table class="w-full min-w-[1080px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-white text-sm font-semibold uppercase tracking-[0.1em] text-slate-500"><tr><th class="px-5 py-3">Aset</th><th class="px-4 py-3">Kebijakan</th><th class="px-4 py-3 text-right">Stok saat ini</th><th class="px-4 py-3 text-right">Kebutuhan</th><th class="px-4 py-3 text-right">Proposal</th><th class="px-4 py-3 text-right">Safety stock</th><th class="px-4 py-3">Umur</th><th class="px-5 py-3">Audit Excel</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in predictiveAssets" :key="item.asset_id" class="hover:bg-slate-50">
                <td class="px-5 py-3"><p class="font-semibold text-slate-950">{{ item.name }}</p><p class="mt-1 text-sm text-slate-500">{{ item.category.group }} / {{ item.category.system }} / {{ item.category.subsystem }}</p><p v-if="can.choose_unit" class="mt-1 font-mono text-sm text-[#2d2a70]">{{ item.unit?.code }}</p></td>
                <td class="px-4 py-3 text-slate-700">{{ item.inventory_policy }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold" :class="Number(item.current_stock) < 0 ? 'text-red-700' : 'text-slate-800'">{{ predictiveStockLabel(item.current_stock) }}</td>
                <td class="px-4 py-3 text-right font-mono text-slate-800">{{ item.needed_stock }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-[#d95418]">{{ item.proposal_quantity }}</td>
                <td class="px-4 py-3 text-right font-mono text-slate-800">{{ item.final_safety_stock }}</td>
                <td class="px-4 py-3"><p class="text-slate-800">{{ item.age_condition || '—' }}</p><p class="mt-1 text-sm text-slate-500">{{ item.lifetime_status || 'Belum ada data umur' }}</p></td>
                <td class="px-5 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold" :class="item.parity_status === 'matched' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : item.parity_status === 'corrected' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-slate-100 text-slate-600'">{{ parityLabel(item.parity_status) }}</span><p v-if="item.parity_differences" class="mt-1 text-sm text-slate-500">{{ Object.keys(item.parity_differences).length }} nilai berbeda</p></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-12 text-center"><h3 class="text-sm font-semibold text-slate-900">Belum ada data predictive</h3><p class="mt-1 text-sm text-slate-500">Import workbook RAMS untuk menampilkan rekomendasi kebutuhan stok.</p></div>
      </section>

      <section v-else-if="activeTab === 'reconciliation'" data-reconciliation class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="reconciliation-title">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#171650] text-white"><Scale :size="20" aria-hidden="true" /></span>
              <div><h2 id="reconciliation-title" class="text-sm font-semibold text-slate-950">Rekonsiliasi stok Excel dan ledger</h2><p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Perbandingan ini hanya untuk pemeriksaan. Koreksi harus dicatat sebagai transaksi IN/OUT atau koreksi resmi agar jejak audit tetap utuh.</p></div>
            </div>
            <label class="flex min-w-56 flex-col gap-1 text-sm font-semibold text-slate-700">Status
              <select v-model="filterState.reconciliation_status" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium outline-none focus:border-[#2d2a70] focus:ring-2 focus:ring-indigo-100" @change="changeFilter({ key: 'reconciliation_status', value: filterState.reconciliation_status })">
                <option value="all">Semua status</option><option value="matched">Sesuai</option><option value="difference">Ada selisih</option><option value="missing_ledger">Belum ada stok ledger</option><option value="missing_excel">Tidak ada referensi Excel</option><option value="ambiguous">Kecocokan ambigu</option>
              </select>
            </label>
          </div>
        </div>
        <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-5">
          <div class="rounded-lg border border-slate-200 p-3"><p class="text-sm font-medium text-slate-500">Total pembanding</p><p class="mt-1 font-mono text-xl font-bold text-slate-950">{{ reconciliation.stats.total ?? 0 }}</p></div>
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3"><p class="text-sm font-medium text-emerald-700">Sesuai</p><p class="mt-1 font-mono text-xl font-bold text-emerald-900">{{ reconciliation.stats.matched ?? 0 }}</p></div>
          <div class="rounded-lg border border-orange-200 bg-orange-50 p-3"><p class="text-sm font-medium text-orange-700">Ada selisih</p><p class="mt-1 font-mono text-xl font-bold text-orange-900">{{ reconciliation.stats.difference ?? 0 }}</p></div>
          <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-sm font-medium text-red-700">Ledger belum ada</p><p class="mt-1 font-mono text-xl font-bold text-red-900">{{ reconciliation.stats.missing_ledger ?? 0 }}</p></div>
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-3"><p class="text-sm font-medium text-amber-700">Perlu pemeriksaan</p><p class="mt-1 font-mono text-xl font-bold text-amber-900">{{ (reconciliation.stats.missing_excel ?? 0) + (reconciliation.stats.ambiguous ?? 0) }}</p></div>
        </div>
        <div v-if="reconciliation.rows.length" class="overflow-x-auto">
          <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-white font-semibold uppercase tracking-[0.1em] text-slate-500"><tr><th class="px-5 py-3">Aset / suku cadang</th><th class="px-4 py-3">Unit / subsystem</th><th class="px-4 py-3 text-right">Stok Excel</th><th class="px-4 py-3 text-right">Stok ledger</th><th class="px-4 py-3 text-right">Selisih</th><th class="px-5 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in reconciliation.rows" :key="row.id" class="hover:bg-slate-50">
                <td class="px-5 py-3"><p class="font-semibold text-slate-950">{{ row.asset_name || row.part_name || 'Tanpa nama' }}</p><p class="mt-1 font-mono text-sm text-slate-500">{{ row.part_code || 'Belum dipasangkan ke master suku cadang' }}</p></td>
                <td class="px-4 py-3"><p class="font-mono font-semibold text-[#2d2a70]">{{ row.unit?.code || '—' }}</p><p class="mt-1 text-sm text-slate-500">{{ row.category?.subsystem || 'Tanpa subsystem' }}</p></td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-slate-800">{{ row.excel_stock ?? '—' }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-slate-800">{{ row.ledger_stock ?? '—' }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold" :class="row.difference ? 'text-orange-700' : 'text-slate-500'">{{ row.difference === null ? '—' : row.difference === 0 ? '0' : `Selisih ${row.difference}` }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-semibold" :class="reconciliationTone(row.status)"><CheckCircle2 v-if="row.status === 'matched'" :size="15" /><TriangleAlert v-else :size="15" />{{ reconciliationLabel(row.status) }}</span><p v-if="row.status === 'ambiguous'" class="mt-1 text-sm text-slate-500">{{ row.candidate_count }} kandidat perlu dipilih manual</p></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-12 text-center"><Scale :size="34" class="mx-auto text-slate-300" /><h3 class="mt-3 text-sm font-semibold text-slate-900">Belum ada data rekonsiliasi</h3><p class="mt-1 text-sm text-slate-500">Import workbook dan catat stok pembukaan untuk mulai membandingkan.</p></div>
      </section>

      <section v-else-if="activeTab === 'master' && can.manage_master" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="master-parts-title">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3"><div><h2 id="master-parts-title" class="text-sm font-semibold text-slate-950">Master suku cadang global</h2><p class="mt-0.5 text-sm text-slate-500">{{ spareParts.length }} identitas barang dalam hasil filter</p></div><span class="font-mono text-sm uppercase tracking-wider text-slate-400">Pusat</span></div>
        <div v-if="loadError" data-master-error class="m-4 rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
          <div class="flex items-start gap-3 text-red-800"><AlertCircle :size="20" class="mt-0.5 shrink-0" aria-hidden="true" /><div><p class="font-semibold">Data master tidak dapat dimuat</p><p class="mt-1 text-sm">{{ loadError }}</p></div></div>
          <button data-master-retry type="button" class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-semibold text-red-700 outline-none hover:bg-red-100 focus:ring-2 focus:ring-red-600" @click="visit()"><RefreshCw :size="17" aria-hidden="true" /> Coba lagi</button>
        </div>
        <div v-else-if="loading" data-master-loading class="space-y-3 p-4" aria-label="Memuat master suku cadang" aria-busy="true">
          <div v-for="index in 6" :key="index" class="h-14 animate-pulse rounded-lg bg-slate-100 motion-reduce:animate-none" />
        </div>
        <div v-else-if="spareParts.length" data-master-desktop class="hidden overflow-x-auto lg:block">
          <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-200 text-sm font-semibold uppercase tracking-[0.12em] text-slate-500"><tr><th class="px-5 py-3">Kode / nama</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Satuan</th><th class="px-4 py-3 text-right">Safety stock</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100"><tr v-for="part in masterPageParts" :key="part.id" class="hover:bg-slate-50"><td class="px-5 py-3"><p class="font-mono text-sm font-semibold text-[#2d2a70]">{{ part.code }}</p><p class="mt-1 font-semibold text-slate-900">{{ part.detail_equipment }}</p><p v-if="part.equipment" class="mt-0.5 text-sm text-slate-500">{{ part.equipment }}</p></td><td class="max-w-80 px-4 py-3 text-sm leading-5 text-slate-600">{{ masterCategory(part) }}</td><td class="px-4 py-3 text-slate-700">{{ part.unit_of_measure }}</td><td class="px-4 py-3 text-right font-mono tabular-nums text-slate-700">{{ part.safety_stock ?? '—' }}</td><td class="px-4 py-3"><span class="rounded-full border px-2.5 py-1 text-sm font-semibold" :class="part.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600'">{{ part.is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="px-5 py-3 text-right"><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70]" :aria-label="`Ubah suku cadang ${part.detail_equipment}`" @click="openPart(part)"><Pencil :size="16" aria-hidden="true" /> Ubah</button></td></tr></tbody>
          </table>
        </div>
        <div v-if="!loading && !loadError && spareParts.length" data-master-mobile class="space-y-3 bg-slate-50 p-3 lg:hidden">
          <article v-for="part in masterPageParts" :key="part.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-mono text-sm font-semibold text-[#2d2a70]">{{ part.code }}</p><h3 class="mt-1 font-semibold text-slate-950">{{ part.detail_equipment }}</h3><p v-if="part.equipment" class="mt-1 text-sm text-slate-500">{{ part.equipment }}</p></div><span class="shrink-0 rounded-full border px-2.5 py-1 text-sm font-semibold" :class="part.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600'">{{ part.is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
            <dl class="mt-4 grid grid-cols-2 gap-3 border-y border-slate-100 py-3 text-sm"><div class="col-span-2"><dt class="text-slate-500">Kategori</dt><dd class="mt-1 text-slate-700">{{ masterCategory(part) }}</dd></div><div><dt class="text-slate-500">Satuan</dt><dd class="mt-1 font-medium text-slate-800">{{ part.unit_of_measure }}</dd></div><div class="text-right"><dt class="text-slate-500">Safety stock</dt><dd class="mt-1 font-mono text-slate-800">{{ part.safety_stock ?? '—' }}</dd></div></dl>
            <div class="mt-3 flex justify-end"><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70]" :aria-label="`Ubah suku cadang ${part.detail_equipment}`" @click="openPart(part)"><Pencil :size="16" aria-hidden="true" /> Ubah</button></div>
          </article>
        </div>
        <nav v-if="!loading && !loadError && spareParts.length && masterPageCount > 1" data-master-pagination class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3" aria-label="Paginasi master suku cadang"><p class="text-sm text-slate-500">Halaman {{ masterPage }} dari {{ masterPageCount }}</p><div class="flex gap-2"><button type="button" :disabled="masterPage === 1" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70] disabled:cursor-not-allowed disabled:opacity-40" @click="changeMasterPage(masterPage - 1)">Sebelumnya</button><button data-master-next type="button" :disabled="masterPage === masterPageCount" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70] disabled:cursor-not-allowed disabled:opacity-40" @click="changeMasterPage(masterPage + 1)">Berikutnya</button></div></nav>
        <div v-if="!loadError && !loading && !spareParts.length" class="px-5 py-12 text-center"><PackagePlus :size="34" class="mx-auto text-slate-300" aria-hidden="true" /><h3 class="mt-3 text-sm font-semibold text-slate-900">{{ hasActiveFilters ? 'Tidak ada suku cadang sesuai filter' : 'Belum ada master suku cadang' }}</h3><p class="mt-1 text-sm text-slate-500">{{ hasActiveFilters ? 'Hapus filter untuk melihat data master lainnya.' : 'Tambahkan identitas suku cadang pertama untuk mulai mencatat stok.' }}</p><button v-if="hasActiveFilters" data-master-reset type="button" class="mt-4 min-h-11 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70]" @click="resetFilters">Hapus filter master</button><button v-else data-master-create type="button" class="mt-4 min-h-11 rounded-lg bg-[#f26522] px-4 text-sm font-semibold text-white outline-none hover:bg-[#d95418] focus-visible:ring-2 focus-visible:ring-[#f26522] focus-visible:ring-offset-2" @click="openPart()">Tambah suku cadang</button></div>
      </section>
    </div>

    <MovementDialog v-if="movementOpen" :key="movementDialogKey" :open="movementOpen" :spare-parts="spareParts" :stocks="stocks.data" :units="units" :can-choose-unit="can.choose_unit" :initial-part="movementPart" :initial-stock="movementStock" :correction="correctionSource" @close="closeMovement" />
    <SparePartDialog v-if="can.manage_master && partOpen" :key="partDialogKey" :open="partOpen" :part="editedPart" :categories="categories" @close="closePart" />
  </MainLayout>
</template>
