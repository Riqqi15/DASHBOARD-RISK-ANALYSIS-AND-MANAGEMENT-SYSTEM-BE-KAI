<script setup>
import { Link } from '@inertiajs/vue3'
import { AlertCircle, History, RefreshCw, RotateCcw } from 'lucide-vue-next'

const props = defineProps({
  movements: { type: Object, required: true },
  showUnit: Boolean,
  loading: Boolean,
  error: { type: String, default: '' },
  canReset: Boolean,
})
defineEmits(['correct', 'retry', 'reset'])

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
        <p class="mt-0.5 text-sm text-slate-500">Catatan bersifat permanen; kesalahan diperbaiki melalui transaksi koreksi.</p>
      </div>
      <span class="hidden font-mono text-sm uppercase tracking-wider text-slate-400 sm:block">operasional / posted</span>
    </div>

    <div v-if="error" data-history-error class="m-4 rounded-xl border border-red-200 bg-red-50 p-5" role="alert">
      <div class="flex items-start gap-3 text-red-800">
        <AlertCircle :size="20" class="mt-0.5 shrink-0" aria-hidden="true" />
        <div><p class="font-semibold">Riwayat transaksi tidak dapat dimuat</p><p class="mt-1 text-sm">{{ error }}</p></div>
      </div>
      <button data-history-retry type="button" class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-semibold text-red-700 outline-none hover:bg-red-100 focus:ring-2 focus:ring-red-600" @click="$emit('retry')"><RefreshCw :size="17" aria-hidden="true" /> Coba lagi</button>
    </div>

    <div v-else-if="loading" data-history-loading class="space-y-2 p-4" aria-label="Memuat riwayat transaksi" aria-busy="true">
      <div v-for="index in 5" :key="index" class="h-14 animate-pulse rounded-lg bg-slate-100 motion-reduce:animate-none" />
    </div>

    <div v-else-if="movements.data.length" data-history-desktop class="hidden overflow-x-auto lg:block">
      <table class="w-full min-w-[1180px] border-collapse text-left text-sm">
        <thead class="border-b border-slate-200 text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">
          <tr>
            <th class="px-5 py-3">Waktu</th><th v-if="showUnit" class="px-4 py-3">Unit</th><th class="px-4 py-3">Suku cadang</th><th class="px-4 py-3">Arah / jumlah</th><th class="px-4 py-3">Ledger stok</th><th class="px-4 py-3">Pelaksana</th><th class="px-4 py-3">Referensi</th><th class="px-5 py-3 text-right">Tindak lanjut</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in movements.data" :key="row.id" class="align-top hover:bg-slate-50">
            <td class="px-5 py-3"><p class="font-medium text-slate-800">{{ formatDate(row.movement_date) }}</p><p class="mt-1 font-mono text-sm text-slate-500">Diposting {{ formatTime(row.posted_at) }}</p></td>
            <td v-if="showUnit" class="px-4 py-3"><p class="font-mono text-sm font-semibold text-slate-800">{{ row.unit.code }}</p><p class="mt-1 max-w-40 truncate text-sm text-slate-500">{{ row.unit.name }}</p></td>
            <td class="px-4 py-3"><p class="font-mono text-sm font-semibold text-[#2d2a70]">{{ row.spare_part.code }}</p><p class="mt-1 max-w-56 font-medium text-slate-900">{{ row.spare_part.detail_equipment }}</p></td>
            <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-sm font-semibold" :class="meta(row).class">{{ meta(row).label }}</span><p class="mt-2 font-mono font-semibold tabular-nums" :class="row.direction === 'out' ? 'text-red-700' : 'text-emerald-700'">{{ signedQuantity(row) }}</p></td>
            <td class="px-4 py-3"><p class="font-mono font-semibold tabular-nums text-slate-900">{{ row.stock_before }} → {{ row.stock_after }}</p><p class="mt-1 text-sm text-slate-500">{{ row.spare_part.unit_of_measure }}</p></td>
            <td class="px-4 py-3"><p class="text-sm font-medium text-slate-800">{{ row.actor?.name ?? 'Sistem' }}</p><p class="mt-1 font-mono text-sm text-slate-400">#{{ row.id }}</p></td>
            <td class="px-4 py-3"><p class="font-mono text-sm text-slate-700">{{ row.reference_number ?? '—' }}</p><p v-if="row.notes" class="mt-1 max-w-52 text-sm leading-5 text-slate-500">{{ row.notes }}</p><span v-if="row.reverses_movement_id" class="mt-2 inline-flex rounded bg-amber-100 px-2 py-1 text-sm font-semibold text-amber-800">Koreksi #{{ row.reverses_movement_id }}</span></td>
            <td class="px-5 py-3 text-right">
              <button v-if="canCorrect(row)" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus:ring-2 focus:ring-[#2d2a70] focus:ring-offset-2" :aria-label="`Koreksi transaksi ${row.id}`" @click="$emit('correct', row)"><RotateCcw :size="16" aria-hidden="true" /> Koreksi</button>
              <span v-else class="inline-flex min-h-11 items-center text-sm text-slate-400">Catatan koreksi</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="!loading && !error && movements.data.length" data-history-mobile class="space-y-3 bg-slate-50 p-3 lg:hidden">
      <article v-for="row in movements.data" :key="row.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0"><p class="font-mono text-sm font-semibold text-[#2d2a70]">{{ row.spare_part.code }}</p><h3 class="mt-1 font-semibold text-slate-950">{{ row.spare_part.detail_equipment }}</h3></div>
          <span class="shrink-0 rounded-full border px-2.5 py-1 text-sm font-semibold" :class="meta(row).class">{{ meta(row).label }}</span>
        </div>
        <dl class="mt-4 grid grid-cols-2 gap-3 border-y border-slate-100 py-3 text-sm">
          <div><dt class="text-slate-500">Tanggal</dt><dd class="mt-1 font-medium text-slate-800">{{ formatDate(row.movement_date) }}</dd><dd class="mt-1 font-mono text-sm text-slate-500">Diposting {{ formatTime(row.posted_at) }}</dd></div>
          <div class="text-right"><dt class="text-slate-500">Jumlah / ledger</dt><dd class="mt-1 font-mono font-semibold" :class="row.direction === 'out' ? 'text-red-700' : 'text-emerald-700'">{{ signedQuantity(row) }}</dd><dd class="mt-1 font-mono text-slate-700">{{ row.stock_before }} → {{ row.stock_after }}</dd></div>
          <div v-if="showUnit"><dt class="text-slate-500">Unit kerja</dt><dd class="mt-1 font-mono font-semibold text-slate-800">{{ row.unit.code }}</dd></div>
          <div :class="showUnit ? 'text-right' : ''"><dt class="text-slate-500">Pelaksana</dt><dd class="mt-1 font-medium text-slate-800">{{ row.actor?.name ?? 'Sistem' }}</dd></div>
        </dl>
        <div class="mt-3 flex items-end justify-between gap-3"><div class="min-w-0"><p class="text-slate-500">Referensi</p><p class="mt-1 truncate font-mono text-sm text-slate-700">{{ row.reference_number ?? '—' }}</p><p v-if="row.reverses_movement_id" class="mt-1 text-sm font-semibold text-amber-800">Koreksi #{{ row.reverses_movement_id }}</p></div><button v-if="canCorrect(row)" type="button" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70]" :aria-label="`Koreksi transaksi ${row.id}`" @click="$emit('correct', row)"><RotateCcw :size="16" aria-hidden="true" /> Koreksi</button></div>
      </article>
    </div>

    <div v-if="!loading && !error && !movements.data.length" class="px-5 py-12 text-center">
      <History :size="34" class="mx-auto text-slate-300" aria-hidden="true" />
      <h3 class="mt-3 text-sm font-semibold text-slate-900">Belum ada transaksi stok</h3>
      <p class="mt-1 text-sm text-slate-500">Transaksi masuk, keluar, saldo awal, dan koreksi akan tercatat di sini.</p>
      <button v-if="canReset" data-history-reset type="button" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-slate-50 focus:ring-2 focus:ring-[#2d2a70]" @click="$emit('reset')">Hapus filter riwayat</button>
    </div>

    <nav v-if="!loading && !error && movements.data.length && movements.links?.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3" aria-label="Paginasi riwayat">
      <p class="text-sm text-slate-500">{{ movements.from ?? 0 }}–{{ movements.to ?? 0 }} dari {{ movements.total ?? 0 }}</p>
      <div class="flex gap-1">
        <template v-for="link in movements.links" :key="link.label">
          <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border px-3 text-sm font-semibold outline-none hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70] focus-visible:ring-offset-2" :class="link.active ? 'border-[#2d2a70] bg-[#2d2a70] text-white hover:bg-[#171650]' : 'border-slate-200 text-slate-600'">{{ pageLabel(link.label) }}</Link>
          <span v-else class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-slate-100 px-3 text-sm text-slate-300">{{ pageLabel(link.label) }}</span>
        </template>
      </div>
    </nav>
  </section>
</template>
