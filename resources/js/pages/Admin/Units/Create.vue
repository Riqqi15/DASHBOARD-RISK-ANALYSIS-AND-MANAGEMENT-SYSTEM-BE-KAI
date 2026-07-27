<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { Building2, ChevronRight } from 'lucide-vue-next'
import MainLayout from '@/presentation/layouts/MainLayout.vue'
import UnitForm from './Partials/UnitForm.vue'

defineProps({ typeOptions: { type: Array, required: true } })
</script>

<template>
  <Head title="Tambah Unit Kerja" />
  <MainLayout>
    <nav class="mb-5 flex items-center gap-2 text-xs text-slate-500" aria-label="Breadcrumb">
      <Link href="/admin/units" class="hover:text-[#171650]">Unit Kerja</Link>
      <ChevronRight :size="14" aria-hidden="true" />
      <span class="text-slate-800">Tambah unit</span>
    </nav>

    <div class="mb-6 flex items-start gap-4">
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
        <Building2 :size="22" aria-hidden="true" />
      </div>
      <div>
        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Tambah unit kerja</h2>
        <p class="mt-1 text-sm text-slate-600">Daftarkan Daop atau Divre yang akan menggunakan KAI RAMS.</p>
      </div>
    </div>

    <section class="max-w-4xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <UnitForm :type-options="typeOptions" submit-label="Simpan unit" />
    </section>
  </MainLayout>
</template>
