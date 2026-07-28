<script setup>
import { AlertTriangle, X } from 'lucide-vue-next'

defineProps({
  asset: { type: Object, default: null },
  processing: { type: Boolean, default: false },
})

defineEmits(['close', 'confirm'])
</script>

<template>
  <Teleport to="body">
    <div v-if="asset" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[1px]" @click.self="$emit('close')">
      <section role="dialog" aria-modal="true" aria-labelledby="delete-asset-title" class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-950/20">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
            <AlertTriangle :size="22" aria-hidden="true" />
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup dialog" :disabled="processing" @click="$emit('close')">
            <X :size="19" aria-hidden="true" />
          </button>
        </div>

        <h2 id="delete-asset-title" class="mt-5 text-lg font-semibold text-slate-950">Hapus aset?</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
          <strong class="font-semibold text-slate-800">{{ asset.nama_aset }}</strong> akan dihapus dari daftar aktif. Tindakan ini tercatat dalam audit log.
        </p>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" class="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50" :disabled="processing" @click="$emit('close')">Batal</button>
          <button type="button" class="h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="processing" @click="$emit('confirm')">
            {{ processing ? 'Menghapus...' : 'Hapus aset' }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
