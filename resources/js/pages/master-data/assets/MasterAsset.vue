<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  Boxes,
  CircleCheckBig,
  Database,
  Plus,
  Search,
  Warehouse,
  X,
} from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import AssetHierarchyCard from './Partials/AssetHierarchyCard.vue'
import AssetHierarchyTable from './Partials/AssetHierarchyTable.vue'
import DeleteAssetDialog from './Partials/DeleteAssetDialog.vue'

const props = defineProps({
  assets: { type: Object, required: true },
  hierarchy: { type: Array, required: true },
  legacySummary: { type: Object, default: null },
  stats: { type: Object, required: true },
  filters: { type: Object, required: true },
  units: { type: Array, required: true },
  statusOptions: { type: Array, required: true },
  can: { type: Object, required: true },
})

const filters = reactive({
  search: props.filters.search ?? '',
  status: props.filters.status ?? '',
  unit_kerja_id: props.filters.unit_kerja_id ?? '',
})
const assetToDelete = ref(null)
const deleting = ref(false)
const hasActiveFilters = computed(() => Boolean(
  filters.search || filters.status || filters.unit_kerja_id,
))

const applyFilters = () => router.get('/master-asset', filters, {
  preserveState: true,
  replace: true,
})

const clearFilters = () => {
  filters.search = ''
  filters.status = ''
  filters.unit_kerja_id = ''
  applyFilters()
}

const confirmDelete = () => {
  if (!assetToDelete.value || deleting.value) return

  deleting.value = true
  router.delete(`/master-asset/${assetToDelete.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleting.value = false
      assetToDelete.value = null
    },
  })
}

const number = (value) => new Intl.NumberFormat('id-ID').format(value ?? 0)
const paginationLabel = (label) => label
  .replace('&laquo; Previous', 'Sebelumnya')
  .replace('Next &raquo;', 'Berikutnya')
</script>

<template>
  <Head title="Master Aset" />
  <MainLayout>
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <p class="text-sm font-medium text-orange-600">Data referensi operasional</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Master Aset</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
          Kelola identitas aset Sintel, jumlah unit, lokasi, dan status operasional sesuai wilayah akses Anda.
        </p>
      </div>
      <Link href="/master-asset/create" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#ea580c] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#c2410c]">
        <Plus :size="18" aria-hidden="true" />
        Tambah aset
      </Link>
    </div>

    <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan Master Aset">
      <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total aset</p>
          <span class="rounded-lg bg-blue-50 p-2 text-[#2d2a70]"><Database :size="18" aria-hidden="true" /></span>
        </div>
        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ number(stats.total_assets) }}</p>
        <p class="mt-1 text-xs text-slate-500">Baris aset sesuai filter</p>
      </article>
      <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah unit</p>
          <span class="rounded-lg bg-orange-50 p-2 text-orange-600"><Boxes :size="18" aria-hidden="true" /></span>
        </div>
        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ number(stats.total_units) }}</p>
        <p class="mt-1 text-xs text-slate-500">Akumulasi peralatan</p>
      </article>
      <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Aset aktif</p>
          <span class="rounded-lg bg-emerald-50 p-2 text-emerald-600"><CircleCheckBig :size="18" aria-hidden="true" /></span>
        </div>
        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ number(stats.active_assets) }}</p>
        <p class="mt-1 text-xs text-slate-500">Status siap digunakan</p>
      </article>
      <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Subsystem</p>
          <span class="rounded-lg bg-violet-50 p-2 text-violet-600"><Warehouse :size="18" aria-hidden="true" /></span>
        </div>
        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ number(stats.unique_subsystems) }}</p>
        <p class="mt-1 text-xs text-slate-500">Jenis subsystem unik</p>
      </article>
    </section>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <form class="grid gap-3" :class="can.choose_unit ? 'lg:grid-cols-[minmax(16rem,1fr)_13rem_15rem_auto]' : 'lg:grid-cols-[minmax(16rem,1fr)_15rem_auto]'" @submit.prevent="applyFilters">
        <label class="relative">
          <span class="sr-only">Cari aset</span>
          <Search :size="17" class="pointer-events-none absolute left-3 top-3 text-slate-400" aria-hidden="true" />
          <input id="asset-search" v-model="filters.search" type="search" class="h-11 w-full rounded-lg border border-slate-300 pl-10 pr-3 text-sm outline-none transition focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10" placeholder="Cari nama, system, subsystem, atau lokasi..." />
        </label>
        <select v-if="can.choose_unit" id="asset-unit" v-model="filters.unit_kerja_id" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-[#2d2a70]" aria-label="Filter unit kerja">
          <option value="">Semua unit kerja</option>
          <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">{{ unit.code }} — {{ unit.name }}</option>
        </select>
        <select id="asset-status" v-model="filters.status" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-[#2d2a70]" aria-label="Filter status aset">
          <option value="">Semua status</option>
          <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <div class="flex gap-2">
          <button type="submit" class="h-11 flex-1 rounded-lg bg-[#171650] px-4 text-sm font-medium text-white transition hover:bg-[#24236b]">Terapkan</button>
          <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-300 text-slate-600 transition hover:bg-slate-50" aria-label="Hapus semua filter" @click="clearFilters">
            <X :size="18" aria-hidden="true" />
          </button>
        </div>
      </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div v-if="assets.data.length">
        <div data-desktop-hierarchy class="hidden md:block">
          <AssetHierarchyTable :rows="hierarchy" :assets="assets.data" :legacy-summary="legacySummary" :status-options="statusOptions" :show-unit="can.choose_unit" @delete="assetToDelete = $event" />
        </div>
        <div data-mobile-hierarchy class="bg-slate-50 p-3 md:hidden">
          <AssetHierarchyCard :rows="hierarchy" :assets="assets.data" :legacy-summary="legacySummary" :status-options="statusOptions" :show-unit="can.choose_unit" @delete="assetToDelete = $event" />
        </div>
      </div>

      <div v-else class="flex flex-col items-center px-6 py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><Database :size="24" aria-hidden="true" /></div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ hasActiveFilters ? 'Tidak ada aset sesuai filter' : 'Belum ada aset' }}</h3>
        <p v-if="hasActiveFilters" class="mt-2 max-w-md text-sm leading-6 text-slate-500">Ubah pencarian atau hapus filter untuk melihat aset lain.</p>
        <p v-else class="mt-2 max-w-md text-sm leading-6 text-slate-500">Tambahkan aset pertama untuk mulai menyusun hierarki wilayah Anda.</p>
        <button v-if="hasActiveFilters" data-clear-empty-filters type="button" class="mt-4 inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 outline-none transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#171650]" @click="clearFilters">Hapus filter</button>
        <Link v-else href="/master-asset/create" class="mt-4 inline-flex h-11 items-center justify-center rounded-lg bg-[#F15A24] px-4 text-sm font-semibold text-white outline-none transition hover:bg-[#d94c1a] focus-visible:ring-2 focus-visible:ring-[#F15A24] focus-visible:ring-offset-2">Tambah aset pertama</Link>
      </div>

      <div v-if="assets.links.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
        <p class="text-xs text-slate-500">Menampilkan {{ assets.from }}–{{ assets.to }} dari {{ number(assets.total) }} aset</p>
        <nav class="flex flex-wrap gap-1" aria-label="Paginasi Master Aset">
          <Link v-for="link in assets.links" :key="`${link.label}-${link.url}`" :href="link.url || '#'" preserve-scroll class="min-w-9 rounded-lg border px-3 py-2 text-center text-xs" :class="[link.active ? 'border-[#171650] bg-[#171650] text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50', !link.url ? 'pointer-events-none opacity-40' : '']">
            {{ paginationLabel(link.label) }}
          </Link>
        </nav>
      </div>
    </section>

    <DeleteAssetDialog
      :asset="assetToDelete"
      :processing="deleting"
      @close="assetToDelete = null"
      @confirm="confirmDelete"
    />
  </MainLayout>
</template>
