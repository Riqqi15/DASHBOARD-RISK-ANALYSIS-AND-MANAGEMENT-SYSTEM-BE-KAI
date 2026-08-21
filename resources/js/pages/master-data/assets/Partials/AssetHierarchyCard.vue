<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Pencil, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  rows: { type: Array, required: true },
  assets: { type: Array, required: true },
  categoryTree: { type: Array, default: () => [] },
  legacySummary: { type: Object, default: null },
  statusOptions: { type: Array, required: true },
  showUnit: { type: Boolean, default: false },
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
const unitLabel = (asset) => asset.unit_kerja
  ? `${asset.unit_kerja.code}${asset.unit_kerja.name ? ` — ${asset.unit_kerja.name}` : ''}`
  : 'Unit tidak tersedia'

const cards = computed(() => {
  const rowById = new Map(props.rows.map((row) => [String(row.id), row]))
  const seenSubsystems = new Set()
  const scopedCards = []
  const buildSubsystemCard = (row, group, system) => {
    seenSubsystems.add(String(row.id))

    return {
      id: row.id,
      name: row.name,
      breadcrumb: [group?.name, system?.name, row.name].filter(Boolean).join(' / '),
      total: row.total ?? 0,
      sparepart_in: row.sparepart_in ?? 0,
      sparepart_out: row.sparepart_out ?? 0,
      assets: props.assets.filter((asset) => String(asset.asset_subsystem_id) === String(row.id)),
    }
  }

  for (const group of props.categoryTree) {
    if (!(group.systems ?? []).length) {
      scopedCards.push({
        id: `group-${group.id}`,
        name: group.name,
        breadcrumb: group.name,
        total: 0,
        sparepart_in: 0,
        sparepart_out: 0,
        assets: [],
        emptyMessage: 'Belum ada system aktif',
      })
      continue
    }

    for (const system of group.systems ?? []) {
      if (!(system.subsystems ?? []).length) {
        scopedCards.push({
          id: `system-${system.id}`,
          name: system.name,
          breadcrumb: [group.name, system.name].join(' / '),
          total: 0,
          sparepart_in: 0,
          sparepart_out: 0,
          assets: [],
          emptyMessage: 'Belum ada subsystem aktif',
        })
        continue
      }

      for (const subsystem of system.subsystems ?? []) {
        scopedCards.push(buildSubsystemCard(rowById.get(String(subsystem.id)) ?? subsystem, group, system))
      }
    }
  }

  for (const row of props.rows) {
    if (seenSubsystems.has(String(row.id))) continue
    const system = relation(row, 'asset_system', 'assetSystem')
    const group = relation(system, 'asset_group', 'assetGroup')
    scopedCards.push(buildSubsystemCard(row, group, system))
  }

  if (props.legacySummary) {
    scopedCards.push({
      id: 'legacy',
      name: 'Belum diklasifikasikan',
      breadcrumb: 'Belum diklasifikasikan',
      total: props.legacySummary.total ?? 0,
      sparepart_in: props.legacySummary.sparepart_in ?? 0,
      sparepart_out: props.legacySummary.sparepart_out ?? 0,
      assets: props.assets.filter((asset) => asset.asset_subsystem_id == null),
    })
  }

  return scopedCards
})
</script>

<template>
  <div class="grid gap-4">
    <article v-for="card in cards" :key="card.id" :data-subsystem-card="card.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="border-l-4 border-[#F15A24] p-4">
        <p class="text-xs leading-5 text-slate-500">{{ card.breadcrumb }}</p>
        <h3 class="mt-2 font-semibold text-slate-950">{{ card.name }}</h3>

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

        <p v-if="card.emptyMessage" class="mt-4 text-sm italic text-slate-400">{{ card.emptyMessage }}</p>
        <div v-else-if="card.assets.length" class="mt-4 space-y-3">
          <div v-for="asset in card.assets" :key="asset.id" data-asset-detail class="rounded-lg border border-slate-200 p-3">
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm font-semibold text-slate-900">{{ asset.nama_aset }}</p>
              <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(asset.status)">{{ statusLabel(asset.status) }}</span>
            </div>
            <p v-if="showUnit" class="mt-1.5 text-xs font-medium text-[#171650]">{{ unitLabel(asset) }}</p>
            <div class="mt-2 flex justify-end gap-1 border-t border-slate-100 pt-2">
              <Link :href="`/master-asset/${asset.id}/edit`" class="inline-flex h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-[#2d2a70] outline-none transition hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-[#171650] motion-reduce:transition-none" :aria-label="`Edit aset ${asset.nama_aset}`">
                <Pencil :size="16" aria-hidden="true" />
                Edit
              </Link>
              <button type="button" class="inline-flex h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-red-600 outline-none transition hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-600 motion-reduce:transition-none" :aria-label="`Hapus aset ${asset.nama_aset}`" @click="emit('delete', asset)">
                <Trash2 :size="16" aria-hidden="true" />
                Hapus
              </button>
            </div>
          </div>
        </div>
        <p v-else class="mt-4 text-sm italic text-slate-400">Detail aset tersedia di halaman lain</p>
      </div>
    </article>
  </div>
</template>
