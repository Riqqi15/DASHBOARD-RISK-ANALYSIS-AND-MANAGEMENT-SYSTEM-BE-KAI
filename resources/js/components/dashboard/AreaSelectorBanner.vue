<template>
  <section
    v-if="currentUser.isPusat()"
    ref="selector"
    :class="[
      'area-selector',
      {
        'area-selector--collapsible': props.collapsible,
        'area-selector--compact': props.collapsible && isCompact,
      },
    ]"
    aria-labelledby="area-selector-title"
  >
    <div class="area-selector__intro">
      <div class="area-selector__intro-inner">
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
    </div>

    <div class="area-selector__control">
      <div class="area-selector__compact-label" aria-hidden="true">
        <MapPinned :size="18" :stroke-width="2" />
        <span>Wilayah</span>
      </div>

      <div class="area-selector__field">
        <label for="area-select" class="area-selector__label">Wilayah kerja</label>
        <div class="area-selector__select-wrap">
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
      </div>

      <p id="area-selector-help" class="area-selector__help">
        Data keandalan, aset, dan laporan akan mengikuti wilayah ini.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronDown, MapPinned } from 'lucide-vue-next'
import { useAuth } from '@/application/composables/useAuth'

const props = defineProps({
  units: { type: Array, default: () => [] },
  selectedArea: { type: String, default: null },
  collapsible: { type: Boolean, default: false },
})

const APP_HEADER_HEIGHT = 76
const { currentUser } = useAuth()
const selector = ref(null)
const isCompact = ref(false)
let collapsePoint = 0
let frameRequested = false
let shouldMeasure = false
let isMounted = false

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

const documentTop = (element) => {
  let node = element
  let top = 0

  while (node) {
    top += node.offsetTop || 0
    node = node.offsetParent
  }

  return top || element.getBoundingClientRect().top + window.scrollY
}

const measureCollapsePoint = () => {
  if (!selector.value) return
  collapsePoint = Math.max(0, documentTop(selector.value) - APP_HEADER_HEIGHT)
}

const updateCompactState = () => {
  isCompact.value = window.scrollY >= collapsePoint && collapsePoint > 0
}

const scheduleUpdate = (measure = false) => {
  shouldMeasure ||= measure
  if (frameRequested) return

  frameRequested = true
  window.requestAnimationFrame(() => {
    frameRequested = false
    if (!isMounted) return

    if (shouldMeasure) {
      measureCollapsePoint()
      shouldMeasure = false
    }
    updateCompactState()
  })
}

const onScroll = () => scheduleUpdate()
const onResize = () => scheduleUpdate(true)

onMounted(() => {
  if (!props.collapsible) return

  isMounted = true
  measureCollapsePoint()
  updateCompactState()
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onResize, { passive: true })
})

onBeforeUnmount(() => {
  isMounted = false
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
/* Keep Chrome from counter-scrolling when the sticky panel changes height. */
:global(html:has(.area-selector--collapsible)) { overflow-anchor: none; }

.area-selector {
  overflow: hidden;
  margin-bottom: 2rem;
  border: 1px solid #dbe3ef;
  border-radius: 0.875rem;
  background: #fff;
  box-shadow: 0 10px 24px -22px rgb(15 23 42 / 55%);
}

.area-selector--collapsible {
  position: sticky;
  top: 76px;
  z-index: 20;
  transition:
    border-radius 200ms cubic-bezier(0.16, 1, 0.3, 1),
    box-shadow 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.area-selector--compact {
  border-radius: 0 0 0.875rem 0.875rem;
  box-shadow: 0 12px 24px -18px rgb(15 23 42 / 45%);
}

.area-selector__intro {
  display: grid;
  grid-template-rows: minmax(0, 1fr);
  overflow: hidden;
  border-bottom: 1px solid #dbe3ef;
  opacity: 1;
  transition:
    grid-template-rows 200ms cubic-bezier(0.16, 1, 0.3, 1),
    opacity 120ms ease-out,
    border-color 160ms ease-out;
}

.area-selector__intro-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  min-height: 0;
  overflow: hidden;
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

.area-selector__control {
  padding: 1.25rem;
}

.area-selector__compact-label { display: none; }

.area-selector__label {
  display: block;
  color: #1e293b;
  font-size: 0.875rem;
  font-weight: 700;
}

.area-selector__select-wrap {
  position: relative;
  margin-top: 0.5rem;
}

.area-selector__help {
  margin-top: 0.5rem;
  color: #475569;
  font-size: 0.875rem;
  line-height: 1.5rem;
}

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

.area-selector--compact .area-selector__intro {
  grid-template-rows: minmax(0, 0fr);
  border-bottom-color: transparent;
  opacity: 0;
}

.area-selector--compact .area-selector__control {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.75rem;
  min-height: 3.25rem;
  padding: 0.375rem 0.75rem;
}

.area-selector--compact .area-selector__compact-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #171650;
  font-size: 0.875rem;
  font-weight: 700;
}

.area-selector--compact .area-selector__label,
.area-selector--compact .area-selector__help { display: none; }

.area-selector--compact .area-selector__select-wrap { margin-top: 0; }

.area-selector--compact .area-selector__select {
  min-height: 2.5rem;
  border-color: #cbd5e1;
  padding-top: 0.375rem;
  padding-bottom: 0.375rem;
  font-size: 0.875rem;
}

.area-selector__select:hover { border-color: #f26522; }

.area-selector__select:focus-visible {
  outline: 3px solid #fed7aa;
  outline-offset: 2px;
  border-color: #f26522;
}

@media (max-width: 640px) {
  .area-selector__intro-inner {
    align-items: flex-start;
    flex-direction: column;
  }

  .area-selector__active { width: 100%; }

  .area-selector--compact .area-selector__compact-label span { display: none; }

  .area-selector--compact .area-selector__control { gap: 0.5rem; }
}

@media (prefers-reduced-motion: reduce) {
  .area-selector--collapsible,
  .area-selector__intro { transition: none; }
}
</style>
