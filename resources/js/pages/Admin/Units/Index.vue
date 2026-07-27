<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Building2, Pencil, Plus, Search, X } from 'lucide-vue-next'
import MainLayout from '@/presentation/layouts/MainLayout.vue'

const props = defineProps({
  units: { type: Object, required: true },
  filters: { type: Object, required: true },
})

const filters = reactive({
  search: props.filters.search ?? '',
  type: props.filters.type ?? '',
  status: props.filters.status ?? '',
})

const applyFilters = () => router.get('/admin/units', filters, { preserveState: true, replace: true })
const clearFilters = () => {
  filters.search = ''
  filters.type = ''
  filters.status = ''
  applyFilters()
}

const typeLabel = (type) => type === 'daop' ? 'Daop' : 'Divre'
const paginationLabel = (label) => label
  .replace('&laquo; Previous', 'Sebelumnya')
  .replace('Next &raquo;', 'Berikutnya')
</script>

<template>
  <Head title="Unit Kerja" />
  <MainLayout>
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <p class="text-sm font-medium text-orange-600">Administrasi organisasi</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Unit Kerja</h2>
        <p class="mt-2 text-sm text-slate-600">Kelola referensi Daop dan Divre untuk pembatasan akses wilayah.</p>
      </div>
      <Link href="/admin/units/create" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#ea580c] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#c2410c]">
        <Plus :size="18" aria-hidden="true" />
        Tambah unit
      </Link>
    </div>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <form class="grid gap-3 lg:grid-cols-[minmax(15rem,1fr)_12rem_12rem_auto]" @submit.prevent="applyFilters">
        <label class="relative">
          <span class="sr-only">Cari unit</span>
          <Search :size="17" class="pointer-events-none absolute left-3 top-3 text-slate-400" aria-hidden="true" />
          <input v-model="filters.search" type="search" class="h-11 w-full rounded-lg border border-slate-300 pl-10 pr-3 text-sm outline-none focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10" placeholder="Cari kode atau nama…" />
        </label>
        <select v-model="filters.type" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-[#2d2a70]" aria-label="Filter jenis unit">
          <option value="">Semua jenis</option>
          <option value="daop">Daop</option>
          <option value="divre">Divre</option>
        </select>
        <select v-model="filters.status" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-[#2d2a70]" aria-label="Filter status unit">
          <option value="">Semua status</option>
          <option value="1">Aktif</option>
          <option value="0">Nonaktif</option>
        </select>
        <div class="flex gap-2">
          <button type="submit" class="h-11 flex-1 rounded-lg bg-[#171650] px-4 text-sm font-medium text-white hover:bg-[#24236b]">Terapkan</button>
          <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" aria-label="Hapus semua filter" @click="clearFilters">
            <X :size="18" aria-hidden="true" />
          </button>
        </div>
      </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div v-if="units.data.length" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kode</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama unit</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="unit in units.data" :key="unit.id" class="hover:bg-slate-50/70">
              <td class="whitespace-nowrap px-5 py-4"><span class="rounded bg-blue-50 px-2 py-1 font-mono text-xs font-semibold text-[#2d2a70]">{{ unit.code }}</span></td>
              <td class="px-5 py-4 text-sm font-medium text-slate-900">{{ unit.name }}</td>
              <td class="px-5 py-4 text-sm text-slate-600">{{ typeLabel(unit.type) }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="unit.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                  <span class="h-1.5 w-1.5 rounded-full" :class="unit.is_active ? 'bg-emerald-500' : 'bg-slate-400'" aria-hidden="true" />
                  {{ unit.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <Link :href="`/admin/units/${unit.id}/edit`" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-[#2d2a70] hover:bg-blue-50">
                  <Pencil :size="16" aria-hidden="true" /> Edit
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="flex flex-col items-center px-6 py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><Building2 :size="24" aria-hidden="true" /></div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Unit kerja tidak ditemukan</h3>
        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Ubah kata pencarian atau filter. Jika belum ada data, tambahkan unit kerja pertama.</p>
      </div>

      <div v-if="units.links.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
        <p class="text-xs text-slate-500">Menampilkan {{ units.from }}–{{ units.to }} dari {{ units.total }} unit</p>
        <nav class="flex flex-wrap gap-1" aria-label="Paginasi">
          <Link v-for="link in units.links" :key="`${link.label}-${link.url}`" :href="link.url || '#'" preserve-scroll class="min-w-9 rounded-lg border px-3 py-2 text-center text-xs" :class="[link.active ? 'border-[#171650] bg-[#171650] text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50', !link.url ? 'pointer-events-none opacity-40' : '']">
            {{ paginationLabel(link.label) }}
          </Link>
        </nav>
      </div>
    </section>
  </MainLayout>
</template>
