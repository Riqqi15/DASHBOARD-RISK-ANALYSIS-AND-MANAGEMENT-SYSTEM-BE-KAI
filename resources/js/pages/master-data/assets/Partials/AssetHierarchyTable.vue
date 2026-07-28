<script setup>
import { computed, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronDown, ChevronRight, Pencil, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  rows: { type: Array, required: true },
  assets: { type: Array, required: true },
})

const emit = defineEmits(['delete'])

const collapsedGroups = reactive(new Set())
const collapsedSystems = reactive(new Set())
const number = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0)
const relation = (row, snake, camel) => row?.[snake] ?? row?.[camel] ?? null

const groups = computed(() => {
  const grouped = new Map()
  const usedSubsystems = new Set(
    props.assets.filter((asset) => asset.asset_subsystem_id != null).map((asset) => String(asset.asset_subsystem_id)),
  )

  for (const row of props.rows) {
    if (!usedSubsystems.has(String(row.id))) continue

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
    groupRow.systems.get(String(system.id)).subsystems.push({
      id: row.id,
      name: row.name,
      total: Number(row.total) || 0,
      sparepart_in: Number(row.sparepart_in) || 0,
      sparepart_out: Number(row.sparepart_out) || 0,
      assets: props.assets.filter((asset) => String(asset.asset_subsystem_id) === String(row.id)),
    })
  }

  const legacyAssets = props.assets.filter((asset) => asset.asset_subsystem_id == null)
  if (legacyAssets.length) {
    grouped.set('legacy', {
      id: 'legacy',
      name: 'Belum diklasifikasikan',
      systems: new Map([['legacy', {
        id: 'legacy',
        name: 'Belum diklasifikasikan',
        subsystems: [{
          id: 'legacy',
          name: 'Belum diklasifikasikan',
          total: legacyAssets.reduce((total, asset) => total + (Number(asset.jumlah_unit) || 0), 0),
          sparepart_in: 0,
          sparepart_out: 0,
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

      <tbody v-for="group in groups" :id="`asset-group-${group.id}-rows`" :key="group.id" class="group/body">
        <tr :data-group-id="group.id" class="bg-[#171650]/[0.04] text-slate-900">
          <td class="border-b border-slate-200 px-5 py-3 font-semibold">
            <button
              type="button"
              class="inline-flex min-h-11 items-center gap-2 rounded-md text-left text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2"
              :aria-expanded="!collapsedGroups.has(String(group.id))"
              :aria-controls="`asset-group-${group.id}-rows`"
              :aria-label="`${collapsedGroups.has(String(group.id)) ? 'Buka' : 'Tutup'} kelompok ${group.name}`"
              @click="toggle(collapsedGroups, String(group.id))"
            >
              <ChevronRight v-if="collapsedGroups.has(String(group.id))" :size="17" aria-hidden="true" />
              <ChevronDown v-else :size="17" aria-hidden="true" />
              {{ group.name }}
            </button>
          </td>
          <td colspan="2" class="border-b border-slate-200 px-5 py-3 text-xs text-slate-500">Subtotal kelompok</td>
          <td class="border-b border-slate-200 px-5 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ number(group.total) }}</td>
          <td class="border-b border-slate-200 px-5 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ number(group.sparepart_in) }}</td>
          <td class="border-b border-slate-200 px-5 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ number(group.sparepart_out) }}</td>
          <td class="border-b border-slate-200 px-5 py-3" />
        </tr>

        <template v-if="!collapsedGroups.has(String(group.id))">
          <template v-for="system in group.systems" :key="system.id">
            <tr :data-system-id="system.id" class="bg-slate-50/70 text-slate-800">
              <td class="border-b border-slate-100 px-5 py-3" />
              <td class="border-b border-slate-100 px-5 py-3 font-medium">
                <button
                  type="button"
                  class="inline-flex min-h-11 items-center gap-2 rounded-md text-left text-sm outline-none focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2"
                  :aria-expanded="!collapsedSystems.has(systemKey(group.id, system.id))"
                  :aria-controls="`asset-system-${system.id}-rows`"
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
              v-for="(subsystem, subsystemIndex) in collapsedSystems.has(systemKey(group.id, system.id)) ? [] : system.subsystems"
              :id="subsystemIndex === 0 ? `asset-system-${system.id}-rows` : undefined"
              :key="subsystem.id"
              :data-subsystem-id="subsystem.id"
              class="transition hover:bg-orange-50/30 odd:bg-white even:bg-slate-50/30 motion-reduce:transition-none"
            >
              <td class="border-b border-slate-100 px-5 py-4" />
              <td class="border-b border-slate-100 px-5 py-4" />
              <td class="border-b border-slate-100 px-5 py-4">
                <div class="border-l-2 border-[#F15A24] pl-4">
                  <p class="text-sm font-semibold text-slate-900">{{ subsystem.name }}</p>
                  <p v-for="asset in subsystem.assets" :key="asset.id" class="mt-1 text-xs text-slate-500">{{ asset.nama_aset }}</p>
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
        </template>
      </tbody>
    </table>
  </div>
</template>
