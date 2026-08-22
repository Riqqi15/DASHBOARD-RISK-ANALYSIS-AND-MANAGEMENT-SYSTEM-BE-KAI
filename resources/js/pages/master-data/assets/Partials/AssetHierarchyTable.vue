<script setup>
import { computed, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronDown, ChevronRight, Pencil, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  rows: { type: Array, required: true },
  assets: { type: Array, required: true },
  categoryTree: { type: Array, default: () => [] },
  legacySummary: { type: Object, default: null },
  statusOptions: { type: Array, required: true },
  showUnit: { type: Boolean, default: false },
})

const emit = defineEmits(['delete'])

const collapsedGroups = reactive(new Set())
const collapsedSystems = reactive(new Set())
const number = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0)
const relation = (row, snake, camel) => row?.[snake] ?? row?.[camel] ?? null
const statusLabel = (status) => props.statusOptions.find((item) => item.value === status)?.label ?? status
const statusClass = (status) => ({
  aktif: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
  nonaktif: 'bg-slate-100 text-slate-600 ring-slate-500/15',
  dalam_perbaikan: 'bg-amber-50 text-amber-700 ring-amber-600/15',
}[status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15')
const unitLabel = (asset) => {
  if (!asset.unit_kerja) return 'Unit tidak tersedia'
  const name = asset.unit_kerja.name ? ` — ${asset.unit_kerja.name}` : ''
  return `${asset.unit_kerja.code}${name}`
}
const emptyGroupRowId = (groupId) => `asset-empty-group-${groupId}`
const emptySystemRowId = (groupId, systemId) => `asset-empty-system-${groupId}-${systemId}`
const subsystemRow = (subsystem, aggregate = {}) => ({
  id: subsystem.id,
  name: subsystem.name,
  total: Number(aggregate.total) || 0,
  sparepart_in: Number(aggregate.sparepart_in) || 0,
  sparepart_out: Number(aggregate.sparepart_out) || 0,
  assets: props.assets.filter((asset) => String(asset.asset_subsystem_id) === String(subsystem.id)),
})

const groups = computed(() => {
  const grouped = new Map()

  for (const group of props.categoryTree) {
    grouped.set(String(group.id), {
      id: group.id,
      name: group.name,
      systems: new Map((group.systems ?? []).map((system) => [
        String(system.id),
        {
          id: system.id,
          name: system.name,
          subsystems: (system.subsystems ?? []).map((subsystem) => subsystemRow(subsystem)),
        },
      ])),
    })
  }

  for (const row of props.rows) {
    const system = relation(row, 'asset_system', 'assetSystem')
    const group = relation(system, 'asset_group', 'assetGroup')
    if (!system || !group) continue

    if (!grouped.has(String(group.id))) {
      grouped.set(String(group.id), { id: group.id, name: group.name, systems: new Map() })
    }
    const groupRow = grouped.get(String(group.id))
    if (!groupRow.systems.has(String(system.id))) {
      groupRow.systems.set(String(system.id), { id: system.id, name: system.name, subsystems: [] })
    }
    const systemRow = groupRow.systems.get(String(system.id))
    const subsystemIndex = systemRow.subsystems.findIndex((subsystem) => String(subsystem.id) === String(row.id))
    const merged = subsystemRow(row, row)
    if (subsystemIndex === -1) systemRow.subsystems.push(merged)
    else systemRow.subsystems[subsystemIndex] = merged
  }

  const legacyAssets = props.assets.filter((asset) => asset.asset_subsystem_id == null)
  if (props.legacySummary) {
    grouped.set('legacy', {
      id: 'legacy',
      name: 'Belum diklasifikasikan',
      systems: new Map([['legacy', {
        id: 'legacy',
        name: 'Belum diklasifikasikan',
        subsystems: [{
          id: 'legacy',
          name: 'Belum diklasifikasikan',
          total: Number(props.legacySummary.total) || 0,
          sparepart_in: Number(props.legacySummary.sparepart_in) || 0,
          sparepart_out: Number(props.legacySummary.sparepart_out) || 0,
          assets: legacyAssets,
        }],
      }]]),
    })
  }

  return [...grouped.values()].map((group) => ({
    ...group,
    systems: [...group.systems.values()].map((system) => ({
      ...system,
      total: system.subsystems.reduce((sum, item) => sum + item.total, 0),
      sparepart_in: system.subsystems.reduce((sum, item) => sum + item.sparepart_in, 0),
      sparepart_out: system.subsystems.reduce((sum, item) => sum + item.sparepart_out, 0),
    })),
  })).map((group) => ({
    ...group,
    total: group.systems.reduce((sum, item) => sum + item.total, 0),
    sparepart_in: group.systems.reduce((sum, item) => sum + item.sparepart_in, 0),
    sparepart_out: group.systems.reduce((sum, item) => sum + item.sparepart_out, 0),
  }))
})

const toggle = (collection, key) => {
  if (collection.has(key)) collection.delete(key)
  else collection.add(key)
}
const systemKey = (groupId, systemId) => `${groupId}-${systemId}`
const systemHeaderId = (groupId, systemId) => `asset-system-header-${groupId}-${systemId}`
const subsystemRowId = (subsystemId) => `asset-subsystem-row-${subsystemId}`
const systemControlIds = (groupId, system) => system.subsystems.length
  ? system.subsystems.map((subsystem) => subsystemRowId(subsystem.id)).join(' ')
  : emptySystemRowId(groupId, system.id)
const groupControlIds = (group) => group.systems.flatMap((system) => [
  systemHeaderId(group.id, system.id),
  ...(system.subsystems.length
    ? system.subsystems.map((subsystem) => subsystemRowId(subsystem.id))
    : [emptySystemRowId(group.id, system.id)]),
]).join(' ') || emptyGroupRowId(group.id)
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[1080px] border-separate border-spacing-0">
      <thead class="sticky top-0 z-10 bg-slate-50">
        <tr>
          <th v-for="heading in ['Aset Prasarana Sintel', 'System', 'Subsystem']" :key="heading" class="border-b border-slate-200 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ heading }}</th>
          <th v-for="heading in ['TOTAL', 'Sparepart IN', 'Sparepart OUT']" :key="heading" class="border-b border-slate-200 px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ heading }}</th>
          <th class="border-b border-slate-200 px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
        </tr>
      </thead>

      <tbody v-for="group in groups" :key="group.id" class="group/body">
        <tr :data-group-id="group.id" class="bg-[#171650]/[0.04] text-slate-900">
          <td class="border-b border-slate-200 px-5 py-3 font-semibold">
            <button
              type="button"
              class="inline-flex min-h-11 items-center gap-2 rounded-md text-left text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2"
              :aria-expanded="!collapsedGroups.has(String(group.id))"
              :aria-controls="groupControlIds(group)"
              :aria-label="`${collapsedGroups.has(String(group.id)) ? 'Buka' : 'Tutup'} kelompok ${group.name}`"
              @click="toggle(collapsedGroups, String(group.id))"
            >
              <ChevronRight v-if="collapsedGroups.has(String(group.id))" :size="17" aria-hidden="true" />
              <ChevronDown v-else :size="17" aria-hidden="true" />
              {{ group.name }}
            </button>
          </td>
          <td class="border-b border-slate-200 px-5 py-3 text-xs text-slate-500">Subtotal kelompok</td>
          <td class="border-b border-slate-200 px-5 py-3" />
          <td class="border-b border-slate-200 px-5 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ number(group.total) }}</td>
          <td class="border-b border-slate-200 px-5 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ number(group.sparepart_in) }}</td>
          <td class="border-b border-slate-200 px-5 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ number(group.sparepart_out) }}</td>
          <td class="border-b border-slate-200 px-5 py-3" />
        </tr>

        <tr
          v-if="!group.systems.length"
          :id="emptyGroupRowId(group.id)"
          v-show="!collapsedGroups.has(String(group.id))"
          class="bg-white"
        >
          <td class="border-b border-slate-100 px-5 py-4" />
          <td class="border-b border-slate-100 px-5 py-4 text-sm italic text-slate-400">Belum ada system aktif</td>
          <td class="border-b border-slate-100 px-5 py-4" />
          <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-400">0</td>
          <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-400">0</td>
          <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-400">0</td>
          <td class="border-b border-slate-100 px-5 py-4" />
        </tr>

        <template v-for="system in group.systems" :key="system.id">
            <tr :id="systemHeaderId(group.id, system.id)" v-show="!collapsedGroups.has(String(group.id))" :data-system-id="system.id" class="bg-slate-50/70 text-slate-800">
              <td class="border-b border-slate-100 px-5 py-3" />
              <td class="border-b border-slate-100 px-5 py-3 font-medium">
                <button
                  type="button"
                  class="inline-flex min-h-11 items-center gap-2 rounded-md text-left text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2"
                  :aria-expanded="!collapsedSystems.has(systemKey(group.id, system.id))"
                  :aria-controls="systemControlIds(group.id, system)"
                  :aria-label="`${collapsedSystems.has(systemKey(group.id, system.id)) ? 'Buka' : 'Tutup'} system ${system.name}`"
                  @click="toggle(collapsedSystems, systemKey(group.id, system.id))"
                >
                  <ChevronRight v-if="collapsedSystems.has(systemKey(group.id, system.id))" :size="16" aria-hidden="true" />
                  <ChevronDown v-else :size="16" aria-hidden="true" />
                  {{ system.name }}
                </button>
              </td>
              <td class="border-b border-slate-100 px-5 py-3 text-xs text-slate-500">Subtotal system</td>
              <td class="border-b border-slate-100 px-5 py-3 text-right font-mono text-sm font-medium tabular-nums">{{ number(system.total) }}</td>
              <td class="border-b border-slate-100 px-5 py-3 text-right font-mono text-sm font-medium tabular-nums">{{ number(system.sparepart_in) }}</td>
              <td class="border-b border-slate-100 px-5 py-3 text-right font-mono text-sm font-medium tabular-nums">{{ number(system.sparepart_out) }}</td>
              <td class="border-b border-slate-100 px-5 py-3" />
            </tr>

            <tr
              v-if="!system.subsystems.length"
              :id="emptySystemRowId(group.id, system.id)"
              v-show="!collapsedGroups.has(String(group.id)) && !collapsedSystems.has(systemKey(group.id, system.id))"
              class="bg-white"
            >
              <td class="border-b border-slate-100 px-5 py-4" />
              <td class="border-b border-slate-100 px-5 py-4" />
              <td class="border-b border-slate-100 px-5 py-4 text-sm italic text-slate-400">Belum ada subsystem aktif</td>
              <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-400">0</td>
              <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-400">0</td>
              <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-400">0</td>
              <td class="border-b border-slate-100 px-5 py-4" />
            </tr>

            <tr
              v-for="subsystem in system.subsystems"
              :id="subsystemRowId(subsystem.id)"
              :key="subsystem.id"
              :data-subsystem-id="subsystem.id"
              v-show="!collapsedGroups.has(String(group.id)) && !collapsedSystems.has(systemKey(group.id, system.id))"
              class="transition hover:bg-orange-50/30 odd:bg-white even:bg-slate-50/30 motion-reduce:transition-none"
            >
              <td class="border-b border-slate-100 px-5 py-4" />
              <td class="border-b border-slate-100 px-5 py-4" />
              <td class="border-b border-slate-100 px-5 py-4">
                <div class="border-l-2 border-[#F15A24] pl-4">
                  <p class="text-sm font-semibold text-slate-900">{{ subsystem.name }}</p>
                  <div v-if="subsystem.assets.length" class="mt-2 space-y-2">
                    <div v-for="asset in subsystem.assets" :key="asset.id" class="rounded-md bg-slate-50 px-3 py-2">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-medium text-slate-800">{{ asset.nama_aset }}</p>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset" :class="statusClass(asset.status)">{{ statusLabel(asset.status) }}</span>
                      </div>
                      <p v-if="showUnit" class="mt-1 text-[11px] font-medium text-[#171650]">{{ unitLabel(asset) }}</p>
                    </div>
                  </div>
                  <p v-else class="mt-1 text-xs italic text-slate-400">Detail aset tersedia di halaman lain</p>
                </div>
              </td>
              <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm font-semibold tabular-nums text-slate-900">{{ number(subsystem.total) }}</td>
              <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-700">{{ number(subsystem.sparepart_in) }}</td>
              <td class="border-b border-slate-100 px-5 py-4 text-right font-mono text-sm tabular-nums text-slate-700">{{ number(subsystem.sparepart_out) }}</td>
              <td class="border-b border-slate-100 px-5 py-4 text-right">
                <span v-for="asset in subsystem.assets" :key="asset.id" class="inline-flex">
                  <Link :href="`/master-asset/${asset.id}/edit`" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-[#2d2a70] outline-none transition hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-[#171650] motion-reduce:transition-none" :aria-label="`Edit aset ${asset.nama_aset}`">
                    <Pencil :size="16" aria-hidden="true" />
                  </Link>
                  <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-red-600 outline-none transition hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-600 motion-reduce:transition-none" :aria-label="`Hapus aset ${asset.nama_aset}`" @click="emit('delete', asset)">
                    <Trash2 :size="16" aria-hidden="true" />
                  </button>
                </span>
              </td>
            </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
