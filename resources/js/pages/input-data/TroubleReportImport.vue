<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import {
  AlertTriangle,
  CheckCircle2,
  FileSpreadsheet,
  UploadCloud,
} from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'

const props = defineProps({
  can_choose_unit: { type: Boolean, default: false },
  selected_unit_id: { type: Number, default: null },
  units: { type: Array, default: () => [] },
  result: { type: Object, default: null },
})

const page = usePage()
const assignedUnit = computed(() => page.props.auth?.user?.unit_kerja ?? null)
const form = useForm({
  unit_kerja_id: props.selected_unit_id ?? '',
  workbook: null,
})

const selectWorkbook = (event) => {
  form.workbook = event.target.files?.[0] ?? null
}

const submit = () => {
  form.post('/trouble-report/import', {
    forceFormData: true,
    preserveScroll: true,
  })
}

const counters = computed(() => props.result ? [
  { label: 'Sheet terbaca', value: props.result.sheets ?? 0, tone: 'text-blue-700 bg-blue-50 border-blue-100' },
  { label: 'Snapshot Excel', value: props.result.snapshots ?? 0, tone: 'text-cyan-700 bg-cyan-50 border-cyan-100' },
  { label: 'Dibuat', value: props.result.created ?? 0, tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { label: 'Diperbarui', value: props.result.updated ?? 0, tone: 'text-indigo-700 bg-indigo-50 border-indigo-100' },
  { label: 'Tidak berubah', value: props.result.unchanged ?? 0, tone: 'text-slate-700 bg-slate-50 border-slate-200' },
  { label: 'Dilewati', value: props.result.skipped ?? 0, tone: 'text-amber-700 bg-amber-50 border-amber-100' },
  { label: 'Parity dihitung', value: props.result.parity?.calculated ?? 0, tone: 'text-violet-700 bg-violet-50 border-violet-100' },
  { label: 'Sesuai Excel', value: props.result.parity?.matched ?? 0, tone: 'text-emerald-700 bg-emerald-50 border-emerald-100' },
  { label: 'Ada selisih', value: props.result.parity?.mismatch ?? 0, tone: 'text-orange-700 bg-orange-50 border-orange-100' },
] : [])
</script>

<template>
  <Head title="Import Trouble Report" />
  <MainLayout>
    <div class="mx-auto max-w-6xl space-y-6">
      <header>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orange-600">Input Data</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Import Trouble Report</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
          Unggah workbook RAMS berformat .xlsm atau .xlsx. Sistem membaca tabel detail kejadian, menyimpan snapshot ringkasan Excel, lalu menghitung parity formula backend.
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
              {{ form.processing ? 'Sedang mengimpor…' : 'Import Trouble Report' }}
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
              <h3 class="font-semibold text-slate-950">Hasil impor</h3>
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
          <div v-for="counter in counters" :key="counter.label" class="rounded-lg border p-4" :class="counter.tone">
            <p class="text-xs font-medium">{{ counter.label }}</p>
            <p class="mt-1 text-2xl font-bold">{{ counter.value }}</p>
          </div>
        </div>

        <div v-if="result.issues?.length" class="border-t border-slate-200">
          <div class="bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-900">
            Daftar masalah ({{ result.issues.length }})
          </div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(issue, index) in result.issues" :key="`${issue.sheet_name}-${issue.source_row}-${index}`" class="flex gap-3 px-5 py-4">
              <AlertTriangle class="mt-0.5 shrink-0 text-amber-600" :size="17" aria-hidden="true" />
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800">{{ issue.message }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ issue.sheet_name || 'Workbook' }}
                  <template v-if="issue.source_row"> · Baris {{ issue.source_row }}</template>
                  <template v-if="issue.source_column"> · {{ issue.source_column }}</template>
                </p>
              </div>
            </li>
          </ul>
        </div>

        <div v-else class="flex items-center gap-2 border-t border-slate-200 px-5 py-4 text-sm text-emerald-700">
          <FileSpreadsheet :size="17" aria-hidden="true" />
          Tidak ada masalah pada baris yang diimpor.
        </div>
      </section>
    </div>
  </MainLayout>
</template>
