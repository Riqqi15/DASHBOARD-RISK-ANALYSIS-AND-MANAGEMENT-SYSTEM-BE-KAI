<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { Building2, ChevronRight } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import UnitForm from './Partials/UnitForm.vue'

defineProps({
  unit: { type: Object, required: true },
  typeOptions: { type: Array, required: true },
})
</script>

<template>
  <Head :title="`Edit ${unit.code}`" />
  <MainLayout>
    <nav class="mb-5 flex items-center gap-2 text-xs text-slate-500" aria-label="Breadcrumb">
      <Link href="/admin/units" class="hover:text-[#171650]">Unit Kerja</Link>
      <ChevronRight :size="14" aria-hidden="true" />
      <span class="text-slate-800">{{ unit.code }}</span>
    </nav>

    <div class="mb-6 flex items-start gap-4">
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#2d2a70]">
        <Building2 :size="22" aria-hidden="true" />
      </div>
      <div>
        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Edit unit kerja</h2>
        <p class="mt-1 text-sm text-slate-600">Perbarui identitas dan status operasional {{ unit.code }}.</p>
      </div>
    </div>

    <section class="max-w-4xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <UnitForm :unit="unit" :type-options="typeOptions" submit-label="Simpan perubahan" />
    </section>
  </MainLayout>
</template>
