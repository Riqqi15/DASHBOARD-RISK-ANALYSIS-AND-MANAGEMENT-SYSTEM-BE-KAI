<template>
  <MainLayout>
    <AreaSelectorBanner :units="units" :selected-area="selected_area" />

    <!-- Asymmetrical Grid Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6 2xl:gap-7.5">
      
      <!-- LEFT COLUMN (Span 2) -->
      <div class="xl:col-span-2 flex flex-col gap-4 md:gap-6 2xl:gap-7.5">
        
        <!-- Top Small Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 2xl:gap-7.5">
          <!-- Card 1: Total Master Aset -->
          <div class="rounded-xl border border-slate-200 bg-white py-6 px-7.5 shadow-sm">
            <div class="flex items-center justify-between">
              <div class="flex h-11.5 w-11.5 items-center justify-center rounded-full bg-slate-50">
                <DatabaseIcon class="text-slate-500 w-6 h-6 stroke-[1.5]" />
              </div>
            </div>
            <div class="mt-4 flex items-end justify-between">
              <div>
                <span class="text-sm font-medium text-slate-500">Total Master Aset</span>
                <h4 class="text-2xl font-bold text-black mt-1">{{ summary.totalAset }}</h4>
              </div>
              <span class="flex items-center gap-1 text-sm font-medium text-green-500 bg-green-50 px-2 py-0.5 rounded">
                <ArrowUpIcon class="w-3 h-3" /> Aktif
              </span>
            </div>
          </div>

          <!-- Card 2: Risiko Extreme -->
          <div class="rounded-xl border border-slate-200 bg-white py-6 px-7.5 shadow-sm">
            <div class="flex items-center justify-between">
              <div class="flex h-11.5 w-11.5 items-center justify-center rounded-full bg-slate-50">
                <AlertTriangleIcon class="text-slate-500 w-6 h-6 stroke-[1.5]" />
              </div>
            </div>
            <div class="mt-4 flex items-end justify-between">
              <div>
                <span class="text-sm font-medium text-slate-500">Risiko Extreme</span>
                <h4 class="text-2xl font-bold text-black mt-1">{{ summary.risikoExtreme }}</h4>
              </div>
              <span v-if="summary.risikoHigh > 0" class="flex items-center gap-1 text-sm font-medium text-orange-500 bg-orange-50 px-2 py-0.5 rounded">
                +{{ summary.risikoHigh }} High
              </span>
            </div>
          </div>
        </div>

        <!-- Middle Bar Chart (Tren Risiko Bulanan) -->
        <div class="rounded-xl border border-slate-200 bg-white px-5 pt-7.5 pb-5 shadow-sm sm:px-7.5 h-[380px] flex flex-col">
          <div class="flex justify-between items-start mb-6">
            <div>
              <h4 class="text-lg font-bold text-black">Tren Kegagalan Bulanan</h4>
              <p class="text-sm text-slate-500 mt-1">Total failure event per bulan ({{ selectedAreaLabel }})</p>
            </div>
            <div class="cursor-pointer text-slate-400 hover:text-slate-700">
              <MoreVerticalIcon class="w-5 h-5" />
            </div>
          </div>
          <div class="flex-grow flex items-end justify-between gap-2 md:gap-4 mt-4 px-2">
            <div v-for="item in failureTrend" :key="item.period" class="w-full flex flex-col items-center gap-2">
              <span class="text-[10px] font-bold text-slate-600">{{ item.count }}</span>
              <div class="w-full bg-[#3C50E0] rounded-t-sm transition-all duration-300 hover:bg-[#1E3A8A]" :style="{ height: getFailureBarHeight(item.count) + '%' }"></div>
              <span class="text-[10px] text-slate-500 uppercase">{{ formatPeriod(item.period) }}</span>
            </div>
            <p v-if="failureTrend.length === 0" class="w-full self-center text-center text-sm text-slate-400">Belum ada failure log.</p>
          </div>
        </div>

      </div>

      <!-- RIGHT COLUMN (Span 1) - Target Keandalan -->
      <div class="xl:col-span-1 flex flex-col gap-4 md:gap-6 2xl:gap-7.5">
        <div class="rounded-xl border border-slate-200 bg-white px-5 pt-7.5 pb-5 shadow-sm sm:px-7.5 h-full flex flex-col items-center justify-start min-h-[400px]">
          <div class="flex justify-between items-start w-full mb-8">
            <div>
              <h4 class="text-lg font-bold text-black">Rata-rata Availability</h4>
              <p class="text-sm text-slate-500 mt-1">SLA keandalan sistem sintel</p>
            </div>
            <div class="cursor-pointer text-slate-400 hover:text-slate-700">
              <MoreVerticalIcon class="w-5 h-5" />
            </div>
          </div>

          <!-- CSS Semi Circle Gauge -->
          <div class="relative w-48 h-24 overflow-hidden mb-6">
            <div class="absolute top-0 left-0 w-48 h-48 rounded-full border-[20px] border-slate-100"></div>
            <div class="absolute top-0 left-0 w-48 h-48 rounded-full border-[20px] border-[#3C50E0] rotate-[135deg]" style="border-right-color: transparent; border-bottom-color: transparent;"></div>
          </div>
          <h2 class="text-4xl font-extrabold text-black mb-1 -mt-6">{{ (summary.avgAvailability * 100).toFixed(2) }}%</h2>
          <span class="text-xs font-bold text-green-500 bg-green-50 px-2 py-0.5 rounded mb-6">SLA Terpenuhi</span>

          <p class="text-sm text-slate-500 text-center px-4 mb-8">
            Keandalan sistem sintel berada di atas ambang batas SLA. Seluruh subsystem berjalan normal.
          </p>

          <div class="w-full grid grid-cols-3 border-t border-slate-100 pt-6">
            <div class="text-center border-r border-slate-100">
              <span class="text-xs text-slate-500 block mb-1">Total Failure</span>
              <span class="text-sm font-bold text-black">{{ summary.totalFailure }}</span>
            </div>
            <div class="text-center border-r border-slate-100">
              <span class="text-xs text-slate-500 block mb-1">Proposal RO</span>
              <span class="text-sm font-bold text-black">{{ summary.totalProposalReorder }}</span>
            </div>
            <div class="text-center">
              <span class="text-xs text-slate-500 block mb-1">Risiko High</span>
              <span class="text-sm font-bold text-red-500">{{ summary.risikoHigh }}</span>
            </div>
          </div>
        </div>
      </div>
      
    </div>

    <!-- BOTTOM ROW: Risk Register Table -->
    <div class="mt-4 md:mt-6 2xl:mt-7.5 rounded-xl border border-slate-200 bg-white px-5 pt-6 pb-2.5 shadow-sm sm:px-7.5 xl:pb-1">
      <div class="flex justify-between items-center mb-6">
        <h4 class="text-lg font-bold text-black">
          Risk Register (LxC)
        </h4>
        <div class="flex items-center gap-2 text-sm">
          <span class="font-medium px-3 py-1.5 rounded bg-slate-100 text-slate-700 cursor-pointer">Semua</span>
          <span class="text-slate-400 cursor-pointer hover:text-black transition px-3 py-1.5">Open</span>
          <span class="text-slate-400 cursor-pointer hover:text-black transition px-3 py-1.5">In Progress</span>
          <span class="text-slate-400 cursor-pointer hover:text-black transition px-3 py-1.5">Closed</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200">
              <th class="py-4 px-2 text-xs font-semibold text-slate-500">Part No.</th>
              <th class="py-4 px-2 text-xs font-semibold text-slate-500">Peristiwa Risiko</th>
              <th class="py-4 px-2 text-xs font-semibold text-slate-500">Penyebab</th>
              <th class="py-4 px-2 text-xs font-semibold text-slate-500">Lokasi Aset</th>
              <th class="py-4 px-2 text-xs font-semibold text-slate-500 text-center">Status</th>
              <th class="py-4 px-2 text-xs font-semibold text-slate-500 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="riskRegisters.length === 0">
              <td colspan="6" class="py-6 text-center text-slate-500 text-sm">Belum ada risk register.</td>
            </tr>
            <tr 
              v-else 
              v-for="register in riskRegisters" 
              :key="register.id"
              class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors last:border-0"
            >
              <td class="py-4 px-2">
                <span class="font-mono font-semibold text-black text-sm">{{ register.part_number }}</span>
              </td>
              <td class="py-4 px-2">
                <p class="font-medium text-black text-sm">{{ register.peristiwa_risiko }}</p>
              </td>
              <td class="py-4 px-2">
                <p class="text-sm text-slate-600">{{ register.penyebab || register.penyebab_risiko }}</p>
              </td>
              <td class="py-4 px-2">
                <span class="text-sm text-slate-600">{{ register.location || getAssetLocation(register.aset_id) }}</span>
              </td>
              <td class="py-4 px-2 text-center">
                <span :class="[
                  'inline-flex items-center justify-center rounded px-2.5 py-1 text-xs font-medium',
                  register.status === 'Open' ? 'bg-red-50 text-red-500' : '',
                  register.status === 'In Progress' ? 'bg-orange-50 text-[#EA580C]' : '',
                  register.status === 'Closed' ? 'bg-green-50 text-green-500' : ''
                ]">
                  {{ register.status }}
                </span>
              </td>
              <td class="py-4 px-2 text-right">
                <button class="text-slate-400 hover:text-black transition-colors">
                  <EyeIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed } from 'vue'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'
import { 
  DatabaseIcon, 
  AlertTriangleIcon, 
  ArrowUpIcon,
  MoreVerticalIcon,
  EyeIcon
} from 'lucide-vue-next'

const props = defineProps({
  selected_area: { type: String, default: null },
  units: { type: Array, default: () => [] },
  summary: {
    type: Object,
    default: () => ({ totalAset: 0, risikoExtreme: 0, risikoHigh: 0, avgAvailability: 0, totalFailure: 0, totalProposalReorder: 0 }),
  },
  risk_registers: { type: Array, default: () => [] },
  assets: { type: Array, default: () => [] },
  failure_trend: { type: Array, default: () => [] },
})

const summary = computed(() => props.summary)
const riskRegisters = computed(() => props.risk_registers)
const assets = computed(() => props.assets)
const failureTrend = computed(() => props.failure_trend)
const selectedAreaLabel = computed(() => props.selected_area || 'Nasional')
const maxFailureCount = computed(() => Math.max(0, ...failureTrend.value.map(item => Number(item.count))))

const getAssetLocation = (asetId) => {
  const asset = assets.value.find(a => a.id === asetId)
  return asset ? asset.lokasi : '-'
}

const getFailureBarHeight = (count) => {
  if (maxFailureCount.value === 0) return 0
  return Math.max(8, (Number(count) / maxFailureCount.value) * 100)
}

const formatPeriod = (period) => {
  const [year, month] = String(period).split('-')
  return month && year ? `${month}/${year.slice(-2)}` : period
}
</script>
