<script setup>
import { AlertTriangle, Boxes, PackageCheck, ScrollText } from 'lucide-vue-next'

defineProps({ stats: { type: Object, required: true } })

const items = [
  { key: 'total_parts', label: 'Total Jenis', hint: 'jenis suku cadang', icon: Boxes, tone: 'text-[#2d2a70] bg-indigo-50' },
  { key: 'total_quantity', label: 'Total Unit Tersedia', hint: 'unit fisik tercatat', icon: PackageCheck, tone: 'text-emerald-700 bg-emerald-50' },
  { key: 'below_reorder', label: 'Di Bawah Reorder Point', hint: 'baris stok perlu perhatian', icon: AlertTriangle, tone: 'text-amber-700 bg-amber-50' },
  { key: 'movements_this_month', label: 'Transaksi Bulan Ini', hint: 'catatan pergerakan', icon: ScrollText, tone: 'text-slate-700 bg-slate-100' },
]

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value ?? 0))
</script>

<template>
  <section class="grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 shadow-sm sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan inventori">
    <article v-for="item in items" :key="item.key" class="bg-white p-4 sm:p-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">{{ item.label }}</p>
          <p class="mt-3 font-mono text-2xl font-semibold tabular-nums text-slate-950">{{ formatNumber(stats[item.key]) }}</p>
          <p class="mt-1 text-sm text-slate-500">{{ item.hint }}</p>
        </div>
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="item.tone" aria-hidden="true">
          <component :is="item.icon" :size="19" :stroke-width="1.8" />
        </span>
      </div>
    </article>
  </section>
</template>
