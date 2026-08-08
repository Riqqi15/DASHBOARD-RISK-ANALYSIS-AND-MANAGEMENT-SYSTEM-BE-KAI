<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { AlertTriangle, Boxes, Download, FileSpreadsheet, FileText, Gauge, Wrench } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'

const props = defineProps({
  selected_area: { type: String, default: null },
  units: { type: Array, default: () => [] },
})

const query = computed(() => props.selected_area ? `?area=${encodeURIComponent(props.selected_area)}` : '')
const reports = [
  { type: 'inventory', title: 'Inventori & Proposal', description: 'Stok saat ini, safety stock, reorder point, status beli, dan jumlah proposal.', icon: Boxes, tone: 'bg-teal-50 text-teal-700' },
  { type: 'trouble-report', title: 'Trouble Report', description: 'Histori gangguan, penyebab, tindakan, downtime, penggantian sparepart, dan vandalisme.', icon: Wrench, tone: 'bg-orange-50 text-orange-700' },
  { type: 'risk-register', title: 'Risk Register', description: 'Peristiwa risiko, L × C, rating, rekomendasi, status, dan sumber data.', icon: AlertTriangle, tone: 'bg-red-50 text-red-700' },
  { type: 'reliability', title: 'Reliability & Availability', description: 'Workbook berisi ringkasan dan satu sheet untuk setiap subsystem dengan rumus Excel, mengikuti area yang dipilih.', icon: Gauge, tone: 'bg-blue-50 text-blue-700', formulaWorkbook: true },
]
</script>

<template>
  <Head title="Laporan RAMS" />
  <MainLayout>
    <div class="space-y-6">
      <AreaSelectorBanner v-if="units.length" :units="units" :selected-area="selected_area" />

      <header>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orange-600">Reporting</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Laporan RAMS</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Unduh data operasional sebagai Excel untuk pengolahan atau PDF siap cetak. Keduanya mengikuti pembatasan unit akun dan area yang dipilih.</p>
      </header>

      <div class="rounded-xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-900">
        <div class="flex gap-3"><FileSpreadsheet class="mt-0.5 shrink-0 text-blue-700" :size="19" /><p><strong>Area laporan:</strong> {{ selected_area || 'Nasional' }}. Workbook memiliki header terformat, filter, dan baris pertama yang dibekukan agar nyaman dibaca di Excel.</p></div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <article v-for="report in reports" :key="report.type" class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
          <div class="flex items-start justify-between gap-4">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl" :class="report.tone"><component :is="report.icon" :size="23" /></span>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">XLSX</span>
          </div>
          <h2 class="mt-5 text-lg font-bold text-slate-950">{{ report.title }}</h2>
          <p class="mt-2 min-h-12 text-sm leading-6 text-slate-600">{{ report.description }}</p>
          <div class="mt-5 flex flex-wrap gap-2">
            <a :href="`/reports/${report.type}/xlsx${query}`" class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#171650] px-4 text-sm font-semibold text-white transition hover:bg-orange-600">
              <Download :size="17" /> {{ report.formulaWorkbook ? 'Unduh Excel Berformula' : 'Unduh Excel' }}
            </a>
            <a :href="`/reports/${report.type}/pdf${query}`" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700">
              <FileText :size="17" /> Unduh PDF
            </a>
          </div>
        </article>
      </div>
    </div>
  </MainLayout>
</template>
