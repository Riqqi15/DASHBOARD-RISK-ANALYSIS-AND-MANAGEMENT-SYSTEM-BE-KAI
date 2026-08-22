<script setup>
import { X } from 'lucide-vue-next'
import AccessibleDialog from './AccessibleDialog.vue'

defineProps({ form: { type: Object, required: true }, nodeName: { type: String, required: true } })
const emit = defineEmits(['close', 'submit', 'update-field'])
const updateField = (field, value) => emit('update-field', { field, value })
const numberValue = (event) => event.target.value === '' ? '' : event.target.valueAsNumber
</script>

<template>
  <AccessibleDialog labelledby="asset-dialog-title" describedby="asset-dialog-description" :processing="form.processing" panel-class="w-full max-w-xl" @close="$emit('close')">
    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
      <div>
        <h2 id="asset-dialog-title" class="text-lg font-semibold text-slate-950">Input jumlah aset wilayah</h2>
        <p id="asset-dialog-description" class="mt-1 text-sm leading-6 text-slate-600">Catat jumlah peralatan pada kategori dan wilayah yang sedang dipilih.</p>
      </div>
      <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Tutup dialog" :disabled="form.processing" @click="$emit('close')"><X :size="19" /></button>
    </header>
    <form class="grid gap-5 p-6 sm:grid-cols-2" @submit.prevent="$emit('submit')">
      <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 sm:col-span-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Kategori aset</p>
        <p class="mt-1 font-semibold text-slate-950">{{ nodeName }}</p>
        <p class="mt-1 text-sm text-slate-600">Nama aset mengikuti kategori. Status awal otomatis aktif.</p>
      </div>
      <div>
        <label for="taxonomy-asset-units" class="mb-2 block text-sm font-medium text-slate-800">Jumlah unit</label>
        <input id="taxonomy-asset-units" :value="form.jumlah_unit" data-dialog-initial-focus type="number" min="0" required class="h-11 w-full rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10" @input="updateField('jumlah_unit', numberValue($event))" />
        <p v-if="form.errors.jumlah_unit" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.jumlah_unit }}</p>
      </div>
      <div>
        <label for="taxonomy-asset-date" class="mb-2 block text-sm font-medium text-slate-800">Tanggal pemasangan <span class="font-normal text-slate-400">(opsional)</span></label>
        <input id="taxonomy-asset-date" :value="form.tanggal_pemasangan" type="date" class="h-11 w-full rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10" @input="updateField('tanggal_pemasangan', $event.target.value)" />
        <p class="mt-2 text-xs text-slate-500">Dapat diperbarui lagi melalui halaman Master Aset.</p>
        <p v-if="form.errors.tanggal_pemasangan" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.tanggal_pemasangan }}</p>
      </div>
      <p v-if="Object.keys(form.errors).length" role="alert" class="sm:col-span-2 text-sm text-red-600">Periksa kembali data yang ditandai, lalu simpan ulang.</p>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5 sm:col-span-2">
        <button type="button" class="h-11 rounded-lg border border-slate-300 px-5 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="form.processing" @click="$emit('close')">Batal</button>
        <button type="submit" class="h-11 rounded-lg bg-[#F15A24] px-5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan data' }}</button>
      </div>
    </form>
  </AccessibleDialog>
</template>
