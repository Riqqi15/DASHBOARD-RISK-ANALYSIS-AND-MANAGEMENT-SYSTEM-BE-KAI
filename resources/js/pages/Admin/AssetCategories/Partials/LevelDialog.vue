<script setup>
import { X } from 'lucide-vue-next'
import AccessibleDialog from './AccessibleDialog.vue'

defineProps({ form: { type: Object, required: true } })
defineEmits(['close', 'submit'])
</script>

<template>
  <AccessibleDialog labelledby="level-dialog-title" describedby="level-dialog-description" :processing="form.processing" panel-class="w-full max-w-md" @close="$emit('close')">
    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
      <div>
        <h2 id="level-dialog-title" class="text-lg font-semibold text-slate-950">Tambah level kategori</h2>
        <p id="level-dialog-description" class="mt-1 text-sm leading-6 text-slate-600">Level baru ditempatkan setelah level terakhir dan berlaku untuk semua wilayah.</p>
      </div>
      <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Tutup dialog" :disabled="form.processing" @click="$emit('close')"><X :size="19" /></button>
    </header>
    <form class="space-y-5 p-6" @submit.prevent="$emit('submit')">
      <div>
        <label for="level-name" class="mb-2 block text-sm font-medium text-slate-800">Nama level</label>
        <input id="level-name" v-model="form.name" data-dialog-initial-focus required maxlength="100" class="h-11 w-full rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10" placeholder="Contoh: Jenis perangkat" />
        <p v-if="form.errors.name || form.errors.normalized_name" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.name || form.errors.normalized_name }}</p>
      </div>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
        <button type="button" class="h-11 rounded-lg border border-slate-300 px-5 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="form.processing" @click="$emit('close')">Batal</button>
        <button type="submit" class="h-11 rounded-lg bg-[#F15A24] px-5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Tambah level' }}</button>
      </div>
    </form>
  </AccessibleDialog>
</template>
