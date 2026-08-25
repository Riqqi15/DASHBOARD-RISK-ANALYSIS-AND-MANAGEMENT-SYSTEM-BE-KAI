<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { CalendarClock, Save } from 'lucide-vue-next'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps({
  unit: { type: Object, default: null },
  typeOptions: { type: Array, required: true },
  importedBaselineDate: { type: String, default: null },
  submitLabel: { type: String, required: true },
})

const initialBaselineDate = props.unit?.operating_start_date ?? ''
const form = useForm({
  code: props.unit?.code ?? '',
  name: props.unit?.name ?? '',
  type: props.unit?.type ?? 'daop',
  is_active: props.unit?.is_active ?? true,
  operating_start_date: props.unit?.operating_start_date ?? '',
  ...(props.unit ? {
    baseline_change_reason: '',
    baseline_change_confirmed: false,
  } : {}),
})

const baselineChanged = computed(() => Boolean(props.unit)
  && (form.operating_start_date || '') !== initialBaselineDate)
const formatDate = (value) => value
  ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`))
  : 'Belum tersedia'
const baselineStatus = computed(() => {
  if (!form.operating_start_date) return props.importedBaselineDate ? 'Mengikuti hasil import Excel' : 'Belum dapat dihitung'
  if (form.operating_start_date === props.importedBaselineDate) return 'Sama dengan hasil import Excel'
  return 'Override manual aktif'
})

const submit = () => {
  if (props.unit) {
    form.put(`/admin/units/${props.unit.id}`, { preserveScroll: true })
    return
  }

  form.post('/admin/units', { preserveScroll: true })
}

const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
const today = new Date().toISOString().slice(0, 10)
</script>

<template>
  <form class="space-y-6" @submit.prevent="submit">
    <div class="grid gap-6 md:grid-cols-2">
      <div>
        <label for="code" class="mb-2 block text-sm font-medium text-slate-800">Kode unit</label>
        <input id="code" v-model="form.code" :class="inputClass" maxlength="20" required placeholder="Contoh: DAOP-1" autocomplete="off" />
        <p class="mt-1.5 text-xs text-slate-500">Huruf, angka, tanda hubung, atau garis bawah. Kode otomatis menjadi kapital.</p>
        <p v-if="form.errors.code" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.code }}</p>
      </div>

      <div>
        <label for="type" class="mb-2 block text-sm font-medium text-slate-800">Jenis unit</label>
        <select id="type" v-model="form.type" :class="inputClass" required>
          <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="form.errors.type" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.type }}</p>
      </div>
    </div>

    <div>
      <label for="name" class="mb-2 block text-sm font-medium text-slate-800">Nama unit kerja</label>
      <input id="name" v-model="form.name" :class="inputClass" maxlength="255" required placeholder="Nama lengkap unit kerja" autocomplete="organization" />
      <p v-if="form.errors.name" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.name }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <label for="unit-is-active" class="flex cursor-pointer items-start gap-3">
        <input id="unit-is-active" v-model="form.is_active" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-[#2d2a70]" />
        <span>
          <span class="block text-sm font-medium text-slate-800">Unit aktif</span>
          <span class="mt-1 block text-xs leading-5 text-slate-500">Unit aktif dapat dipilih saat administrator membuat atau memindahkan akun wilayah.</span>
        </span>
      </label>
      <p v-if="form.errors.is_active" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.is_active }}</p>
    </div>

    <section v-if="unit" class="rounded-xl border border-amber-200 bg-amber-50/60 p-5" aria-labelledby="baseline-title">
      <div class="flex items-start gap-3">
        <span class="rounded-lg bg-amber-100 p-2 text-amber-700"><CalendarClock :size="19" aria-hidden="true" /></span>
        <div>
          <h3 id="baseline-title" class="text-sm font-semibold text-slate-900">Baseline Operating Days sesuai Excel</h3>
          <p class="mt-1 text-xs leading-5 text-slate-600">Dipakai untuk menghitung Operating Hours, Reliability, dan Availability seluruh subsystem di wilayah ini.</p>
        </div>
      </div>

      <dl class="mt-4 grid gap-3 rounded-lg border border-amber-100 bg-white p-4 sm:grid-cols-2">
        <div>
          <dt class="text-xs text-slate-500">Baseline import terakhir</dt>
          <dd class="mt-1 text-sm font-semibold text-slate-900">{{ formatDate(importedBaselineDate) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-500">Status acuan aktif</dt>
          <dd class="mt-1 text-sm font-semibold text-slate-900">{{ baselineStatus }}</dd>
        </div>
      </dl>

      <div class="mt-4">
        <div class="flex items-center justify-between gap-3">
          <label for="operating_start_date" class="block text-sm font-medium text-slate-800">Override baseline wilayah</label>
          <button v-if="form.operating_start_date" type="button" class="text-xs font-semibold text-amber-800 hover:underline" @click="form.operating_start_date = ''">Gunakan hasil import</button>
        </div>
        <input id="operating_start_date" v-model="form.operating_start_date" type="date" :max="today" :class="`${inputClass} mt-2`" />
        <p class="mt-1.5 text-xs text-slate-500">Kosongkan agar sistem mengikuti baseline workbook Excel terbaru. Tanggal pemasangan aset tidak dipakai sebagai pengganti.</p>
        <p v-if="form.errors.operating_start_date" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.operating_start_date }}</p>
      </div>

      <div v-if="baselineChanged" class="mt-4 rounded-lg border border-amber-300 bg-white p-4">
        <p class="text-sm font-semibold text-amber-900">Konfirmasi perubahan baseline</p>
        <p class="mt-1 text-xs leading-5 text-slate-600">Setelah disimpan, ringkasan keandalan wilayah ini akan dihitung ulang.</p>
        <label for="baseline-change-reason" class="mt-3 block text-sm font-medium text-slate-800">Alasan perubahan</label>
        <textarea id="baseline-change-reason" v-model="form.baseline_change_reason" rows="3" maxlength="500" required class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10" placeholder="Contoh: Koreksi baseline berdasarkan workbook hasil validasi KAI." />
        <p v-if="form.errors.baseline_change_reason" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.baseline_change_reason }}</p>
        <label for="baseline-change-confirmed" class="mt-3 flex cursor-pointer items-start gap-3">
          <input id="baseline-change-confirmed" v-model="form.baseline_change_confirmed" type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-[#2d2a70]" />
          <span class="text-xs leading-5 text-slate-700">Saya memahami perubahan ini memengaruhi seluruh perhitungan keandalan pada wilayah ini.</span>
        </label>
        <p v-if="form.errors.baseline_change_confirmed" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.baseline_change_confirmed }}</p>
      </div>
    </section>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
      <Link href="/admin/units" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 text-sm font-medium text-slate-700 hover:bg-slate-50">Batal</Link>
      <BaseButton type="submit" variant="primary" class="h-11 rounded-lg px-5" :loading="form.processing">
        <Save :size="17" class="mr-2" aria-hidden="true" />
        {{ submitLabel }}
      </BaseButton>
    </div>
  </form>
</template>
