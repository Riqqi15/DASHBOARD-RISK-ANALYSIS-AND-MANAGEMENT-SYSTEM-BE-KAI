<script setup>
import { ArrowDownToLine, ArrowUpFromLine } from 'lucide-vue-next'

defineProps({ row: { type: Object, required: true }, showUnit: Boolean })
defineEmits(['movement'])

const statusMeta = {
  available: { label: 'Tersedia', class: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
  below_reorder: { label: 'Di bawah reorder', class: 'border-amber-200 bg-amber-50 text-amber-800' },
  critical: { label: 'Kritis', class: 'border-red-200 bg-red-50 text-red-700' },
  empty: { label: 'Habis', class: 'border-red-300 bg-red-50 text-red-800' },
}

const meta = (status) => statusMeta[status] ?? { label: status, class: 'border-slate-200 bg-slate-50 text-slate-700' }
</script>

<template>
  <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-l-4 border-[#2d2a70] p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="font-mono text-xs font-semibold tracking-wide text-[#2d2a70]">{{ row.spare_part.code }}</p>
          <h3 class="mt-1 text-sm font-semibold leading-5 text-slate-950">{{ row.spare_part.detail_equipment }}</h3>
          <p v-if="row.spare_part.equipment" class="mt-1 text-xs text-slate-500">{{ row.spare_part.equipment }}</p>
        </div>
        <span class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold" :class="meta(row.status).class">{{ meta(row.status).label }}</span>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200">
        <div class="bg-slate-50 px-3 py-2.5">
          <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Stok tercatat</p>
          <p class="mt-1 font-mono text-lg font-semibold tabular-nums text-slate-950">{{ row.quantity }} <span class="text-xs font-normal text-slate-500">{{ row.spare_part.unit_of_measure }}</span></p>
        </div>
        <div class="bg-slate-50 px-3 py-2.5">
          <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Batas reorder</p>
          <p class="mt-1 font-mono text-lg font-semibold tabular-nums text-slate-950">{{ row.spare_part.reorder_point ?? '—' }} <span v-if="row.spare_part.reorder_point !== null" class="text-xs font-normal text-slate-500">{{ row.spare_part.unit_of_measure }}</span></p>
        </div>
      </div>

      <dl class="mt-3 space-y-2 text-xs">
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Kategori</dt><dd class="text-right font-medium text-slate-700">{{ row.spare_part.category?.subsystem?.name ?? 'Tanpa kategori' }}</dd></div>
        <div v-if="showUnit" class="flex justify-between gap-4"><dt class="text-slate-500">Unit</dt><dd class="text-right font-medium text-slate-700">{{ row.unit.code }} — {{ row.unit.name }}</dd></div>
      </dl>

      <button type="button" class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-[#171650] outline-none hover:bg-slate-50 focus:ring-2 focus:ring-[#2d2a70] focus:ring-offset-2" :aria-label="`Catat transaksi ${row.spare_part.detail_equipment}`" @click="$emit('movement', row)">
        <ArrowDownToLine :size="16" aria-hidden="true" /><ArrowUpFromLine :size="16" aria-hidden="true" /> Catat transaksi
      </button>
    </div>
  </article>
</template>
