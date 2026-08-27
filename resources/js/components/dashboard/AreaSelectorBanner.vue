<template>
  <section
    data-dashboard-command-bar
    class="area-selector area-selector--sticky"
    aria-labelledby="dashboard-command-title"
  >
    <div class="area-selector__bar">
      <div class="area-selector__identity">
        <h1 id="dashboard-command-title" class="area-selector__title">Dashboard Persinyalan</h1>
        <p class="area-selector__region">{{ activeAreaLabel }}</p>
      </div>

      <div class="area-selector__controls">
        <p class="area-selector__status" aria-live="polite">
          <strong>{{ failureCount }}</strong> gangguan tercatat
        </p>

        <div v-if="currentUser.isPusat()" class="area-selector__action">
          <label for="area-select" class="sr-only">Wilayah kerja</label>
          <div class="area-selector__select-wrap">
            <select
              id="area-select"
              :value="displayedArea || ''"
              class="area-selector__select"
              @change="selectArea($event.target.value)"
            >
              <option v-if="!units.length" value="">Belum ada wilayah</option>
              <option
                v-for="area in units"
                :key="area.id"
                :value="area.code"
                :data-area-code="area.code"
              >
                {{ areaLabel(area) }}
              </option>
            </select>
            <ChevronDown class="area-selector__select-chevron" :size="18" aria-hidden="true" />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'
import { useAuth } from '@/application/composables/useAuth'

const props = defineProps({
  units: { type: Array, default: () => [] },
  selectedArea: { type: String, default: null },
  failureCount: { type: [String, Number], default: 0 },
})

const { currentUser } = useAuth()

const displayedArea = computed(() => props.selectedArea || props.units[0]?.code || null)
const activeUnit = computed(() => props.units.find((unit) => unit.code === displayedArea.value))

const areaLabel = (area) => {
  if (!area) return 'Wilayah belum dipilih'
  return `${area.code}${area.name && area.name !== area.code ? ` — ${area.name}` : ''}`
}

const activeAreaLabel = computed(() => (
  activeUnit.value ? areaLabel(activeUnit.value) : displayedArea.value || 'Wilayah belum dipilih'
))

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
  margin-bottom: 1.75rem;
  border-bottom: 1px solid #dbe3ee;
  background: #ffffff;
}

.area-selector--sticky {
  position: sticky;
  top: 76px;
  z-index: 20;
}

.area-selector__bar {
  display: flex;
  min-height: 4.25rem;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 0.75rem 1.25rem;
}

.area-selector__identity {
  min-width: 0;
}

.area-selector__title {
  color: #0f172a;
  font-size: 1rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  line-height: 1.3;
}

.area-selector__region {
  margin-top: 0.125rem;
  overflow: hidden;
  color: #64748b;
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.area-selector__controls {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 1.25rem;
}

.area-selector__status {
  color: #475569;
  font-size: 0.8125rem;
  line-height: 1.4;
  white-space: nowrap;
}

.area-selector__status strong {
  color: #0f172a;
  font-size: 0.9375rem;
  font-weight: 750;
}

.area-selector__action {
  flex-shrink: 0;
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
  border-radius: 0.375rem;
  background: #ffffff;
  padding: 0.4rem 2.5rem 0.4rem 0.75rem;
  color: #0f172a;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}

.area-selector__select:hover {
  border-color: #94a3b8;
}

.area-selector__select:focus-visible {
  border-color: #f26522;
  outline: none;
  box-shadow: 0 0 0 3px rgba(242, 101, 34, 0.14);
}

.area-selector__select-chevron {
  pointer-events: none;
  position: absolute;
  right: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
}

@media (max-width: 767px) {
  .area-selector__bar {
    min-height: auto;
    flex-direction: column;
    align-items: stretch;
    gap: 0.625rem;
    padding: 0.75rem 1rem;
  }

  .area-selector__controls {
    justify-content: space-between;
    gap: 0.75rem;
  }

  .area-selector__action,
  .area-selector__select-wrap {
    min-width: 0;
    width: 100%;
  }
}

@media (max-width: 520px) {
  .area-selector__controls {
    flex-direction: column;
    align-items: stretch;
  }

  .area-selector__status {
    white-space: normal;
  }
}
</style>
