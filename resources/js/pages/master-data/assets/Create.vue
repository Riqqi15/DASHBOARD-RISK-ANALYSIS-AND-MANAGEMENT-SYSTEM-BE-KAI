<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { Boxes, ChevronRight } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import AssetForm from './Partials/AssetForm.vue'

defineProps({
  units: { type: Array, required: true },
  categories: { type: Array, required: true },
  statusOptions: { type: Array, required: true },
  can: { type: Object, required: true },
})
</script>

<template>
  <Head><title>Tambah Master Aset</title></Head>
  <MainLayout>
    <nav class="mb-5 flex items-center gap-2 text-xs text-slate-500" aria-label="Breadcrumb">
      <Link href="/master-asset" class="hover:text-[#171650]">Master Aset</Link>
      <ChevronRight :size="14" aria-hidden="true" />
      <span class="text-slate-800">Tambah aset</span>
    </nav>

    <div class="mb-6 flex items-start gap-4">
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
        <Boxes :size="22" aria-hidden="true" />
      </div>
      <div>
        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Tambah Master Aset</h2>
        <p class="mt-1 text-sm text-slate-600">Catat aset prasarana baru beserta klasifikasi dan data operasionalnya.</p>
      </div>
    </div>

    <section class="max-w-5xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <AssetForm :units="units" :categories="categories" :status-options="statusOptions" :can="can" submit-label="Simpan aset" />
    </section>
  </MainLayout>
</template>
