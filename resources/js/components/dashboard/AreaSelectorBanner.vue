<template>
  <section
    v-if="currentUser.isPusat()"
    class="relative mb-8 overflow-hidden rounded-2xl border border-sky-200/80 bg-white shadow-[0_18px_50px_-35px_rgba(23,22,80,0.65)]"
    aria-labelledby="area-lintas-title"
  >
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#171650] via-[#304da1] to-[#f58220]" aria-hidden="true" />

    <header class="relative flex flex-col gap-4 border-b border-sky-100 bg-gradient-to-r from-[#eaf7fc] via-[#f4fbfe] to-white px-5 pb-5 pt-6 sm:flex-row sm:items-center sm:justify-between sm:px-7">
      <div class="flex min-w-0 items-center gap-3.5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#171650] text-white shadow-lg shadow-[#171650]/15">
          <MapPinned :size="21" :stroke-width="1.9" aria-hidden="true" />
        </span>
        <div class="min-w-0">
          <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[#f26a21]">Navigasi wilayah</p>
          <h2 id="area-lintas-title" class="mt-1 text-xl font-extrabold tracking-tight text-[#171650] sm:text-2xl">Area Lintas</h2>
        </div>
      </div>

      <div class="self-start rounded-xl border border-sky-200/80 bg-white/90 px-3.5 py-2 shadow-sm sm:self-auto" aria-live="polite">
        <span class="block text-[9px] font-bold uppercase tracking-[0.18em] text-slate-400">Area aktif</span>
        <span class="mt-0.5 flex items-center gap-1.5 text-xs font-bold text-[#171650]">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 ring-4 ring-emerald-50" aria-hidden="true" />
          {{ activeAreaLabel }}
        </span>
      </div>
    </header>

    <div class="relative bg-[radial-gradient(circle_at_top_right,rgba(186,230,253,0.34),transparent_38%)] px-5 py-5 sm:px-7 sm:py-6">
      <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.13em] text-slate-500">Pilih cakupan data</p>

      <div class="flex w-full flex-wrap gap-2.5" role="group" aria-label="Pilih area lintas">
        <button
          type="button"
          data-area-code="national"
          :aria-pressed="selectedArea === null"
          class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orange-200"
          :class="selectedArea === null
            ? 'border-[#171650] bg-[#171650] text-white shadow-lg shadow-[#171650]/20 ring-2 ring-orange-400 ring-offset-2'
            : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:-translate-y-0.5 hover:border-orange-300 hover:text-[#171650] hover:shadow-md'"
          @click="selectArea(null)"
        >
          <Check v-if="selectedArea === null" :size="15" :stroke-width="2.6" aria-hidden="true" />
          Nasional (Pusat)
        </button>

        <button
          v-for="area in units"
          :key="area.id"
          type="button"
          :data-area-code="area.code"
          :aria-pressed="isActive(area.code)"
          :title="area.name || area.code"
          class="inline-flex min-h-11 min-w-[88px] items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orange-200"
          :class="isActive(area.code)
            ? 'border-[#171650] bg-[#171650] text-white shadow-lg shadow-[#171650]/20 ring-2 ring-orange-400 ring-offset-2'
            : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:-translate-y-0.5 hover:border-orange-300 hover:text-[#171650] hover:shadow-md'"
          @click="selectArea(area.code)"
        >
          <Check v-if="isActive(area.code)" :size="15" :stroke-width="2.6" aria-hidden="true" />
          {{ area.code }}
        </button>
      </div>
    </div>

    <footer class="relative flex flex-col gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
      <div class="flex min-w-0 items-center gap-3">
        <span class="h-10 w-1 shrink-0 rounded-full bg-[#f58220]" aria-hidden="true" />
        <div class="min-w-0">
          <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Executive overview</p>
          <h1 class="mt-1 text-lg font-extrabold uppercase leading-tight tracking-[0.04em] text-[#171650] sm:text-xl lg:text-2xl">
            Dashboard Risk Analysis and Management System
          </h1>
        </div>
      </div>
      <span class="self-start rounded-lg bg-[#171650] px-3 py-1.5 text-[10px] font-bold tracking-[0.18em] text-white sm:self-auto">KAI RAMS</span>
    </footer>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Check, MapPinned } from 'lucide-vue-next'
import { useAuth } from '@/application/composables/useAuth'

const props = defineProps({
  units: {
    type: Array,
    default: () => [],
  },
  selectedArea: {
    type: String,
    default: null,
  },
})

const { currentUser } = useAuth()
const activeAreaLabel = computed(() => props.selectedArea || 'Nasional (Pusat)')
const isActive = (code) => code === props.selectedArea

const selectArea = (code) => {
  router.get(
    window.location.pathname,
    code ? { area: code } : {},
    { preserveScroll: true, preserveState: false, replace: true },
  )
}
</script>
