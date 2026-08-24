<template>
  <MainLayout>
    <div class="space-y-6 pb-10">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 pb-5 gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold tracking-widest uppercase">Subsystem</span>
          </div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">{{ subsystemName }}</h2>
          <p class="text-sm text-slate-500 mt-1">Formulir Laporan Gangguan (Trouble Report) dan Ringkasan Keandalan</p>
        </div>
        <div class="flex items-center gap-3">
          <BaseButton variant="secondary" @click="backToDashboard">Kembali</BaseButton>
          <BaseButton :disabled="assets.length === 0" variant="primary" @click="isModalOpen = true" class="shadow-md shadow-blue-500/20 flex items-center gap-2">
            <PlusIcon class="w-4 h-4" /> Input Manual
          </BaseButton>
        </div>
      </div>

      <section class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3" aria-label="Identitas subsystem">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Wilayah</p>
          <p class="mt-1 text-sm font-semibold text-slate-900">{{ selectedAreaLabel }}</p>
        </div>
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Jumlah unit</p>
          <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900">{{ formatNumber(totalUnits) }}</p>
        </div>
        <div>
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                <CalendarDaysIcon class="h-3.5 w-3.5" aria-hidden="true" />
                Tanggal pemasangan equipment
                <span class="group relative inline-flex normal-case tracking-normal">
                  <button
                    type="button"
                    data-calculation-baseline-info
                    aria-label="Informasi dasar perhitungan RAMS"
                    aria-describedby="calculation-baseline-tooltip"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                  >
                    <InfoIcon class="h-3.5 w-3.5" aria-hidden="true" />
                  </button>
                  <span
                    id="calculation-baseline-tooltip"
                    role="tooltip"
                    class="pointer-events-none invisible absolute right-0 top-full z-20 mt-2 w-72 max-w-[calc(100vw-3rem)] rounded-lg bg-slate-900 px-3 py-2.5 text-left text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
                  >
                    Reliability dan availability memakai baseline wilayah dari Dashboard Excel: <strong>{{ calculationBaselineLabel }}</strong>. Tanggal pemasangan equipment hanya informasi aset.
                  </span>
                </span>
              </p>
              <p class="mt-1 text-sm font-semibold text-slate-900">{{ installationDateLabel }}</p>
              <p v-if="installationDates.length > 1" class="mt-1 text-xs leading-5 text-amber-700">Terdapat lebih dari satu tanggal pada equipment di subsystem ini.</p>
            </div>
            <button
              v-if="assets.length"
              data-edit-installation-date
              type="button"
              class="shrink-0 text-xs font-semibold text-blue-700 hover:text-blue-900"
              @click="openInstallationDateEditor"
            >
              Ubah tanggal
            </button>
          </div>

          <div v-if="isInstallationDateEditorOpen" class="mt-3 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div v-for="asset in assets" :key="asset.id" class="space-y-1.5">
              <label :for="`installation-date-${asset.id}`" class="block text-xs font-semibold text-slate-700">
                {{ asset.nama_aset || subsystemName }}
              </label>
              <div class="flex flex-wrap gap-2">
                <input
                  :id="`installation-date-${asset.id}`"
                  v-model="installationDateDrafts[asset.id]"
                  :data-installation-date-input="asset.id"
                  type="date"
                  :max="today"
                  class="h-10 min-w-44 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
                >
                <button
                  type="button"
                  :data-save-installation-date="asset.id"
                  class="h-10 rounded-lg bg-[#171650] px-4 text-sm font-semibold text-white hover:bg-[#24236a]"
                  @click="saveInstallationDate(asset)"
                >
                  Simpan
                </button>
              </div>
              <p v-if="installationDateErrors[asset.id]" class="text-xs text-red-600" role="alert">{{ installationDateErrors[asset.id] }}</p>
            </div>
            <button type="button" class="text-xs font-semibold text-slate-600 hover:text-slate-900" @click="isInstallationDateEditorOpen = false">Batal</button>
          </div>
        </div>
      </section>

      <!-- Tabel Ringkasan (Biru - Modern) -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-[#4A72B2] to-[#3a5a8f] px-4 py-3 border-b border-slate-200">
          <h3 class="text-white font-bold text-sm flex items-center gap-2">
            <ActivityIcon class="w-4 h-4 text-white/80" />
            Ringkasan Keandalan (Reliability Data)
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="p-3 font-semibold min-w-[150px]">Subsystem</th>
                <th class="p-3 font-semibold text-center">Unit</th>
                <th class="p-3 font-semibold text-center">Opr. Hour</th>
                <th class="p-3 font-semibold text-center">Uptime</th>
                <th class="p-3 font-semibold text-center">Downtime</th>
                <th class="p-3 font-semibold text-center">Failure</th>
                <th class="p-3 font-semibold text-center">MTTF</th>
                <th class="p-3 font-semibold text-center">MTBF</th>
                <th class="p-3 font-semibold text-center">Fail Rate &lambda;</th>
                <th class="p-3 font-semibold text-center">Reliability</th>
                <th class="p-3 font-semibold text-center">Availability</th>
                <th class="p-3 font-semibold text-center">Ganti Sparepart</th>
                <th class="p-3 font-semibold text-center">Vandalisme</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="summaryData" class="hover:bg-slate-50 transition-colors">
                <td class="p-3 font-semibold text-slate-700">{{ subsystemName }}</td>
                <td class="p-3 text-center">{{ formatNumber(summaryData.jumlah_unit) }}</td>
                <td class="p-3 text-center">{{ formatNumber(summaryData.total_operating_hour) }}</td>
                <td class="p-3 text-center text-emerald-600 font-medium">{{ formatNumber(summaryData.total_uptime) }}</td>
                <td class="p-3 text-center text-rose-600 font-medium">{{ formatNumber(summaryData.total_downtime) }}</td>
                <td class="p-3 text-center font-bold">{{ formatNumber(summaryData.jumlah_failure) }}</td>
                <td class="p-3 text-center">{{ formatNumber(summaryData.mttf) }}</td>
                <td class="p-3 text-center font-medium">{{ formatNumber(summaryData.mtbf) }}</td>
                <td class="p-3 text-center">{{ formatDecimal(summaryData.failure_rate) }}</td>
                <td class="p-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                    {{ formatPercent(summaryData.reliability) }}
                  </span>
                </td>
                <td class="p-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                    {{ formatPercent(summaryData.availability) }}
                  </span>
                </td>
                <td class="p-3 text-center bg-orange-50 font-bold text-orange-600 border-l border-r border-orange-100">{{ formatNumber(summaryData.spare_part_replacement_count) }}</td>
                <td class="p-3 text-center font-bold text-rose-600">{{ formatNumber(summaryData.vandalism_count) }}</td>
              </tr>
              <tr v-else>
                <td colspan="13" class="p-8 text-center text-slate-400">Memuat data ringkasan...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tabel Input (Ungu - Modern) -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mt-8">
        <div class="bg-gradient-to-r from-[#7030A0] to-[#582182] px-4 py-3 border-b border-slate-200">
          <h3 class="text-white font-bold text-sm flex items-center gap-2">
            <AlertTriangleIcon class="w-4 h-4 text-white/80" />
            Log Kejadian Kegagalan (Failure Report)
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
              <tr>
                <th class="p-3 font-semibold min-w-[120px]">Lokasi</th>
                <th class="p-3 font-semibold min-w-[120px]">Resor</th>
                <th class="p-3 font-semibold">QC</th>
                <th class="p-3 font-semibold min-w-[200px]">Failure Event</th>
                <th class="p-3 font-semibold min-w-[200px]">Penyebab</th>
                <th class="p-3 font-semibold min-w-[200px]">Tindakan</th>
                <th class="p-3 font-semibold text-center">Ganti Sparepart</th>
                <th class="p-3 font-semibold text-center">Vandalisme</th>
                <th class="p-3 font-semibold min-w-[140px]">Tgl Jam Kejadian</th>
                <th class="p-3 font-semibold min-w-[140px]">Tgl Jam Penanganan</th>
                <th class="p-3 font-semibold text-center">Downtime<br>(hh:mm)</th>
                <th class="p-3 font-semibold text-center">Konversi<br>ke Menit</th>
                <th class="p-3 font-semibold text-center">Interval<br>Failure (jam)</th>
                <th class="p-3 font-semibold text-center min-w-[80px]">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="failureLogs.length === 0">
                <td colspan="14" class="p-12 text-center text-slate-400 bg-slate-50/50">
                  <div class="flex flex-col items-center justify-center">
                    <AlertTriangleIcon class="w-8 h-8 text-slate-300 mb-2" />
                    <p>Belum ada data kejadian kegagalan untuk subsystem ini di unit kerja Anda.</p>
                    <div class="mt-4 flex gap-3 justify-center items-center">
                      <button v-if="assets.length > 0" type="button" @click="openCreateModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 flex items-center gap-2 transition">
                        <PlusIcon class="w-4 h-4" /> Input Manual
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
              <tr v-for="(log, idx) in failureLogs" :key="idx" class="hover:bg-slate-50 transition-colors">
                <td class="p-3 font-medium text-slate-700">{{ log.lokasi }}</td>
                <td class="p-3">{{ log.resor }}</td>
                <td class="p-3">{{ log.qc || '-' }}</td>
                <td class="p-3">{{ log.failure_event }}</td>
                <td class="p-3">{{ log.penyebab }}</td>
                <td class="p-3">{{ log.tindakan }}</td>
                <td class="p-3 text-center font-bold" :class="log.penggantian_sparepart === 'Y' || log.penggantian_sparepart === 'Ya' ? 'text-rose-600' : 'text-slate-500'">
                  {{ log.penggantian_sparepart }}
                </td>
                <td class="p-3 text-center" :class="log.tindak_vandalisme === 'Y' || log.tindak_vandalisme === 'Ya' ? 'text-rose-600 font-bold' : ''">{{ log.tindak_vandalisme }}</td>
                
                <td class="p-3 whitespace-nowrap text-rose-600 font-medium">{{ formatReportDateTime(log.tanggal_jam_kejadian || (log.tanggal_kejadian + ' ' + (log.mulai || '00:00'))) }}</td>
                <td class="p-3 whitespace-nowrap text-emerald-600 font-medium">{{ formatReportDateTime(log.tanggal_jam_penanganan || (log.tanggal_penanganan + ' ' + (log.selesai || '00:00'))) }}</td>
                
                <td class="p-3 text-center font-bold">{{ log.downtime_jam !== undefined ? log.downtime_jam : '-' }}</td>
                <td class="p-3 text-center text-slate-600">{{ log.downtime_menit !== undefined ? log.downtime_menit : '-' }}</td>
                <td class="p-3 text-center text-slate-600">{{ log.interval_jam !== undefined ? log.interval_jam : '-' }}</td>
                <td class="p-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button type="button" @click="openEditModal(log)" class="text-blue-500 hover:text-blue-700 p-1" title="Edit">
                      <EditIcon class="w-4 h-4" />
                    </button>
                    <button type="button" @click="deleteLog(log)" class="text-rose-500 hover:text-rose-700 p-1" title="Hapus">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tabel Output: Daftar Kebutuhan Sparepart (Kuning/Amber) -->
      <div class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden mt-8">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-3 border-b border-amber-200">
          <h3 class="text-white font-bold text-sm flex items-center gap-2">
            <SettingsIcon class="w-4 h-4 text-white/90" />
            Output Report: Daftar Kebutuhan & Penggantian Sparepart
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left">
            <thead class="bg-amber-50 text-amber-900 border-b border-amber-100">
              <tr>
                <th class="p-3 font-semibold">Tgl Kejadian</th>
                <th class="p-3 font-semibold">Lokasi / Resor</th>
                <th class="p-3 font-semibold">Failure Event</th>
                <th class="p-3 font-semibold">Tindakan</th>
                <th class="p-3 font-semibold text-center bg-amber-100">Nama Sparepart</th>
                <th class="p-3 font-semibold text-center bg-amber-100">Jumlah</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-amber-100">
              <tr v-if="sparepartLogs.length === 0">
                <td colspan="6" class="p-8 text-center text-amber-600/70">
                  <div class="flex flex-col items-center justify-center">
                    <SettingsIcon class="w-8 h-8 text-amber-300 mb-2 opacity-50" />
                    <p>Belum ada laporan gangguan yang memerlukan penggantian sparepart di unit ini.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="(log, idx) in sparepartLogs" :key="'sp-'+idx" class="hover:bg-amber-50/50 transition-colors">
                <td class="p-3 font-medium text-slate-700">{{ formatReportDateTime(log.tanggal_jam_kejadian) }}</td>
                <td class="p-3">{{ log.lokasi }} <br><span class="text-[10px] text-slate-500">{{ log.resor }}</span></td>
                <td class="p-3">{{ log.failure_event }}</td>
                <td class="p-3">{{ log.tindakan }}</td>
                <td class="p-3 text-center font-bold text-amber-700 bg-amber-50/30">{{ log.nama_sparepart || 'Sesuai Tindakan' }}</td>
                <td class="p-3 text-center font-bold text-amber-700 bg-amber-50/30">{{ log.jumlah_sparepart || '1' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <TroubleReportModal
        :is-open="isModalOpen"
        :subsystem-name="subsystemName"
        :spare-parts="spare_parts"
        :log="selectedLog"
        @close="isModalOpen = false"
        @save="handleSaveLog"
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import MainLayout from '@/layouts/MainLayout.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { ActivityIcon, AlertTriangleIcon, CalendarDaysIcon, InfoIcon, PlusIcon, SettingsIcon, EditIcon, TrashIcon } from 'lucide-vue-next'
import TroubleReportModal from '@/components/trouble-report/TroubleReportModal.vue'

const props = defineProps({
  selected_area: { type: String, default: null },
  subsystem: { type: String, default: 'Subsystem Tidak Diketahui' },
  assets: { type: Array, default: () => [] },
  reliability: { type: Array, default: () => [] },
  failure_logs: { type: Array, default: () => [] },
  spare_parts: { type: Array, default: () => [] },
})

const isModalOpen = ref(false)
const selectedLog = ref(null)
const isInstallationDateEditorOpen = ref(false)
const installationDateDrafts = ref({})
const installationDateErrors = ref({})
const today = new Date().toISOString().slice(0, 10)
const subsystemName = computed(() => props.subsystem || 'Subsystem Tidak Diketahui')
const failureLogs = computed(() => props.failure_logs)
const totalUnits = computed(() => props.assets.reduce((total, asset) => total + Number(asset.jumlah_unit || 0), 0))
const selectedAreaLabel = computed(() => props.selected_area || 'Wilayah belum dipilih')
const installationDates = computed(() => [...new Set(
  props.assets.map((asset) => asset.tahun_pemasangan).filter(Boolean),
)].sort())
const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
}).format(new Date(`${value}T00:00:00`))
const formatReportDateTime = (value) => {
  const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/)

  return match ? `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}` : (value || '—')
}
const installationDateLabel = computed(() => installationDates.value.length
  ? installationDates.value.map(formatDate).join(' • ')
  : 'Belum tercatat')
const excelBaselineDate = computed(() => props.reliability.find((summary) => summary.baseline_date)?.baseline_date || null)
const calculationBaselineLabel = computed(() => excelBaselineDate.value
  ? formatDate(excelBaselineDate.value)
  : 'belum tersedia')
const summaryData = computed(() => {
  const summary = props.reliability[0]
  if (!summary && props.assets.length === 0) return null

  return {
    jumlah_unit: summary?.jumlah_unit ?? totalUnits.value,
    total_operating_hour: summary?.total_operating_hour ?? null,
    total_uptime: summary?.total_uptime ?? null,
    total_downtime: summary?.total_downtime ?? null,
    jumlah_failure: summary?.jumlah_failure ?? props.failure_logs.length,
    mttf: summary?.mttf ?? null,
    mtbf: summary?.mtbf ?? null,
    failure_rate: summary?.failure_rate ?? null,
    reliability: summary?.reliability ?? null,
    availability: summary?.availability ?? null,
    spare_part_replacement_count: summary?.spare_part_replacement_count ?? 0,
    vandalism_count: summary?.vandalism_count ?? 0,
  }
})

// Formatting helpers
const isMissing = (value) => value === null || value === undefined || value === ''
const trimTrailingZeroes = (value) => String(Number(value))
const formatNumber = (num) => isMissing(num) ? 'Data belum ada' : trimTrailingZeroes(Number(num).toFixed(2))
const formatDecimal = (num) => isMissing(num) ? 'Data belum ada' : trimTrailingZeroes(Number(num).toFixed(10))
const formatPercent = (num) => isMissing(num) ? 'Data belum ada' : (Number(num) * 100).toFixed(4) + '%'

// Output Report: Filter only logs with Sparepart Replacements
const sparepartLogs = computed(() => {
  return failureLogs.value.filter(log => log.penggantian_sparepart === 'Y')
})

const openCreateModal = () => {
  selectedLog.value = null
  isModalOpen.value = true
}

const openInstallationDateEditor = () => {
  installationDateDrafts.value = Object.fromEntries(
    props.assets.map((asset) => [asset.id, asset.tahun_pemasangan || '']),
  )
  installationDateErrors.value = {}
  isInstallationDateEditorOpen.value = true
}

const saveInstallationDate = (asset) => {
  installationDateErrors.value = { ...installationDateErrors.value, [asset.id]: null }
  router.patch(`/master-asset/${asset.id}/installation-date`, {
    tanggal_pemasangan: installationDateDrafts.value[asset.id] || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      if (props.assets.length === 1) isInstallationDateEditorOpen.value = false
    },
    onError: (errors) => {
      installationDateErrors.value = {
        ...installationDateErrors.value,
        [asset.id]: errors.tanggal_pemasangan || 'Tanggal pemasangan tidak dapat disimpan.',
      }
    },
  })
}

const openEditModal = (log) => {
  selectedLog.value = log
  isModalOpen.value = true
}

const deleteLog = (log) => {
  if (confirm('Apakah Anda yakin ingin menghapus data Trouble Report ini?')) {
    router.delete(`/trouble-report/${log.id}`, {
      preserveScroll: true,
    })
  }
}

const handleSaveLog = (logData) => {
  const asset = props.assets[0]
  if (!asset) return

  if (selectedLog.value) {
    router.put(`/trouble-report/${selectedLog.value.id}`, {
      ...logData,
      asset_id: asset.id,
    }, {
      preserveScroll: true,
      onSuccess: () => { isModalOpen.value = false },
    })
  } else {
    router.post('/trouble-report', {
      ...logData,
      asset_id: asset.id,
      idempotency_key: crypto.randomUUID(),
    }, {
      preserveScroll: true,
      onSuccess: () => { isModalOpen.value = false },
    })
  }
}

const backToDashboard = () => {
  router.get('/dashboard', props.selected_area ? { area: props.selected_area } : {})
}
</script>
