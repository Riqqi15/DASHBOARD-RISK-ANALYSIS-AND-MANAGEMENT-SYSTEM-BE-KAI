<script setup>
import { AlertTriangle, X } from 'lucide-vue-next'
import AccessibleDialog from './AccessibleDialog.vue'

defineProps({
  category: { type: Object, required: true },
  levelLabel: { type: String, required: true },
  form: { type: Object, required: true },
})

defineEmits(['close', 'confirm'])

</script>

<template>
  <AccessibleDialog labelledby="delete-category-title" describedby="delete-category-description" :processing="form.processing" panel-class="w-full max-w-md p-6" @close="$emit('close')">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600"><AlertTriangle :size="21" aria-hidden="true" /></div>
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" aria-label="Tutup dialog" :disabled="form.processing" @click="$emit('close')"><X :size="19" aria-hidden="true" /></button>
        </div>
        <h2 id="delete-category-title" class="mt-5 text-lg font-semibold text-slate-950">Hapus {{ levelLabel }}?</h2>
        <p id="delete-category-description" class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-800">{{ category.name }}</strong> hanya dapat dihapus jika belum digunakan oleh data lain.</p>
        <div v-if="form.errors.category" role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-700">{{ form.errors.category }}</div>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" data-dialog-initial-focus class="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#171650]" :disabled="form.processing" @click="$emit('close')">Batal</button>
          <button type="button" class="h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white outline-none hover:bg-red-700 focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2 disabled:opacity-60" :aria-label="`Konfirmasi hapus ${levelLabel}`" :disabled="form.processing" @click="$emit('confirm')">{{ form.processing ? 'Menghapus…' : `Hapus ${levelLabel}` }}</button>
        </div>
  </AccessibleDialog>
</template>
