<script setup>
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import {
  AlertTriangle,
  CheckCircle2,
  FileSpreadsheet,
  UploadCloud,
  XCircle,
  Info,
  ChevronDown,
  LoaderCircle,
  RotateCcw,
} from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'

const props = defineProps({
  can_choose_unit: { type: Boolean, default: false },
  selected_unit_id: { type: Number, default: null },
  units: { type: Array, default: () => [] },
  result: { type: Object, default: null },
  history: { type: Array, default: () => [] },
})

const page = usePage()
const assignedUnit = computed(() => page.props.auth?.user?.unit_kerja ?? null)
const form = useForm({
  unit_kerja_id: props.selected_unit_id ?? '',
  workbook: null,
  dry_run: false,
})

const detectedUnitCode = ref(null)
const batchHistory = ref(props.history.map(batch => ({ ...batch })))
const expandedBatchId = ref(null)
const batchDetails = ref({})
const batchDetailLoading = ref({})
const batchDetailErrors = ref({})
const batchIssueTabs = ref({})
const expandedBatch = computed(() => {
  if (expandedBatchId.value === null) return null
  return batchDetails.value[expandedBatchId.value]
    || batchHistory.value.find(batch => batch.id === expandedBatchId.value)
    || null
})
let pollingTimer = null

watch(() => props.history, (history) => {
  batchHistory.value = history.map(batch => ({ ...batch }))
}, { deep: true })

const detectUnitCode = (filename = '') => {
  const normalized = filename.toLowerCase()
  if (/daop\s*1\b/.test(normalized)) return 'DAOP-1'
  if (/daop\s*4\b/.test(normalized)) return 'DAOP-4'
  if (/daop\s*8\b/.test(normalized)) return 'DAOP-8'
  if (/divre\s*iii\b/.test(normalized)) return 'DIVRE-III'
  if (/divre\s*iv\b/.test(normalized)) return 'DIVRE-IV'
  return null
}

const selectWorkbook = (event) => {
  form.workbook = event.target.files?.[0] ?? null
  detectedUnitCode.value = form.workbook ? detectUnitCode(form.workbook.name) : null
  if (props.can_choose_unit && detectedUnitCode.value) {
    const detectedUnit = props.units.find(unit => unit.code === detectedUnitCode.value)
    if (detectedUnit) form.unit_kerja_id = String(detectedUnit.id)
  }
}

const submit = () => {
  form.post('/trouble-report/import', {
    forceFormData: true,
    preserveScroll: true,
  })
}

const counters = computed(() => props.result ? [
  { key: 'assets-created', label: 'Aset Dibuat', help: 'Jumlah aset baru yang berhasil ditambahkan dari workbook.', value: props.result.master_assets_created ?? 0, tone: 'text-fuchsia-700 bg-fuchsia-50 border-fuchsia-100' },
  { key: 'assets-updated', label: 'Aset Diperbarui', help: 'Jumlah aset yang sudah ada lalu datanya disesuaikan dari workbook.', value: props.result.master_assets_updated ?? 0, tone: 'text-pink-700 bg-pink-50 border-pink-100' },
  { key: 'risk-register-created', label: 'Risk Register Baru', help: 'Jumlah daftar risiko baru yang ditambahkan ke sistem.', value: props.result.risk_registers_created ?? 0, tone: 'text-rose-700 bg-rose-50 border-rose-100' },
  { key: 'risk-register-updated', label: 'Risk Register Diperbarui', help: 'Jumlah daftar risiko yang sudah ada lalu diperbarui dari workbook.', value: props.result.risk_registers_updated ?? 0, tone: 'text-red-700 bg-red-50 border-red-100' },
  { key: 'spare-parts-created', label: 'Suku Cadang Baru', help: 'Jumlah data suku cadang baru yang ditambahkan ke sistem.', value: props.result.spare_parts_created ?? 0, tone: 'text-teal-700 bg-teal-50 border-teal-100' },
  { key: 'spare-parts-updated', label: 'Suku Cadang Diperbarui', help: 'Jumlah data suku cadang yang sudah ada lalu diperbarui.', value: props.result.spare_parts_updated ?? 0, tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { key: 'sheets-read', label: 'Sheet terbaca', help: 'Jumlah sheet Excel yang berhasil dibaca oleh sistem.', value: props.result.sheets ?? 0, tone: 'text-blue-700 bg-blue-50 border-blue-100' },
  { key: 'reliability-snapshots', label: 'Snapshot Excel', help: 'Jumlah ringkasan reliability dari Excel yang disimpan sebagai rekaman import.', value: props.result.snapshots ?? 0, tone: 'text-cyan-700 bg-cyan-50 border-cyan-100' },
  { key: 'logs-created', label: 'Log Dibuat', help: 'Jumlah laporan gangguan baru yang masuk ke sistem.', value: props.result.created ?? 0, tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { key: 'logs-updated', label: 'Log Diperbarui', help: 'Jumlah laporan gangguan yang sudah ada lalu diperbarui.', value: props.result.updated ?? 0, tone: 'text-indigo-700 bg-indigo-50 border-indigo-100' },
  { key: 'logs-unchanged', label: 'Log Tetap', help: 'Jumlah laporan gangguan yang sama persis sehingga tidak perlu diubah.', value: props.result.unchanged ?? 0, tone: 'text-slate-700 bg-slate-50 border-slate-200' },
  { key: 'logs-skipped', label: 'Log Dilewati', help: 'Jumlah baris laporan gangguan yang tidak dimasukkan karena memiliki masalah atau tidak memenuhi aturan import.', value: props.result.skipped ?? 0, tone: 'text-amber-700 bg-amber-50 border-amber-100' },
  { key: 'parity-calculated', label: 'Parity dihitung', help: 'Jumlah ringkasan reliability yang dibandingkan antara hasil sistem dan Excel.', value: props.result.parity?.calculated ?? 0, tone: 'text-violet-700 bg-violet-50 border-violet-100' },
  { key: 'parity-matched', label: 'Sesuai Excel', help: 'Jumlah hasil perbandingan yang sama atau masih dalam batas toleransi dengan Excel.', value: props.result.parity?.matched ?? 0, tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { key: 'parity-mismatch', label: 'Ada selisih', help: 'Jumlah hasil perbandingan yang berbeda dari Excel dan perlu diperiksa.', value: props.result.parity?.mismatch ?? 0, tone: 'text-orange-700 bg-orange-50 border-orange-100' },
] : [])

const openCounterHelp = ref(null)
const toggleCounterHelp = (key) => {
  openCounterHelp.value = openCounterHelp.value === key ? null : key
}

const activeTab = ref('all')

const categorizedIssues = computed(() => {
  const issues = props.result?.issues || []
  return {
    all: issues,
    error: issues.filter(i => i.severity === 'error'),
    warning: issues.filter(i => i.severity === 'warning'),
    info: issues.filter(i => i.severity === 'info'),
  }
})

const formatDate = (value) => value
  ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
  : '-'

const formatFileSize = (bytes) => `${Math.max(0, Number(bytes || 0) / 1024 / 1024).toFixed(2)} MB`

const isActiveBatch = (batch) => ['queued', 'processing'].includes(batch.status)
const statusLabel = (status) => ({
  queued: 'Menunggu', processing: 'Diproses', succeeded: 'Selesai', failed: 'Gagal', rolled_back: 'Dibatalkan',
}[status] || status)

const detailMetrics = (batch) => {
  const summary = batch?.summary || {}
  return [
    { key: `detail-assets-created-${batch?.id}`, label: 'Master aset dibuat', help: 'Jumlah aset baru yang berhasil ditambahkan dari workbook.', value: summary.master_assets_created ?? 0 },
    { key: `detail-assets-updated-${batch?.id}`, label: 'Master aset diperbarui', help: 'Jumlah aset yang sudah ada lalu datanya disesuaikan dari workbook.', value: summary.master_assets_updated ?? 0 },
    { key: `detail-risk-matrix-created-${batch?.id}`, label: 'Risk Matrix dibuat', help: 'Jumlah data matriks risiko baru yang dibuat dari workbook.', value: summary.risk_matrices_created ?? 0 },
    { key: `detail-colors-updated-${batch?.id}`, label: 'Penyesuaian warna aset', help: 'Jumlah kategori, system, atau subsystem yang warnanya disesuaikan mengikuti warna dari Excel.', value: summary.dashboard_colors_updated ?? 0 },
    { key: `detail-risk-register-created-${batch?.id}`, label: 'Risk Register dibuat', help: 'Jumlah daftar risiko baru yang ditambahkan ke sistem.', value: summary.risk_registers_created ?? 0 },
    { key: `detail-risk-register-updated-${batch?.id}`, label: 'Risk Register diperbarui', help: 'Jumlah daftar risiko yang sudah ada lalu diperbarui dari workbook.', value: summary.risk_registers_updated ?? 0 },
    { key: `detail-spare-parts-created-${batch?.id}`, label: 'Suku cadang dibuat', help: 'Jumlah data suku cadang baru yang ditambahkan ke sistem.', value: summary.spare_parts_created ?? 0 },
    { key: `detail-spare-parts-updated-${batch?.id}`, label: 'Suku cadang diperbarui', help: 'Jumlah data suku cadang yang sudah ada lalu diperbarui.', value: summary.spare_parts_updated ?? 0 },
    { key: `detail-logs-created-${batch?.id}`, label: 'Trouble Report dibuat', help: 'Jumlah laporan gangguan baru yang masuk ke sistem.', value: summary.created ?? 0 },
    { key: `detail-reliability-snapshots-${batch?.id}`, label: 'Snapshot reliability', help: 'Jumlah ringkasan reliability dari Excel yang disimpan sebagai rekaman import.', value: summary.snapshots ?? 0 },
    { key: `detail-parity-matched-${batch?.id}`, label: 'Parity sesuai Excel', help: 'Jumlah hasil perbandingan yang sama atau masih dalam batas toleransi dengan Excel.', value: summary.parity?.matched ?? 0 },
    { key: `detail-parity-mismatch-${batch?.id}`, label: 'Parity berbeda', help: 'Jumlah hasil perbandingan yang berbeda dari Excel dan perlu diperiksa.', value: summary.parity?.mismatch ?? 0 },
  ]
}

const issueTabOptions = (issues = []) => {
  const counts = {
    error: issues.filter(issue => issue.severity === 'error').length,
    warning: issues.filter(issue => issue.severity === 'warning').length,
    info: issues.filter(issue => issue.severity === 'info').length,
  }

  return [
    { key: 'all', label: 'Semua', count: issues.length },
    { key: 'error', label: 'Error', count: counts.error },
    { key: 'warning', label: 'Peringatan', count: counts.warning },
    { key: 'info', label: 'Informasi', count: counts.info },
  ].filter(tab => tab.key === 'all' || tab.count > 0)
}

const visibleIssues = (batch) => {
  const issues = batch?.issues || []
  const activeTab = batchIssueTabs.value[batch?.id] || 'all'
  return activeTab === 'all' ? issues : issues.filter(issue => issue.severity === activeTab)
}

const setIssueTab = (batchId, tab) => {
  batchIssueTabs.value = { ...batchIssueTabs.value, [batchId]: tab }
}

const severityLabel = (severity) => ({
  error: 'Error',
  warning: 'Peringatan',
  info: 'Informasi',
}[severity] || 'Masalah')

const fetchBatch = async (id) => {
  batchDetailLoading.value = { ...batchDetailLoading.value, [id]: true }
  batchDetailErrors.value = { ...batchDetailErrors.value, [id]: null }

  try {
    const response = await fetch(`/trouble-report/import/batch/${id}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
    if (!response.ok) throw new Error('Rincian batch tidak dapat dimuat.')
    const payload = (await response.json()).data
    batchDetails.value = { ...batchDetails.value, [id]: payload }
    const index = batchHistory.value.findIndex(batch => batch.id === id)
    if (index >= 0) batchHistory.value[index] = { ...batchHistory.value[index], ...payload }
  } catch (error) {
    batchDetailErrors.value = {
      ...batchDetailErrors.value,
      [id]: error instanceof Error ? error.message : 'Rincian batch tidak dapat dimuat.',
    }
  } finally {
    batchDetailLoading.value = { ...batchDetailLoading.value, [id]: false }
  }
}

const toggleBatch = async (batch) => {
  expandedBatchId.value = expandedBatchId.value === batch.id ? null : batch.id
  if (expandedBatchId.value === batch.id) await fetchBatch(batch.id)
}

const rollbackBatch = (batch) => {
  if (!window.confirm(`Batalkan seluruh perubahan aman dari batch #${batch.id}? Tindakan ini hanya berjalan bila data belum berubah.`)) return
  router.post(`/trouble-report/import/batch/${batch.id}/rollback`, {}, {
    preserveScroll: true,
    onSuccess: () => { expandedBatchId.value = null },
  })
}

const pollActiveBatches = () => {
  batchHistory.value.filter(isActiveBatch).forEach(batch => fetchBatch(batch.id))
}

onMounted(() => {
  if (import.meta.env.MODE !== 'test' && batchHistory.value.some(isActiveBatch)) {
    pollingTimer = window.setInterval(pollActiveBatches, 2500)
  }
})

onBeforeUnmount(() => {
  if (pollingTimer) window.clearInterval(pollingTimer)
})
</script>

<template>
  <Head title="Import Data RAMS" />
  <MainLayout>
    <div class="mx-auto max-w-6xl space-y-6">
      <header>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orange-600">Input Data</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Import Data RAMS</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
          Unggah workbook RAMS berformat .xlsm atau .xlsx. Sistem membaca master aset dan predictive, matriks serta register risiko, Reorder Stock, Trouble Report, snapshot reliability, lalu menghitung parity formula backend. Import tidak pernah menambah atau mengubah akun pengguna.
        </p>
      </header>

      <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#171650] text-white">
              <UploadCloud :size="21" aria-hidden="true" />
            </span>
            <div>
              <h3 class="font-semibold text-slate-950">Pilih workbook dan unit tujuan</h3>
              <p class="text-xs text-slate-500">Ukuran maksimal 50 MB.</p>
            </div>
          </div>
        </div>

        <form class="space-y-5 p-5" enctype="multipart/form-data" @submit.prevent="submit">
          <div v-if="can_choose_unit">
            <label for="import-unit" class="mb-2 block text-sm font-semibold text-slate-800">Unit kerja tujuan <span class="text-red-600">*</span></label>
            <select
              id="import-unit"
              v-model="form.unit_kerja_id"
              class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
              :aria-invalid="Boolean(form.errors.unit_kerja_id)"
            >
              <option value="" disabled>Pilih unit kerja</option>
              <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">
                {{ unit.code }} — {{ unit.name }}
              </option>
            </select>
            <p v-if="form.errors.unit_kerja_id" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.unit_kerja_id }}</p>
          </div>

          <div v-else class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
            <p class="text-xs font-medium uppercase tracking-wide text-blue-600">Unit tujuan akun</p>
            <p class="mt-1 text-sm font-semibold text-blue-950">
              {{ assignedUnit?.code || 'Unit kerja' }}<span v-if="assignedUnit?.name"> — {{ assignedUnit.name }}</span>
            </p>
          </div>

          <div>
            <label for="import-workbook" class="mb-2 block text-sm font-semibold text-slate-800">File workbook <span class="text-red-600">*</span></label>
            <input
              id="import-workbook"
              type="file"
              accept=".xlsm,.xlsx,application/vnd.ms-excel.sheet.macroEnabled.12,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-4 file:border-0 file:bg-slate-100 file:px-4 file:py-3 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-orange-100"
              :aria-invalid="Boolean(form.errors.workbook)"
              @change="selectWorkbook"
            />
            <p v-if="form.errors.workbook" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.workbook }}</p>
            <div v-if="detectedUnitCode" class="mt-2 flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">
              <CheckCircle2 :size="15" aria-hidden="true" /> Terdeteksi otomatis: {{ detectedUnitCode }}
            </div>
            <p v-else-if="form.workbook && can_choose_unit" class="mt-2 text-xs text-amber-700">Nama file tidak memuat DAOP/DIVRE yang dikenali. Pastikan unit dipilih manual.</p>
          </div>

          <div class="flex items-center gap-2">
            <input
              id="dry-run"
              type="checkbox"
              v-model="form.dry_run"
              class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-600"
            />
            <label for="dry-run" class="text-sm font-medium text-slate-800">
              Hanya Simulasi (Dry Run) <span class="font-normal text-slate-500">— Hanya mengecek error dan selisih tanpa menyimpan data</span>
            </label>
          </div>

          <div v-if="form.progress" class="space-y-2" aria-live="polite">
            <div class="flex items-center justify-between text-xs font-medium text-slate-600">
              <span>Mengunggah workbook</span>
              <span>{{ form.progress.percentage }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-orange-500 transition-all" :style="{ width: `${form.progress.percentage}%` }" />
            </div>
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-orange-600 px-5 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <UploadCloud :size="17" aria-hidden="true" />
              {{ form.processing ? 'Sedang mengimpor…' : 'Import Data RAMS' }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="result" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-live="polite">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div class="flex items-start gap-3">
            <CheckCircle2 v-if="result.status === 'succeeded'" class="mt-0.5 text-emerald-600" :size="21" aria-hidden="true" />
            <AlertTriangle v-else class="mt-0.5 text-red-600" :size="21" aria-hidden="true" />
            <div>
              <h3 class="font-semibold text-slate-950 flex items-center gap-2">
                Hasil impor
                <span v-if="result.dry_run" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 border border-slate-300">Mode Simulasi</span>
              </h3>
              <p class="mt-1 text-xs text-slate-500">{{ result.workbook }} · {{ result.unit?.code }}</p>
            </div>
          </div>
          <span
            class="rounded-full px-3 py-1 text-xs font-semibold"
            :class="result.status === 'succeeded' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
          >
            {{ result.status === 'succeeded' ? 'Selesai' : 'Gagal' }}
          </span>
        </div>

        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="counter in counters" :key="counter.key" class="relative rounded-lg border p-4" :class="counter.tone">
            <div class="flex items-start justify-between gap-2">
              <p class="text-xs font-medium">{{ counter.label }}</p>
              <button
                :data-counter-help="counter.key"
                type="button"
                class="shrink-0 rounded-full p-0.5 opacity-70 transition hover:bg-black/5 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1"
                :aria-label="`Apa arti ${counter.label}?`"
                :aria-expanded="openCounterHelp === counter.key"
                :aria-controls="`counter-help-${counter.key}`"
                @click="toggleCounterHelp(counter.key)"
                @keydown.esc="openCounterHelp = null"
              >
                <Info :size="15" aria-hidden="true" />
              </button>
            </div>
            <p class="mt-1 text-2xl font-bold">{{ counter.value }}</p>
            <div v-if="openCounterHelp === counter.key" :id="`counter-help-${counter.key}`" class="absolute right-3 top-12 z-20 w-64 rounded-lg border border-slate-200 bg-white p-3 text-left text-xs leading-5 text-slate-700 shadow-lg">
              {{ counter.help }}
            </div>
          </div>
        </div>

        <div v-if="result.issues?.length" class="border-t border-slate-200 bg-slate-50">
          <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-3">
            <div class="flex items-center gap-4">
              <span class="text-sm font-semibold text-slate-900">Daftar masalah ({{ result.issues.length }})</span>
              <div class="flex flex-wrap gap-2">
                <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-slate-200 text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-3 py-1 text-xs font-semibold transition">Semua</button>
                <button type="button" v-if="categorizedIssues.error.length" @click="activeTab = 'error'" :class="activeTab === 'error' ? 'bg-red-200 text-red-900 shadow-sm' : 'text-red-600 hover:bg-red-100'" class="rounded-md px-3 py-1 text-xs font-semibold transition">Error ({{ categorizedIssues.error.length }})</button>
                <button type="button" v-if="categorizedIssues.warning.length" @click="activeTab = 'warning'" :class="activeTab === 'warning' ? 'bg-amber-200 text-amber-900 shadow-sm' : 'text-amber-700 hover:bg-amber-100'" class="rounded-md px-3 py-1 text-xs font-semibold transition">Warning ({{ categorizedIssues.warning.length }})</button>
                <button type="button" v-if="categorizedIssues.info.length" @click="activeTab = 'info'" :class="activeTab === 'info' ? 'bg-blue-200 text-blue-900 shadow-sm' : 'text-blue-700 hover:bg-blue-100'" class="rounded-md px-3 py-1 text-xs font-semibold transition">Info ({{ categorizedIssues.info.length }})</button>
              </div>
            </div>
          </div>
          
          <div class="max-h-[500px] overflow-y-auto bg-white">
            <ul class="divide-y divide-slate-100">
              <li v-for="(issue, index) in categorizedIssues[activeTab]" :key="`${issue.sheet_name}-${issue.source_row}-${index}`" class="flex gap-4 px-5 py-4 transition-colors" :class="{
                'bg-red-50/50 hover:bg-red-50': issue.severity === 'error',
                'bg-amber-50/50 hover:bg-amber-50': issue.severity === 'warning',
                'bg-blue-50/50 hover:bg-blue-50': issue.severity === 'info'
              }">
                <XCircle v-if="issue.severity === 'error'" class="mt-0.5 shrink-0 text-red-600" :size="18" aria-hidden="true" />
                <AlertTriangle v-else-if="issue.severity === 'warning'" class="mt-0.5 shrink-0 text-amber-600" :size="18" aria-hidden="true" />
                <Info v-else class="mt-0.5 shrink-0 text-blue-600" :size="18" aria-hidden="true" />
                
                <div class="min-w-0">
                  <p class="text-sm font-semibold" :class="{
                    'text-red-900': issue.severity === 'error',
                    'text-amber-900': issue.severity === 'warning',
                    'text-blue-900': issue.severity === 'info'
                  }">{{ issue.message }}</p>
                  <p class="mt-1 text-xs font-medium" :class="{
                    'text-red-700': issue.severity === 'error',
                    'text-amber-700': issue.severity === 'warning',
                    'text-blue-700': issue.severity === 'info'
                  }">
                    {{ issue.sheet_name || 'Workbook' }}
                    <template v-if="issue.source_row"> · Baris {{ issue.source_row }}</template>
                    <template v-if="issue.source_column"> · Kolom {{ issue.source_column }}</template>
                  </p>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div v-else class="flex items-center gap-2 border-t border-slate-200 px-5 py-4 text-sm text-emerald-700">
          <FileSpreadsheet :size="17" aria-hidden="true" />
          Tidak ada masalah pada baris yang diimpor.
        </div>
      </section>

      <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
          <div>
            <h3 class="font-semibold text-slate-950">Riwayat import</h3>
            <p class="mt-1 text-xs text-slate-500">25 import terbaru yang boleh diakses akun ini.</p>
          </div>
          <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">{{ batchHistory.length }} batch</span>
        </div>

        <div v-if="batchHistory.length" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-white text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3 font-semibold">Workbook</th>
                <th class="px-5 py-3 font-semibold">Unit</th>
                <th class="px-5 py-3 font-semibold">Pengunggah</th>
                <th class="px-5 py-3 font-semibold">Waktu</th>
                <th class="px-5 py-3 font-semibold">Status</th>
                <th class="px-5 py-3 text-right font-semibold">Masalah</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-for="batch in batchHistory" :key="batch.id">
              <tr class="hover:bg-slate-50/80">
                <td class="px-5 py-3">
                  <p class="max-w-xs truncate font-semibold text-slate-900" :title="batch.workbook_name">{{ batch.workbook_name }}</p>
                  <p class="mt-0.5 text-xs text-slate-500">{{ formatFileSize(batch.file_size) }}<span v-if="batch.dry_run"> · Simulasi</span></p>
                </td>
                <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-700">{{ batch.unit?.code || '-' }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ batch.uploaded_by?.name || 'Proses sistem/CLI' }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ formatDate(batch.started_at) }}</td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <LoaderCircle v-if="isActiveBatch(batch)" :size="14" class="animate-spin text-amber-600" />
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="batch.status === 'succeeded' ? 'bg-emerald-50 text-emerald-700' : batch.status === 'failed' ? 'bg-red-50 text-red-700' : batch.status === 'rolled_back' ? 'bg-slate-100 text-slate-700' : 'bg-amber-50 text-amber-700'">
                      {{ statusLabel(batch.status) }}
                    </span>
                    <button :data-batch-detail="batch.id" type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-orange-700" @click="toggleBatch(batch)">
                      Rincian <ChevronDown :size="13" :class="expandedBatchId === batch.id ? 'rotate-180' : ''" class="transition-transform" />
                    </button>
                  </div>
                  <div v-if="isActiveBatch(batch)" class="mt-2 min-w-52">
                    <div class="mb-1 flex justify-between gap-3 text-[11px] text-slate-500"><span>{{ batch.progress_stage }}</span><span>{{ batch.progress_percent }}%</span></div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500 transition-all" :style="{ width: `${batch.progress_percent}%` }" /></div>
                  </div>
                </td>
                <td class="whitespace-nowrap px-5 py-3 text-right">
                  <button
                    v-if="batch.issues_count"
                    :data-batch-issues="batch.id"
                    type="button"
                    class="font-semibold text-orange-700 hover:text-orange-800"
                    @click="toggleBatch(batch)"
                  >
                    {{ batch.issues_count }} masalah
                  </button>
                  <span v-else class="text-slate-400">0</span>
                </td>
              </tr>
              <tr v-if="expandedBatchId === batch.id" :data-batch-detail-row="batch.id">
                <td colspan="6" class="bg-slate-50 px-5 py-5">
                  <div v-if="batchDetailLoading[batch.id]" class="flex items-center gap-2 text-sm text-slate-600" aria-live="polite">
                    <LoaderCircle :size="17" class="animate-spin text-orange-600" aria-hidden="true" />
                    Memuat rincian import dan daftar masalah…
                  </div>
                  <div v-else-if="batchDetailErrors[batch.id]" class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ batchDetailErrors[batch.id] }} Silakan tutup rincian lalu buka kembali untuk mencoba lagi.
                  </div>
                  <template v-else-if="expandedBatch">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <p class="text-sm font-bold text-slate-950">Rincian batch #{{ expandedBatch.id }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ expandedBatch.workbook_name }} · {{ expandedBatch.unit?.code || '-' }}</p>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-500">{{ expandedBatch.progress_stage }}</span>
                        <button v-if="expandedBatch.can_rollback" :data-batch-rollback="expandedBatch.id" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50" @click="rollbackBatch(expandedBatch)">
                          <RotateCcw :size="14" /> Rollback aman
                        </button>
                      </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                      <div v-for="metric in detailMetrics(expandedBatch)" :key="metric.key" class="relative rounded-lg border border-slate-200 bg-white px-3 py-3">
                        <div class="flex items-start justify-between gap-2">
                          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ metric.label }}</p>
                          <button
                            :data-metric-help="metric.key"
                            type="button"
                            class="shrink-0 rounded-full p-0.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1"
                            :aria-label="`Apa arti ${metric.label}?`"
                            :aria-expanded="openCounterHelp === metric.key"
                            :aria-controls="`metric-help-${metric.key}`"
                            @click="toggleCounterHelp(metric.key)"
                            @keydown.esc="openCounterHelp = null"
                          >
                            <Info :size="15" aria-hidden="true" />
                          </button>
                        </div>
                        <p class="mt-1 text-xl font-bold text-slate-950">{{ metric.value }}</p>
                        <div v-if="openCounterHelp === metric.key" :id="`metric-help-${metric.key}`" class="absolute right-3 top-10 z-20 w-64 rounded-lg border border-slate-200 bg-white p-3 text-left text-xs leading-5 text-slate-700 shadow-lg">
                          {{ metric.help }}
                        </div>
                      </div>
                    </div>

                    <div v-if="expandedBatch.issues?.length" class="mt-5 overflow-hidden rounded-lg border border-slate-200 bg-white">
                      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <div>
                          <h4 class="text-sm font-bold text-slate-950">Masalah yang ditemukan</h4>
                          <p class="mt-0.5 text-xs text-slate-500">Telusuri langsung dari workbook yang diproses.</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Filter masalah import">
                          <button
                            v-for="tab in issueTabOptions(expandedBatch.issues)"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="(batchIssueTabs[expandedBatch.id] || 'all') === tab.key"
                            class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                            :class="(batchIssueTabs[expandedBatch.id] || 'all') === tab.key ? 'bg-slate-200 text-slate-950' : 'text-slate-600 hover:bg-slate-100'"
                            @click="setIssueTab(expandedBatch.id, tab.key)"
                          >
                            {{ tab.label }} ({{ tab.count }})
                          </button>
                        </div>
                      </div>

                      <ul class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto" :aria-label="`Daftar masalah batch ${expandedBatch.id}`">
                        <li v-for="(issue, issueIndex) in visibleIssues(expandedBatch)" :key="`${issue.id || issue.source_row || 'issue'}-${issueIndex}`" :data-import-issue="issue.id || issueIndex" class="flex gap-3 px-4 py-3">
                          <XCircle v-if="issue.severity === 'error'" class="mt-0.5 shrink-0 text-red-600" :size="17" aria-hidden="true" />
                          <AlertTriangle v-else-if="issue.severity === 'warning'" class="mt-0.5 shrink-0 text-amber-600" :size="17" aria-hidden="true" />
                          <Info v-else class="mt-0.5 shrink-0 text-blue-600" :size="17" aria-hidden="true" />
                          <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                              <p class="text-sm font-semibold text-slate-900">{{ issue.message }}</p>
                              <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide" :class="issue.severity === 'error' ? 'bg-red-50 text-red-700' : issue.severity === 'warning' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'">
                                {{ severityLabel(issue.severity) }}
                              </span>
                            </div>
                            <p class="mt-1 text-xs font-medium text-slate-500">
                              {{ issue.sheet_name || 'Workbook' }}
                              <template v-if="issue.source_row"> · Baris {{ issue.source_row }}</template>
                              <template v-if="issue.source_column"> · Kolom {{ issue.source_column }}</template>
                            </p>
                          </div>
                        </li>
                      </ul>
                    </div>
                    <div v-else class="mt-5 flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                      <FileSpreadsheet :size="17" aria-hidden="true" />
                      Tidak ada masalah pada batch ini.
                    </div>

                    <p v-if="expandedBatch.error_message" class="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">{{ expandedBatch.error_message }}</p>
                    <p v-if="!expandedBatch.can_rollback && expandedBatch.rollback_unavailable_reason" class="mt-3 text-xs text-slate-500">Rollback tidak tersedia: {{ expandedBatch.rollback_unavailable_reason }}</p>
                  </template>
                </td>
              </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div v-else class="px-5 py-10 text-center text-sm text-slate-500">Belum ada riwayat import.</div>
      </section>
    </div>
  </MainLayout>
</template>
