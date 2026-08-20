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
    <div class="area-selector__bar">
      <!-- Left: Area Identity & Active Status -->
      <div class="area-selector__identity">
        <span class="area-selector__icon-box" aria-hidden="true">
          <MapPinned :size="20" :stroke-width="2.2" />
        </span>
        <div class="min-w-0">
          <h2 id="area-selector-title" class="area-selector__title">Wilayah data</h2>
          <p id="area-selector-help" class="area-selector__subtitle">
            Data keandalan, aset, dan laporan akan mengikuti wilayah ini.
          </p>
        </div>
      </div>

      <!-- Right: Dropdown Selector Control -->
      <div class="area-selector__action">
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
            <option
              v-for="area in units"
              :key="area.id"
              :value="area.code"
              :data-area-code="area.code"
            >
              {{ area.code }}{{ area.name && area.name !== area.code ? ` — ${area.name}` : '' }}
            </option>
          </select>
          <ChevronDown class="area-selector__select-chevron" :size="18" aria-hidden="true" />
        </div>
      </div>
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
:global(html:has(.area-selector--collapsible)) { overflow-anchor: none; }

.area-selector {
  position: relative;
  margin-bottom: 1.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.875rem;
  background: #ffffff;
  box-shadow: 0 1px 3px 0 rgb(15 23 42 / 0.04), 0 1px 2px -1px rgb(15 23 42 / 0.04);
  transition:
    padding 200ms cubic-bezier(0.16, 1, 0.3, 1),
    background-color 200ms ease,
    border-color 200ms ease,
    box-shadow 200ms cubic-bezier(0.16, 1, 0.3, 1),
    border-radius 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.area-selector--collapsible {
  position: sticky;
  top: 76px;
  z-index: 20;
}

.area-selector--compact {
  border-radius: 0 0 0.875rem 0.875rem;
  border-top-color: transparent;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(8px);
  box-shadow: 0 8px 20px -8px rgb(15 23 42 / 0.14);
}

.area-selector__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 0.875rem 1.25rem;
  transition: padding 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.area-selector--compact .area-selector__bar {
  padding: 0.5rem 1.25rem;
}

.area-selector__identity {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.875rem;
}

.area-selector__icon-box {
  display: flex;
  height: 2.375rem;
  width: 2.375rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.625rem;
  background: #171650;
  color: #ffffff;
  box-shadow: 0 2px 6px -1px rgba(23, 22, 80, 0.25);
  transition: transform 180ms ease, height 180ms ease, width 180ms ease;
}

.area-selector--compact .area-selector__icon-box {
  height: 2rem;
  width: 2rem;
}

.area-selector__title {
  font-size: 0.9375rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: #0f172a;
}

.area-selector__subtitle {
  margin-top: 0.125rem;
  font-size: 0.8125rem;
  line-height: 1.25;
  color: #64748b;
  transition: opacity 160ms ease, max-height 160ms ease;
}

.area-selector--compact .area-selector__subtitle {
  display: none;
}

.area-selector__action {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.area-selector__label {
  font-size: 0.8125rem;
  font-weight: 700;
  color: #334155;
  white-space: nowrap;
}

.area-selector--compact .area-selector__label {
  display: none;
}

.area-selector__select-wrap {
  position: relative;
  min-width: 17rem;
}

.area-selector__select {
  display: block;
  width: 100%;
  min-height: 2.5rem;
  appearance: none;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  border-radius: 0.625rem;
  background: #f8fafc;
  padding: 0.4rem 2.25rem 0.4rem 0.875rem;
  color: #0f172a;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
  transition: border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
}

.area-selector__select:hover {
  background: #ffffff;
  border-color: #f26522;
}

.area-selector__select:focus-visible {
  background: #ffffff;
  border-color: #f26522;
  outline: none;
  box-shadow: 0 0 0 3px rgba(242, 101, 34, 0.16);
}

.area-selector__select-chevron {
  pointer-events: none;
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
}

@media (prefers-reduced-motion: reduce) {
  .area-selector,
  .area-selector__bar,
  .area-selector__icon-box,
  .area-selector__subtitle { transition: none; }
}
</style>
