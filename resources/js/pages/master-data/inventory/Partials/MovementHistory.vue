<script setup>
import { Link } from '@inertiajs/vue3'
import { History, RotateCcw } from 'lucide-vue-next'

const props = defineProps({
  movements: { type: Object, required: true },
  showUnit: Boolean,
  loading: Boolean,
})
defineEmits(['correct'])

const canCorrect = (row) => row.type !== 'correction'

const movementMeta = {
  in: { label: 'Masuk', sign: '+', class: 'text-emerald-700 bg-emerald-50 border-emerald-200' },
  out: { label: 'Keluar', sign: '−', class: 'text-red-700 bg-red-50 border-red-200' },
  opening: { label: 'Saldo awal', sign: '+', class: 'text-indigo-700 bg-indigo-50 border-indigo-200' },
  correction: { label: 'Koreksi', sign: '', class: 'text-amber-800 bg-amber-50 border-amber-200' },
}
const meta = (row) => movementMeta[row.type] ?? movementMeta[row.direction]
const signedQuantity = (row) => `${row.direction === 'out' ? '−' : '+'}${row.quantity} ${row.spare_part.unit_of_measure}`
const formatDate = (value) => value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(`${value}T00:00:00+07:00`)) : '—'
const formatTime = (value) => value ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' }).format(new Date(value)).replace(':', '.') : '—'
const pageLabel = (label) => label.includes('Previous') ? 'Sebelumnya' : label.includes('Next') ? 'Berikutnya' : label.replace(/&[^;]+;/g, '')
</script>

<template>
  <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="movement-ledger-title">
    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
      <div>
        <h2 id="movement-ledger-title" class="text-sm font-semibold text-slate-950">Riwayat pergerakan stok</h2>
        <p class="mt-0.5 text-xs text-slate-500">Catatan bersifat permanen; kesalahan diperbaiki melalui transaksi koreksi.</p>
      </div>
      <span class="hidden font-mono text-[10px] uppercase tracking-wider text-slate-400 sm:block">operasional / posted</span>
    </div>

    <div v-if="loading" class="space-y-2 p-4" aria-label="Memuat riwayat transaksi" aria-busy="true">
      <div v-for="index in 5" :key="index" class="h-14 animate-pulse rounded-lg bg-slate-100 motion-reduce:animate-none" />
    </div>

    <div v-else-if="movements.data.length" class="overflow-x-auto">
      <table class="w-full min-w-[1180px] border-collapse text-left text-sm">
        <thead class="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
          <tr>
            <th class="px-5 py-3">Waktu</th><th v-if="showUnit" class="px-4 py-3">Unit</th><th class="px-4 py-3">Suku cadang</th><th class="px-4 py-3">Arah / jumlah</th><th class="px-4 py-3">Ledger stok</th><th class="px-4 py-3">Pelaksana</th><th class="px-4 py-3">Referensi</th><th class="px-5 py-3 text-right">Tindak lanjut</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in movements.data" :key="row.id" class="align-top hover:bg-slate-50">
            <td class="px-5 py-3"><p class="font-medium text-slate-800">{{ formatDate(row.movement_date) }}</p><p class="mt-1 font-mono text-xs text-slate-500">Diposting {{ formatTime(row.posted_at) }}</p></td>
            <td v-if="showUnit" class="px-4 py-3"><p class="font-mono text-xs font-semibold text-slate-800">{{ row.unit.code }}</p><p class="mt-1 max-w-40 truncate text-xs text-slate-500">{{ row.unit.name }}</p></td>
            <td class="px-4 py-3"><p class="font-mono text-xs font-semibold text-[#2d2a70]">{{ row.spare_part.code }}</p><p class="mt-1 max-w-56 font-medium text-slate-900">{{ row.spare_part.detail_equipment }}</p></td>
            <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold" :class="meta(row).class">{{ meta(row).label }}</span><p class="mt-2 font-mono font-semibold tabular-nums" :class="row.direction === 'out' ? 'text-red-700' : 'text-emerald-700'">{{ signedQuantity(row) }}</p></td>
            <td class="px-4 py-3"><p class="font-mono font-semibold tabular-nums text-slate-900">{{ row.stock_before }} → {{ row.stock_after }}</p><p class="mt-1 text-xs text-slate-500">{{ row.spare_part.unit_of_measure }}</p></td>
            <td class="px-4 py-3"><p class="text-sm font-medium text-slate-800">{{ row.actor?.name ?? 'Sistem' }}</p><p class="mt-1 font-mono text-[11px] text-slate-400">#{{ row.id }}</p></td>
            <td class="px-4 py-3"><p class="font-mono text-xs text-slate-700">{{ row.reference_number ?? '—' }}</p><p v-if="row.notes" class="mt-1 max-w-52 text-xs leading-5 text-slate-500">{{ row.notes }}</p><span v-if="row.reverses_movement_id" class="mt-2 inline-flex rounded bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">Koreksi #{{ row.reverses_movement_id }}</span></td>
            <td class="px-5 py-3 text-right">
              <button v-if="canCorrect(row)" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus:ring-2 focus:ring-[#2d2a70] focus:ring-offset-2" :aria-label="`Koreksi transaksi ${row.id}`" @click="$emit('correct', row)"><RotateCcw :size="16" aria-hidden="true" /> Koreksi</button>
              <span v-else class="inline-flex min-h-11 items-center text-xs text-slate-400">Catatan koreksi</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="px-5 py-12 text-center">
      <History :size="34" class="mx-auto text-slate-300" aria-hidden="true" />
      <h3 class="mt-3 text-sm font-semibold text-slate-900">Belum ada transaksi stok</h3>
      <p class="mt-1 text-sm text-slate-500">Transaksi masuk, keluar, saldo awal, dan koreksi akan tercatat di sini.</p>
    </div>

    <nav v-if="movements.links?.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3" aria-label="Paginasi riwayat">
      <p class="text-xs text-slate-500">{{ movements.from ?? 0 }}–{{ movements.to ?? 0 }} dari {{ movements.total ?? 0 }}</p>
      <div class="flex gap-1">
        <template v-for="link in movements.links" :key="link.label">
          <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border px-3 text-xs font-semibold" :class="link.active ? 'border-[#2d2a70] bg-[#2d2a70] text-white' : 'border-slate-200 text-slate-600'">{{ pageLabel(link.label) }}</Link>
          <span v-else class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-slate-100 px-3 text-xs text-slate-300">{{ pageLabel(link.label) }}</span>
        </template>
      </div>
    </nav>
  </section>
</template>
