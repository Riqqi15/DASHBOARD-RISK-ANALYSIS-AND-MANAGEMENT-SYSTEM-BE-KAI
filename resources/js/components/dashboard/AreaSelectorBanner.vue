<template>
  <section
    v-if="currentUser.isPusat()"
    class="area-selector"
    aria-labelledby="area-selector-title"
  >
    <div class="area-selector__intro">
      <div class="flex min-w-0 items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#171650] text-white" aria-hidden="true">
          <MapPinned :size="21" :stroke-width="2" />
        </span>
        <div class="min-w-0">
          <h2 id="area-selector-title" class="text-lg font-bold tracking-tight text-slate-950 sm:text-xl">Wilayah data</h2>
          <p class="mt-1 text-sm leading-6 text-slate-600">Pilih wilayah kerja untuk menyesuaikan isi dashboard.</p>
        </div>
      </div>

      <div class="area-selector__active" aria-live="polite">
        <span class="h-2 w-2 rounded-full bg-emerald-500 ring-4 ring-emerald-100" aria-hidden="true" />
        <div>
          <span class="block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Sedang melihat</span>
          <strong class="mt-0.5 block text-base text-[#171650]">{{ activeAreaLabel }}</strong>
        </div>
      </div>
    </div>

    <div class="area-selector__control">
      <label for="area-select" class="block text-sm font-bold text-slate-800">Wilayah kerja</label>
      <div class="relative mt-2">
        <select
          id="area-select"
          :value="displayedArea || ''"
          class="area-selector__select"
          aria-describedby="area-selector-help"
          @change="selectArea($event.target.value)"
        >
          <option v-if="!units.length" value="">Belum ada wilayah</option>
          <option v-for="area in units" :key="area.id" :value="area.code" :data-area-code="area.code">
            {{ area.code }}{{ area.name && area.name !== area.code ? ` — ${area.name}` : '' }}
          </option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-500" :size="20" aria-hidden="true" />
      </div>
      <p id="area-selector-help" class="mt-2 text-sm leading-6 text-slate-600">
        Data keandalan, aset, dan laporan akan mengikuti wilayah ini.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronDown, MapPinned } from 'lucide-vue-next'
import { useAuth } from '@/application/composables/useAuth'

const props = defineProps({
  units: { type: Array, default: () => [] },
  selectedArea: { type: String, default: null },
})

const { currentUser } = useAuth()
const displayedArea = computed(() => props.selectedArea || props.units[0]?.code || null)
const activeAreaLabel = computed(() => {
  const area = props.units.find((unit) => unit.code === displayedArea.value)
  return area?.name && area.name !== area.code ? `${area.code} — ${area.name}` : (displayedArea.value || 'Pilih wilayah')
})

const selectArea = (code) => {
  if (!code) return

  router.get(
    window.location.pathname,
    { area: code },
    { preserveScroll: true, preserveState: false, replace: true },
  )
}
</script>

<style scoped>
.area-selector {
  overflow: hidden;
  margin-bottom: 2rem;
  border: 1px solid #dbe3ef;
  border-radius: 0.875rem;
  background: #fff;
  box-shadow: 0 10px 24px -22px rgb(15 23 42 / 55%);
}

.area-selector__intro {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  border-bottom: 1px solid #dbe3ef;
  padding: 1.25rem;
}

.area-selector__active {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
  border: 1px solid #bbf7d0;
  border-radius: 0.75rem;
  background: #f0fdf4;
  padding: 0.625rem 0.875rem;
}

.area-selector__control { padding: 1.25rem; }

.area-selector__select {
  display: block;
  width: 100%;
  min-height: 3.25rem;
  appearance: none;
  cursor: pointer;
  border: 1px solid #94a3b8;
  border-radius: 0.75rem;
  background: #fff;
  padding: 0.75rem 3rem 0.75rem 1rem;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.5;
}

.area-selector__select:hover { border-color: #f26522; }

.area-selector__select:focus-visible {
  outline: 3px solid #fed7aa;
  outline-offset: 2px;
  border-color: #f26522;
}

@media (max-width: 640px) {
  .area-selector__intro {
    align-items: flex-start;
    flex-direction: column;
  }

  .area-selector__active { width: 100%; }
}
</style>
