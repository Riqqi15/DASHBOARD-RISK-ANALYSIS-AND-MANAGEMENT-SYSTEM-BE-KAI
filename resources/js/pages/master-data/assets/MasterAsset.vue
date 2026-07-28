<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  Boxes,
  CircleCheckBig,
  Database,
  MapPin,
  Pencil,
  Plus,
  Search,
  Trash2,
  Warehouse,
  X,
} from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import DeleteAssetDialog from './Partials/DeleteAssetDialog.vue'

const props = defineProps({
  assets: { type: Object, required: true },
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
const statusLabel = (status) => props.statusOptions.find((item) => item.value === status)?.label ?? status
const statusClass = (status) => ({
  aktif: 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
  nonaktif: 'bg-slate-100 text-slate-600 ring-slate-500/15',
  dalam_perbaikan: 'bg-amber-50 text-amber-700 ring-amber-600/15',
}[status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15')
const dateLabel = (value) => {
  if (!value) return 'Belum dicatat'

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(value))
}
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
      <div v-if="assets.data.length" class="overflow-x-auto">
        <table class="min-w-[1080px] w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Aset</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Klasifikasi</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Unit &amp; lokasi</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemasangan</th>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="asset in assets.data" :key="asset.id" class="transition hover:bg-slate-50/70">
              <td class="px-5 py-4">
                <p class="font-medium text-slate-950">{{ asset.nama_aset }}</p>
                <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">{{ asset.aset_prasarana_sintel }}</p>
              </td>
              <td class="px-5 py-4">
                <p class="text-sm font-medium text-slate-800">{{ asset.system }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ asset.subsystem }}</p>
              </td>
              <td class="px-5 py-4">
                <span class="rounded bg-blue-50 px-2 py-1 font-mono text-xs font-semibold text-[#2d2a70]">{{ asset.unit_kerja.code }}</span>
                <p class="mt-2 flex items-center gap-1.5 text-xs" :class="asset.lokasi ? 'text-slate-600' : 'italic text-slate-400'">
                  <MapPin :size="13" aria-hidden="true" />
                  {{ asset.lokasi || 'Belum dilengkapi' }}
                </p>
              </td>
              <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ dateLabel(asset.tanggal_pemasangan) }}</td>
              <td class="px-5 py-4 text-right font-mono text-sm font-semibold text-slate-800">{{ number(asset.jumlah_unit) }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(asset.status)">{{ statusLabel(asset.status) }}</span>
              </td>
              <td class="whitespace-nowrap px-5 py-4 text-right">
                <Link :href="`/master-asset/${asset.id}/edit`" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-[#2d2a70] transition hover:bg-blue-50" :aria-label="`Edit aset ${asset.nama_aset}`">
                  <Pencil :size="16" aria-hidden="true" />
                </Link>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" :aria-label="`Hapus aset ${asset.nama_aset}`" @click="assetToDelete = asset">
                  <Trash2 :size="16" aria-hidden="true" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="flex flex-col items-center px-6 py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><Database :size="24" aria-hidden="true" /></div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Aset tidak ditemukan</h3>
        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Ubah kata pencarian atau filter. Jika belum ada data, tambahkan aset pertama untuk unit kerja Anda.</p>
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
