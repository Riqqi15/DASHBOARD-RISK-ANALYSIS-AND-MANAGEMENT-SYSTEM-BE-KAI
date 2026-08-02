<script setup>
import { Link } from '@inertiajs/vue3'
import { AlertCircle, PackageOpen, Plus, RefreshCw } from 'lucide-vue-next'
import InventoryCard from './InventoryCard.vue'

defineProps({
  stocks: { type: Object, required: true },
  showUnit: Boolean,
  loading: Boolean,
  error: { type: String, default: '' },
  canReset: Boolean,
  canRecord: Boolean,
})

defineEmits(['movement', 'retry', 'reset', 'record'])

const statusMeta = {
  available: { label: 'Tersedia', class: 'border-emerald-200 bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
  below_reorder: { label: 'Di bawah reorder', class: 'border-amber-200 bg-amber-50 text-amber-800', dot: 'bg-amber-500' },
  critical: { label: 'Kritis', class: 'border-red-200 bg-red-50 text-red-700', dot: 'bg-red-500' },
  empty: { label: 'Habis', class: 'border-red-300 bg-red-50 text-red-800', dot: 'bg-red-600' },
}
const meta = (status) => statusMeta[status] ?? { label: status, class: 'border-slate-200 bg-slate-50 text-slate-700', dot: 'bg-slate-400' }
const pageLabel = (label) => label.includes('Previous') ? 'Sebelumnya' : label.includes('Next') ? 'Berikutnya' : label.replace(/&[^;]+;/g, '')
</script>

<template>
  <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="stock-ledger-title">
    <div class="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
      <div>
        <h2 id="stock-ledger-title" class="text-sm font-semibold text-slate-950">Ledger stok aktif</h2>
        <p class="mt-0.5 text-sm text-slate-500">{{ stocks.total ?? stocks.data.length }} baris stok sesuai cakupan</p>
      </div>
      <span class="hidden rounded border border-slate-200 bg-white px-2 py-1 font-mono text-sm uppercase tracking-wider text-slate-500 sm:inline">qty / unit</span>
    </div>

    <div v-if="error" data-stock-error class="m-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
      <div class="flex items-start gap-3">
        <AlertCircle :size="19" class="mt-0.5 shrink-0" aria-hidden="true" />
        <div><p class="font-semibold">Data stok tidak dapat dimuat</p><p class="mt-1">{{ error }}</p></div>
      </div>
      <button data-stock-retry type="button" class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-semibold text-red-700 outline-none hover:bg-red-100 focus:ring-2 focus:ring-red-600" @click="$emit('retry')"><RefreshCw :size="17" aria-hidden="true" /> Coba lagi</button>
    </div>

    <template v-else-if="loading">
      <div class="space-y-2 p-4" aria-label="Memuat data stok" aria-busy="true">
        <div v-for="index in 5" :key="index" class="h-14 animate-pulse rounded-lg bg-slate-100 motion-reduce:animate-none" />
      </div>
    </template>

    <template v-else-if="stocks.data.length">
      <div data-inventory-desktop class="hidden overflow-x-auto lg:block">
        <table class="w-full min-w-[980px] border-collapse text-left text-sm">
          <thead class="border-b border-slate-200 bg-white text-sm font-semibold uppercase tracking-[0.12em] text-slate-500">
            <tr>
              <th scope="col" class="px-5 py-3">Kode / nama</th>
              <th scope="col" class="px-4 py-3">Kategori</th>
              <th v-if="showUnit" scope="col" class="px-4 py-3">Unit kerja</th>
              <th scope="col" class="px-4 py-3 text-right">Stok</th>
              <th scope="col" class="px-4 py-3 text-right">Safety stock</th>
              <th scope="col" class="px-4 py-3">Status</th>
              <th scope="col" class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in stocks.data" :key="row.id" class="group hover:bg-slate-50">
              <td class="border-l-4 border-transparent px-5 py-3 group-hover:border-[#2d2a70]">
                <p class="font-mono text-sm font-semibold text-[#2d2a70]">{{ row.spare_part.code }}</p>
                <p class="mt-1 font-semibold text-slate-900">{{ row.spare_part.detail_equipment }}</p>
                <p v-if="row.spare_part.equipment" class="mt-0.5 text-sm text-slate-500">{{ row.spare_part.equipment }}</p>
              </td>
              <td class="max-w-64 px-4 py-3 text-sm text-slate-600">
                <span class="block truncate" :title="row.spare_part.category?.subsystem?.name">{{ row.spare_part.category?.subsystem?.name ?? 'Tanpa kategori' }}</span>
              </td>
              <td v-if="showUnit" class="px-4 py-3">
                <p class="font-mono text-sm font-semibold text-slate-800">{{ row.unit.code }}</p>
                <p class="mt-0.5 max-w-48 truncate text-sm text-slate-500" :title="row.unit.name">{{ row.unit.name }}</p>
              </td>
              <td class="px-4 py-3 text-right font-mono text-base font-semibold tabular-nums text-slate-950">{{ row.quantity }} <span class="text-sm font-normal text-slate-500">{{ row.spare_part.unit_of_measure }}</span></td>
              <td class="px-4 py-3 text-right font-mono text-sm tabular-nums text-slate-700">{{ row.spare_part.safety_stock ?? '—' }} <span v-if="row.spare_part.safety_stock !== null" class="text-sm text-slate-500">{{ row.spare_part.unit_of_measure }}</span></td>
              <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-semibold" :class="meta(row.status).class"><span class="h-1.5 w-1.5 rounded-full" :class="meta(row.status).dot" aria-hidden="true" />{{ meta(row.status).label }}</span></td>
              <td class="px-5 py-3 text-right">
                <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-indigo-50 focus:ring-2 focus:ring-[#2d2a70] focus:ring-offset-2" :aria-label="`Catat IN/OUT ${row.spare_part.detail_equipment}`" @click="$emit('movement', row)">
                  <Plus :size="16" aria-hidden="true" /> Catat IN/OUT
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div data-inventory-mobile class="space-y-3 bg-slate-50 p-3 lg:hidden">
        <InventoryCard v-for="row in stocks.data" :key="row.id" :row="row" :show-unit="showUnit" @movement="$emit('movement', $event)" />
      </div>
    </template>

    <div v-else class="px-5 py-12 text-center">
      <PackageOpen :size="34" class="mx-auto text-slate-300" aria-hidden="true" />
      <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ canReset ? 'Tidak ada stok sesuai filter' : 'Belum ada stok sesuai cakupan' }}</h3>
      <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ canReset ? 'Hapus filter untuk melihat ledger stok lainnya.' : 'Catat transaksi masuk untuk mulai membentuk ledger stok.' }}</p>
      <button v-if="canReset" data-stock-reset type="button" class="mt-4 min-h-11 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-[#2d2a70] outline-none hover:bg-slate-50 focus:ring-2 focus:ring-[#2d2a70]" @click="$emit('reset')">Hapus filter stok</button>
      <button v-else-if="canRecord" data-stock-record type="button" class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#f26522] px-4 text-sm font-semibold text-white outline-none hover:bg-[#d95418] focus:ring-2 focus:ring-[#f26522] focus:ring-offset-2" @click="$emit('record')"><Plus :size="17" aria-hidden="true" /> Catat IN/OUT</button>
    </div>

    <nav v-if="!error && !loading && stocks.data.length && stocks.links?.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3" aria-label="Paginasi stok">
      <p class="text-sm text-slate-500">{{ stocks.from ?? 0 }}–{{ stocks.to ?? 0 }} dari {{ stocks.total ?? 0 }}</p>
      <div class="flex flex-wrap gap-1">
        <template v-for="link in stocks.links" :key="link.label">
          <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border px-3 text-sm font-semibold outline-none hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-[#2d2a70] focus-visible:ring-offset-2" :class="link.active ? 'border-[#2d2a70] bg-[#2d2a70] text-white hover:bg-[#171650]' : 'border-slate-200 bg-white text-slate-600'">{{ pageLabel(link.label) }}</Link>
          <span v-else class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-slate-100 px-3 text-sm text-slate-300">{{ pageLabel(link.label) }}</span>
        </template>
      </div>
    </nav>
  </section>
</template>
