<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { MapPin, Pencil, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  rows: { type: Array, required: true },
  assets: { type: Array, required: true },
  statusOptions: { type: Array, required: true },
})

const emit = defineEmits(['delete'])
const number = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0)
const relation = (row, snake, camel) => row?.[snake] ?? row?.[camel] ?? null
const statusLabel = (status) => props.statusOptions.find((item) => item.value === status)?.label ?? status
const statusClass = (status) => ({
  aktif: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
  nonaktif: 'bg-slate-100 text-slate-600 ring-slate-500/15',
  dalam_perbaikan: 'bg-amber-50 text-amber-700 ring-amber-600/15',
}[status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15')

const cards = computed(() => props.assets.map((asset) => {
  const row = props.rows.find((item) => String(item.id) === String(asset.asset_subsystem_id))
  const system = relation(row, 'asset_system', 'assetSystem')
  const group = relation(system, 'asset_group', 'assetGroup')
  const category = asset.category
  const names = row && system && group
    ? [group.name, system.name, row.name]
    : category
      ? [category.group.name, category.system.name, category.subsystem.name]
      : ['Belum diklasifikasikan']

  return {
    asset,
    breadcrumb: names.join(' / '),
    total: row?.total ?? asset.jumlah_unit ?? 0,
    sparepart_in: row?.sparepart_in ?? 0,
    sparepart_out: row?.sparepart_out ?? 0,
  }
}))
</script>

<template>
  <div class="grid gap-4">
    <article v-for="card in cards" :key="card.asset.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="border-l-4 border-[#F15A24] p-4">
        <p class="text-xs leading-5 text-slate-500">{{ card.breadcrumb }}</p>
        <div class="mt-3 flex items-start justify-between gap-3">
          <div>
            <h3 class="font-semibold text-slate-950">{{ card.asset.nama_aset }}</h3>
            <p class="mt-1.5 flex items-center gap-1.5 text-xs" :class="card.asset.lokasi ? 'text-slate-600' : 'italic text-slate-400'">
              <MapPin :size="13" aria-hidden="true" />
              {{ card.asset.lokasi || 'Belum dilengkapi' }}
            </p>
          </div>
          <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(card.asset.status)">{{ statusLabel(card.asset.status) }}</span>
        </div>

        <dl class="mt-4 grid grid-cols-3 divide-x divide-slate-200 rounded-lg bg-slate-50 py-3 text-center">
          <div>
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">TOTAL</dt>
            <dd class="mt-1 font-mono text-base font-semibold tabular-nums text-slate-950">{{ number(card.total) }}</dd>
          </div>
          <div>
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Sparepart IN</dt>
            <dd class="mt-1 font-mono text-base font-semibold tabular-nums text-slate-950">{{ number(card.sparepart_in) }}</dd>
          </div>
          <div>
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Sparepart OUT</dt>
            <dd class="mt-1 font-mono text-base font-semibold tabular-nums text-slate-950">{{ number(card.sparepart_out) }}</dd>
          </div>
        </dl>

        <div class="mt-3 flex justify-end gap-1 border-t border-slate-100 pt-3">
          <Link :href="`/master-asset/${card.asset.id}/edit`" class="inline-flex h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-[#2d2a70] outline-none transition hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-[#171650] motion-reduce:transition-none" :aria-label="`Edit aset ${card.asset.nama_aset}`">
            <Pencil :size="16" aria-hidden="true" />
            Edit
          </Link>
          <button type="button" class="inline-flex h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-red-600 outline-none transition hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-600 motion-reduce:transition-none" :aria-label="`Hapus aset ${card.asset.nama_aset}`" @click="emit('delete', card.asset)">
            <Trash2 :size="16" aria-hidden="true" />
            Hapus
          </button>
        </div>
      </div>
    </article>
  </div>
</template>
