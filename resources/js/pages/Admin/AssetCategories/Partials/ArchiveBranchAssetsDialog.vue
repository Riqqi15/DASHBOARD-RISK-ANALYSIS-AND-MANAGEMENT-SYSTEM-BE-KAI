<script setup>
import { AlertTriangle, X } from 'lucide-vue-next'
import AccessibleDialog from './AccessibleDialog.vue'

defineProps({ nodeName: { type: String, required: true }, preview: { type: Object, default: null }, loading: { type: Boolean, default: false }, form: { type: Object, required: true } })
defineEmits(['close', 'confirm'])
</script>

<template>
  <AccessibleDialog labelledby="archive-dialog-title" describedby="archive-dialog-description" :processing="form.processing" panel-class="w-full max-w-lg" @close="$emit('close')">
    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
      <div>
        <h2 id="archive-dialog-title" class="text-lg font-semibold text-slate-950">Hapus aset wilayah?</h2>
        <p id="archive-dialog-description" class="mt-1 text-sm leading-6 text-slate-600">Kategori global tidak dihapus. Hanya aset aktif pada <strong>{{ nodeName }}</strong> dan seluruh turunannya di wilayah ini yang diarsipkan.</p>
      </div>
      <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Tutup dialog" :disabled="form.processing" @click="$emit('close')"><X :size="19" /></button>
    </header>
    <div class="space-y-5 p-6">
      <div v-if="loading" class="h-24 animate-pulse rounded-xl bg-slate-100 motion-reduce:animate-none" aria-label="Memuat rincian penghapusan" />
      <div v-else-if="preview" class="grid grid-cols-2 gap-3">
        <div class="rounded-xl bg-slate-100 p-4"><p class="text-xs text-slate-600">Aset diarsipkan</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ preview.assets_count }}</p></div>
        <div class="rounded-xl bg-slate-100 p-4"><p class="text-xs text-slate-600">Riwayat dipertahankan</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ preview.historical_records_count }}</p></div>
      </div>
      <div class="flex gap-3 rounded-xl bg-amber-50 p-4 text-amber-900">
        <AlertTriangle :size="20" class="mt-0.5 shrink-0" aria-hidden="true" />
        <p class="text-sm leading-6">Laporan gangguan, risiko, dan keandalan tetap tersimpan. Aset dari wilayah lain tidak berubah.</p>
      </div>
      <p v-if="form.errors.confirmation || form.errors.unit_kerja_id" role="alert" class="text-sm text-red-600">{{ form.errors.confirmation || form.errors.unit_kerja_id }}</p>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button type="button" class="h-11 rounded-lg border border-slate-300 px-5 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="form.processing" @click="$emit('close')">Batal</button>
        <button type="button" class="h-11 rounded-lg bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60" :disabled="form.processing || loading || !preview?.assets_count" @click="$emit('confirm')">{{ form.processing ? 'Mengarsipkan…' : `Hapus ${preview?.assets_count ?? 0} aset wilayah` }}</button>
      </div>
    </div>
  </AccessibleDialog>
</template>
