<script setup>
import { AlertTriangle, X } from 'lucide-vue-next'

defineProps({
  category: { type: Object, required: true },
  form: { type: Object, required: true },
})

defineEmits(['close', 'confirm'])
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[1px]" @click.self="!form.processing && $emit('close')">
      <section role="dialog" aria-modal="true" aria-labelledby="delete-category-title" aria-describedby="delete-category-description" class="max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-950/20" @keydown.esc.stop="!form.processing && $emit('close')">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600"><AlertTriangle :size="21" aria-hidden="true" /></div>
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" aria-label="Tutup dialog" :disabled="form.processing" @click="$emit('close')"><X :size="19" aria-hidden="true" /></button>
        </div>
        <h2 id="delete-category-title" class="mt-5 text-lg font-semibold text-slate-950">Hapus kategori?</h2>
        <p id="delete-category-description" class="mt-2 text-sm leading-6 text-slate-600"><strong class="font-semibold text-slate-800">{{ category.name }}</strong> hanya dapat dihapus jika belum digunakan oleh kategori turunan atau data aset.</p>
        <div v-if="form.errors.category" role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-700">{{ form.errors.category }}</div>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" class="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#171650]" :disabled="form.processing" @click="$emit('close')">Batal</button>
          <button type="button" class="h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white outline-none hover:bg-red-700 focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2 disabled:opacity-60" aria-label="Konfirmasi hapus kategori" :disabled="form.processing" @click="$emit('confirm')">Hapus kategori</button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
