<script setup>
import { computed } from 'vue'
import { RotateCcw, Search } from 'lucide-vue-next'

const props = defineProps({
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
  units: { type: Array, default: () => [] },
  showUnit: Boolean,
  activeTab: { type: String, required: true },
  canReset: Boolean,
})

const emit = defineEmits(['change', 'reset'])

const subsystems = computed(() => props.categories
  .filter((group) => !props.filters.asset_group_id || String(group.id) === String(props.filters.asset_group_id))
  .flatMap((group) =>
  (group.systems ?? []).flatMap((system) => (system.subsystems ?? []).map((subsystem) => ({
    ...subsystem,
    label: `${system.name} / ${subsystem.name}`,
  }))),
))

const update = (key, event) => emit('change', { key, value: event.target.value })
const updateGroup = (event) => {
  update('asset_group_id', event)
  if (props.filters.asset_subsystem_id) emit('change', { key: 'asset_subsystem_id', value: '' })
}
const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filter inventori">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <div class="md:col-span-2">
        <label for="inventory-search" class="mb-1.5 block text-sm font-semibold text-slate-700">Cari suku cadang</label>
        <div class="relative">
          <Search :size="17" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
          <input
            id="inventory-search"
            name="search"
            type="search"
            :value="filters.search"
            placeholder="Kode, nama, kategori, unit, atau referensi…"
            :class="[inputClass, 'pl-10']"
            @input="update('search', $event)"
          />
        </div>
      </div>

      <div v-if="showUnit">
        <label for="inventory-unit" class="mb-1.5 block text-sm font-semibold text-slate-700">Unit kerja</label>
        <select id="inventory-unit" name="unit_kerja_id" :value="filters.unit_kerja_id" :class="inputClass" @change="update('unit_kerja_id', $event)">
          <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">{{ unit.code }} — {{ unit.name }}</option>
        </select>
      </div>

      <div>
        <label for="inventory-group" class="mb-1.5 block text-sm font-semibold text-slate-700">Kelompok aset</label>
        <select id="inventory-group" name="asset_group_id" :value="filters.asset_group_id" :class="inputClass" @change="updateGroup">
          <option value="">Semua kelompok</option>
          <option v-for="group in categories" :key="group.id" :value="String(group.id)">{{ group.name }}</option>
        </select>
      </div>

      <div>
        <label for="inventory-subsystem" class="mb-1.5 block text-sm font-semibold text-slate-700">Subsystem</label>
        <select id="inventory-subsystem" name="asset_subsystem_id" :value="filters.asset_subsystem_id" :class="inputClass" :disabled="!categories.length" @change="update('asset_subsystem_id', $event)">
          <option value="">Semua subsystem</option>
          <option v-for="subsystem in subsystems" :key="subsystem.id" :value="String(subsystem.id)">{{ subsystem.label }}</option>
        </select>
      </div>

      <div v-if="activeTab === 'stock'">
        <label for="inventory-status" class="mb-1.5 block text-sm font-semibold text-slate-700">Kondisi stok</label>
        <select id="inventory-status" name="stock_status" :value="filters.stock_status" :class="inputClass" @change="update('stock_status', $event)">
          <option value="all">Semua kondisi</option>
          <option value="available">Tersedia</option>
          <option value="below_reorder">Di bawah reorder point</option>
          <option value="critical">Kritis</option>
          <option value="empty">Habis</option>
        </select>
      </div>

      <template v-if="activeTab === 'history'">
        <div>
          <label for="movement-type-filter" class="mb-1.5 block text-sm font-semibold text-slate-700">Jenis transaksi</label>
          <select id="movement-type-filter" name="movement_type" :value="filters.movement_type" :class="inputClass" @change="update('movement_type', $event)">
            <option value="">Semua jenis</option>
            <option value="in">Masuk</option>
            <option value="out">Keluar</option>
            <option value="opening">Stok awal</option>
            <option value="correction">Koreksi</option>
          </select>
        </div>
        <div>
          <label for="movement-date-from" class="mb-1.5 block text-sm font-semibold text-slate-700">Dari tanggal</label>
          <input id="movement-date-from" name="date_from" type="date" :value="filters.date_from" :class="inputClass" @change="update('date_from', $event)" />
        </div>
        <div>
          <label for="movement-date-to" class="mb-1.5 block text-sm font-semibold text-slate-700">Sampai tanggal</label>
          <input id="movement-date-to" name="date_to" type="date" :value="filters.date_to" :class="inputClass" @change="update('date_to', $event)" />
        </div>
      </template>
    </div>

    <div v-if="canReset" class="mt-4 flex justify-end border-t border-slate-100 pt-3">
      <button data-reset-filters type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-slate-600 outline-none hover:bg-slate-100 focus:ring-2 focus:ring-[#2d2a70] focus:ring-offset-2" @click="emit('reset')">
        <RotateCcw :size="16" aria-hidden="true" /> Hapus filter aktif
      </button>
    </div>
  </section>
</template>
