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
                <td class="p-3 text-center">{{ summaryData.jumlah_unit || 0 }}</td>
                <td class="p-3 text-center">{{ summaryData.total_operating_hour || 0 }}</td>
                <td class="p-3 text-center text-emerald-600 font-medium">{{ formatNumber(summaryData.total_uptime) }}</td>
                <td class="p-3 text-center text-rose-600 font-medium">{{ formatNumber(summaryData.total_downtime) }}</td>
                <td class="p-3 text-center font-bold">{{ summaryData.jumlah_failure || 0 }}</td>
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
                <td class="p-3 text-center bg-orange-50 font-bold text-orange-600 border-l border-r border-orange-100">{{ calculatedSparepart }}</td>
                <td class="p-3 text-center font-bold text-rose-600">{{ calculatedVandalism }}</td>
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
                <th class="p-3 font-semibold text-center">Downtime<br>(jam)</th>
                <th class="p-3 font-semibold text-center">Konversi<br>ke Menit</th>
                <th class="p-3 font-semibold text-center">Interval<br>Failure (jam)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="failureLogs.length === 0">
                <td colspan="13" class="p-12 text-center text-slate-400 bg-slate-50/50">
                  <div class="flex flex-col items-center justify-center">
                    <AlertTriangleIcon class="w-8 h-8 text-slate-300 mb-2" />
                    <p>Belum ada data kejadian kegagalan untuk subsystem ini di unit kerja Anda.</p>
                    <div class="mt-4 flex gap-3 justify-center items-center">
                      <button v-if="assets.length > 0" @click="isModalOpen = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 flex items-center gap-2 transition">
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
                
                <td class="p-3 whitespace-nowrap text-rose-600 font-medium">{{ log.tanggal_jam_kejadian || (log.tanggal_kejadian + ' ' + (log.mulai || '00:00')) }}</td>
                <td class="p-3 whitespace-nowrap text-emerald-600 font-medium">{{ log.tanggal_jam_penanganan || (log.tanggal_penanganan + ' ' + (log.selesai || '00:00')) }}</td>
                
                <td class="p-3 text-center font-bold">{{ log.downtime_jam !== undefined ? log.downtime_jam : '-' }}</td>
                <td class="p-3 text-center text-slate-600">{{ log.downtime_menit !== undefined ? log.downtime_menit : '-' }}</td>
                <td class="p-3 text-center text-slate-600">{{ log.interval_jam !== undefined ? log.interval_jam : '-' }}</td>
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
                <td class="p-3 font-medium text-slate-700">{{ log.tanggal_jam_kejadian }}</td>
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
import { ActivityIcon, AlertTriangleIcon, PlusIcon, SettingsIcon } from 'lucide-vue-next'
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
const subsystemName = computed(() => props.subsystem || 'Subsystem Tidak Diketahui')
const failureLogs = computed(() => props.failure_logs)
const totalUnits = computed(() => props.assets.reduce((total, asset) => total + Number(asset.jumlah_unit || 0), 0))
const summaryData = computed(() => {
  const summary = props.reliability[0]
  if (!summary && props.assets.length === 0) return null

  return {
    jumlah_unit: totalUnits.value,
    total_operating_hour: summary?.total_operating_hour ?? 0,
    total_uptime: summary?.total_uptime ?? 0,
    total_downtime: summary?.total_downtime ?? 0,
    jumlah_failure: summary?.jumlah_failure ?? props.failure_logs.length,
    mttf: summary?.mttf ?? 0,
    mtbf: summary?.mtbf ?? 0,
    failure_rate: summary?.failure_rate ?? 0,
    reliability: summary?.reliability ?? 0,
    availability: summary?.availability ?? 0,
  }
})

// Formatting helpers
const formatNumber = (num) => num ? Number(num).toFixed(2).replace(/\.00$/, '') : '0'
const formatDecimal = (num) => num ? Number(num).toFixed(6) : '0'
const formatPercent = (num) => num ? (Number(num) * 100).toFixed(2) + '%' : '0%'

// Auto-calculate "COUNTIF" based on loaded logs
const calculatedSparepart = computed(() => {
  return failureLogs.value.filter(log => log.penggantian_sparepart === 'Y').length
})

const calculatedVandalism = computed(() => {
  return failureLogs.value.filter(log => log.tindak_vandalisme === 'Y').length
})

// Output Report: Filter only logs with Sparepart Replacements
const sparepartLogs = computed(() => {
  return failureLogs.value.filter(log => log.penggantian_sparepart === 'Y')
})

const handleSaveLog = (newLog) => {
  const asset = props.assets[0]
  if (!asset) return

  router.post('/trouble-report', {
    ...newLog,
    asset_id: asset.id,
    idempotency_key: crypto.randomUUID(),
  }, {
    preserveScroll: true,
    onSuccess: () => { isModalOpen.value = false },
  })
}

const backToDashboard = () => {
  router.get('/dashboard', props.selected_area ? { area: props.selected_area } : {})
}
</script>
