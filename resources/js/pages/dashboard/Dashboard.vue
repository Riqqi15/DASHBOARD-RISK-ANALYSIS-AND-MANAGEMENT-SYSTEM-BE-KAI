<template>
  <!--
    THESIS: Dashboard ini membantu pengguna mengambil keputusan operasi tanpa memaksa semua data tampil sekaligus.
    OWN-WORLD: Identitas KAI/RAMS tetap hadir lewat biru tua, jingga, dan warna status Excel pada subsystem.
    STORY: Pilih wilayah, pantau status gangguan operasi, baca kinerja per kelompok aset, lalu akses subsystem peralatan.
    FIRST VIEWPORT: Wilayah aktif, ringkasan kinerja, dan status gangguan menjadi fokus; detail dan aset muncul bertahap.
    FORM: Operate dashboard dengan progressive disclosure; alur Trouble Report dan data hierarchy tetap dipertahankan.
  -->
  <Head><title>Dashboard RAMS</title></Head>
  <MainLayout>
    <AreaSelectorBanner collapsible :units="units" :selected-area="selected_area" />

    <div class="dashboard-shell space-y-7 pb-12">
      <!-- HERO HEADER -->
      <section class="dashboard-hero" aria-labelledby="dashboard-heading">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-md bg-orange-50 px-2 py-0.5 text-xs font-bold text-[#f26522] ring-1 ring-inset ring-orange-200">
              KAI RAMS
            </span>
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Persinyalan & Telekomunikasi</span>
          </div>
          <h1 id="dashboard-heading" class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
            Ringkasan kinerja persinyalan
          </h1>
          <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-600">
            Lihat kondisi umum dan catatan kegagalan peralatan di wilayah yang sedang dipilih. Buka rincian hanya saat Anda membutuhkannya.
          </p>
        </div>
      </section>

      <!-- REKAP GANGGUAN TERCATAT -->
      <section aria-labelledby="failure-status-title">
        <div class="failure-stat-card failure-stat-card--plain">
          <div class="flex items-center gap-3.5">
            <span class="failure-stat-card__icon" aria-hidden="true">
              <FileSpreadsheet :size="20" :stroke-width="2" />
            </span>
            <div>
              <span class="failure-stat-card__label">Rekap gangguan tercatat</span>
              <p class="failure-stat-card__hint">Total akumulasi catatan kegagalan peralatan pada wilayah terpilih</p>
            </div>
          </div>
          <strong id="failure-status-title" class="failure-stat-card__value">
            {{ formattedFailureCount }}
          </strong>
        </div>
      </section>

      <!-- KINERJA PER KELOMPOK ASET (SINTEL KAI) -->
      <section class="family-metrics" aria-labelledby="family-metrics-title">
        <div class="family-metrics__heading">
          <div>
            <div class="flex flex-wrap items-center gap-2.5">
              <h2 id="family-metrics-title" class="text-lg font-bold tracking-tight text-slate-950 sm:text-xl">
                Kinerja per kelompok aset
              </h2>
              <span
                v-if="latestImportLabel"
                data-latest-import-badge
                class="family-metrics__latest-badge"
              >
                {{ latestImportLabel }}
              </span>
            </div>
            <p class="mt-1 text-sm leading-6 text-slate-600">
              Singkatan dan warna mengikuti standar Aset Prasarana Sintel pada Excel KAI.
            </p>
          </div>
          <div class="family-metrics__heading-actions">
            <span class="hidden text-xs font-semibold uppercase tracking-wider text-slate-500 sm:inline">
              Reliability · Availability
            </span>
            <div v-if="isFamilyCarousel" class="family-metrics__navigation" aria-label="Navigasi kelompok aset">
              <button
                type="button"
                class="family-metrics__arrow"
                aria-label="Geser kelompok aset ke kiri"
                aria-controls="family-metrics-track"
                :disabled="!canScrollFamilyLeft"
                @click="scrollFamilyTrack(-1)"
              >
                <ChevronLeft :size="18" aria-hidden="true" />
              </button>
              <button
                type="button"
                class="family-metrics__arrow"
                aria-label="Geser kelompok aset ke kanan"
                aria-controls="family-metrics-track"
                :disabled="!canScrollFamilyRight"
                @click="scrollFamilyTrack(1)"
              >
                <ChevronRight :size="18" aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="reliabilityGroups.length"
          id="family-metrics-track"
          ref="familyTrack"
          data-family-track
          class="family-metrics__track"
          :class="isFamilyCarousel ? 'family-metrics__track--scroll' : 'family-metrics__track--fit'"
          :tabindex="isFamilyCarousel ? 0 : undefined"
          aria-label="Daftar kinerja kelompok aset"
          @scroll="updateFamilyScrollState"
        >
          <article
            v-for="group in reliabilityGroups"
            :key="`family-${group.id || group.code}`"
            class="family-metric family-metric--compact"
            :data-family-code="group.code"
            :aria-label="`${groupLabel(group)}: Reliability ${formatPercentage(group.reliability, 4)}, Availability ${formatPercentage(group.availability, 2)}`"
          >
            <header class="family-metric__header">
              <strong
                class="family-metric__code"
                :class="contrastTextClass(groupAccent(group))"
                :style="{ backgroundColor: groupAccent(group) }"
              >
                {{ group.code }}
              </strong>
              <span class="family-metric__name">{{ groupLabel(group) }}</span>
            </header>
            <dl class="family-metric__values family-metric__values--emphasized">
              <div>
                <dt>Reliability</dt>
                <dd>{{ formatPercentage(group.reliability, 4) }}</dd>
              </div>
              <div>
                <dt>Availability</dt>
                <dd>{{ formatPercentage(group.availability, 2) }}</dd>
              </div>
            </dl>
          </article>
        </div>
        <p v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center text-sm text-slate-600">
          Belum ada data aset untuk wilayah terpilih.
        </p>
      </section>

      <!-- PERALATAN PERSINYALAN & SUBSYSTEM EXPLORER -->
      <section aria-labelledby="asset-list-title">
        <div class="mb-5 flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 id="asset-list-title" class="text-lg font-bold tracking-tight text-slate-950 sm:text-xl">
              Peralatan persinyalan
            </h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
              Buka kelompok peralatan, lalu pilih subsystem untuk melihat data dan membuat Trouble Report.
            </p>
          </div>
          <span class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">
            <Server :size="15" aria-hidden="true" />
            {{ totalSubsystems }} subsystem
          </span>
        </div>

        <div v-if="assetGroups.length" class="space-y-3">
          <details
            v-for="(group, groupIndex) in assetGroups"
            :key="group.name"
            :open="groupIndex === 0"
            :data-asset-group="group.name"
            class="asset-group"
          >
            <summary class="asset-group__summary asset-group__summary--plain asset-group__summary--white">
              <span class="flex min-w-0 items-start gap-3">
                <span class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full ring-4 ring-white" :style="{ backgroundColor: groupAccentValue(group.color) }" aria-hidden="true" />
                <span class="min-w-0">
                  <strong class="block text-base font-bold leading-6 text-slate-950">{{ group.name }}</strong>
                  <span class="mt-0.5 block text-xs text-slate-500">{{ group.assetCount }} aset · {{ group.unitCount }} unit · {{ group.systems.length }} system</span>
                </span>
              </span>
              <ChevronDown :size="20" class="asset-group__chevron shrink-0 text-slate-400" aria-hidden="true" />
            </summary>

            <div class="asset-group__content">
              <article v-for="system in group.systems" :key="`${group.name}-${system.name}`" class="system-row">
                <div class="min-w-0">
                  <h3 class="text-sm font-bold leading-5 text-slate-900">{{ system.name }}</h3>
                  <p class="mt-0.5 text-xs text-slate-500">{{ system.assetCount }} aset · {{ system.unitCount }} unit</p>
                </div>

                <div v-if="system.subsystems.length" class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  <button
                    v-for="subsystem in system.subsystems"
                    :key="`${group.name}-${system.name}-${subsystem.name}`"
                    type="button"
                    class="subsystem-btn group"
                    :data-subsystem-name="subsystem.name"
                    :class="contrastTextClass(subsystem.color || system.color || group.color)"
                    :style="subsystemButtonStyle(subsystem.color || system.color || group.color)"
                    :aria-label="`Buka data ${subsystem.name}`"
                    @click="goToTroubleReport(subsystem.name)"
                  >
                    <span class="min-w-0">
                      <span class="block break-words text-sm font-bold leading-5">{{ subsystem.name }}</span>
                      <span class="mt-1 block text-xs font-medium opacity-90">{{ subsystem.assetCount }} aset · {{ subsystem.unitCount }} unit</span>
                    </span>
                    <ChevronRight :size="18" class="shrink-0 opacity-75 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                  </button>
                </div>
                <p v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-600">
                  Belum ada subsystem aktif.
                </p>
              </article>
              <p v-if="group.systems.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-600">
                Belum ada system aktif.
              </p>
            </div>
          </details>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500" aria-hidden="true">
            <Server :size="24" />
          </span>
          <h3 class="mt-4 text-base font-bold text-slate-900">Belum ada peralatan terhubung</h3>
          <p class="mx-auto mt-1 max-w-xl text-sm leading-6 text-slate-600">
            Tambahkan master aset yang terhubung ke kategori, system, dan subsystem agar muncul di dashboard.
          </p>
        </div>
      </section>
    </div>
  </MainLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  FileSpreadsheet,
  Server,
} from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'

const props = defineProps({
  units: { type: Array, default: () => [] },
  selected_area: { type: String, default: null },
  summary: { type: Object, default: () => ({}) },
  assets: { type: Array, default: () => [] },
  asset_categories: { type: Array, default: () => [] },
})

const groupColors = {
  PDSM: '#92D050',
  PLSM: '#4BACC6',
  PDSE: '#FFFF00',
  PLSE: '#FFC000',
  CDS: '#FF0000',
}

const groupNames = {
  PDSM: 'Peralatan Dalam Sinyal Mekanik',
  PLSM: 'Peralatan Luar Sinyal Mekanik',
  PDSE: 'Peralatan Dalam Sinyal Elektrik',
  PLSE: 'Peralatan Luar Sinyal Elektrik',
  CDS: 'Catu Daya Sintel',
}

const fallbackLabel = 'Tanpa data'
const getLabel = (value) => String(value ?? '').trim() || fallbackLabel

const formatNumber = (value) => {
  const number = Number(value)
  return Number.isFinite(number) ? new Intl.NumberFormat('id-ID').format(number) : '0'
}

const failureCountNumber = computed(() => {
  const failures = Number(props.summary?.totalFailure)
  return Number.isFinite(failures) ? failures : 0
})

const formattedFailureCount = computed(() => {
  const failures = failureCountNumber.value
  return `${formatNumber(failures)} kejadian`
})

const formatPercentage = (value, maxDecimals = 2) => {
  if (value === null || value === undefined || value === '') return 'Belum ada data'

  const number = Number(value)
  if (!Number.isFinite(number)) return 'Belum ada data'

  return new Intl.NumberFormat('id-ID', {
    style: 'percent',
    minimumFractionDigits: 0,
    maximumFractionDigits: maxDecimals,
  }).format(number)
}

const reliabilityGroups = computed(() => props.summary?.reliabilityGroups || [])
const isFamilyCarousel = computed(() => reliabilityGroups.value.length > 7)

const latestImportLabel = computed(() => {
  const value = props.summary?.latestImport?.date
  if (!value) return null

  const date = new Date(`${value}T00:00:00Z`)
  if (Number.isNaN(date.getTime())) return `Data Terbaru · ${value}`

  const formatted = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(date)

  return `Data Terbaru · ${formatted}`
})

const familyTrack = ref(null)
const canScrollFamilyLeft = ref(false)
const canScrollFamilyRight = ref(false)

const updateFamilyScrollState = () => {
  const track = familyTrack.value
  if (!track) return

  if (!isFamilyCarousel.value) {
    canScrollFamilyLeft.value = false
    canScrollFamilyRight.value = false
    return
  }

  const maxScroll = Math.max(track.scrollWidth - track.clientWidth, 0)
  canScrollFamilyLeft.value = track.scrollLeft > 2
  canScrollFamilyRight.value = track.scrollLeft < maxScroll - 2
}

const refreshFamilyScrollState = () => nextTick(updateFamilyScrollState)

const scrollFamilyTrack = (direction) => {
  const track = familyTrack.value
  if (!track || !isFamilyCarousel.value) return

  track.scrollBy({
    left: direction * Math.max(track.clientWidth - 32, 1),
    behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ? 'auto' : 'smooth',
  })
}

onMounted(() => {
  window.addEventListener('resize', updateFamilyScrollState)
  refreshFamilyScrollState()
})

onBeforeUnmount(() => window.removeEventListener('resize', updateFamilyScrollState))
watch(() => reliabilityGroups.value.length, refreshFamilyScrollState)

const groupLabel = (group) => group?.name || groupNames[group?.code] || group?.code || 'Kelompok aset'
const groupAccent = (group) => group?.color || groupColors[group?.code] || '#64748B'
const groupAccentValue = (color) => color || '#64748B'

const sumUnits = (assets) => assets.reduce((total, asset) => {
  const units = Number(asset.jumlah_unit ?? 0)
  return total + (Number.isFinite(units) ? units : 0)
}, 0)

const makeNode = (name, id = null, color = null) => ({
  id,
  name,
  displayName: name,
  color,
  assets: [],
  children: new Map(),
})

const ensureChild = (children, name, id = null, color = null) => {
  if (!children.has(name)) {
    children.set(name, makeNode(name, id, color))
  } else if (color) {
    children.get(name).color = color
  }

  return children.get(name)
}

const assetGroups = computed(() => {
  const groups = new Map()

  props.asset_categories.forEach((category) => {
    const group = ensureChild(groups, getLabel(category.name), category.id, category.dashboard_color)
    const order = Number(category.sort_order)
    if (!/^\s*\d+\s*[.-]/.test(group.name) && Number.isInteger(order) && order > 0) {
      group.displayName = `${order}. ${group.name}`
    }

    ;(category.systems ?? []).forEach((categorySystem) => {
      const system = ensureChild(group.children, getLabel(categorySystem.name), categorySystem.id, categorySystem.dashboard_color)

      ;(categorySystem.subsystems ?? []).forEach((categorySubsystem) => {
        ensureChild(system.children, getLabel(categorySubsystem.name), categorySubsystem.id, categorySubsystem.dashboard_color)
      })
    })
  })

  props.assets.forEach((asset) => {
    const groupName = getLabel(asset.aset_prasarana_sintel)
    const systemName = getLabel(asset.system)
    const subsystemName = getLabel(asset.subsystem)

    const group = ensureChild(groups, groupName)
    const system = ensureChild(group.children, systemName)
    const subsystem = ensureChild(system.children, subsystemName)

    group.assets.push(asset)
    system.assets.push(asset)
    subsystem.assets.push(asset)
  })

  return Array.from(groups.values()).map((group) => {
    const systems = Array.from(group.children.values()).map((system) => {
      const subsystems = Array.from(system.children.values())
        .map((subsystem) => ({
          name: subsystem.name,
          color: subsystem.color,
          assetCount: subsystem.assets.length,
          unitCount: sumUnits(subsystem.assets),
        }))

      return {
        name: system.name,
        color: system.color,
        subsystems,
        assetCount: system.assets.length,
        unitCount: sumUnits(system.assets),
      }
    })

    return {
      name: group.displayName,
      color: group.color,
      systems,
      assetCount: group.assets.length,
      unitCount: sumUnits(group.assets),
    }
  })
})

const totalSubsystems = computed(() => assetGroups.value.reduce(
  (total, group) => total + group.systems.reduce((count, system) => count + system.subsystems.length, 0),
  0,
))

const darken = (hex, amount = 0.16) => {
  if (!/^#[0-9A-F]{6}$/i.test(hex ?? '')) return '#475569'
  const channels = [1, 3, 5].map((offset) => Math.round(Number.parseInt(hex.slice(offset, offset + 2), 16) * (1 - amount)))
  return `#${channels.map((value) => value.toString(16).padStart(2, '0')).join('')}`
}

const contrastTextClass = (hex) => {
  if (!/^#[0-9A-F]{6}$/i.test(hex ?? '')) return 'text-slate-950'
  const [r, g, b] = [1, 3, 5].map((offset) => Number.parseInt(hex.slice(offset, offset + 2), 16) / 255)
  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b
  return luminance > 0.62 ? 'text-slate-950' : 'text-white'
}

const contrastTextColor = (hex) => contrastTextClass(hex) === 'text-slate-950' ? '#0F172A' : '#FFFFFF'

const subsystemButtonStyle = (color) => color ? {
  backgroundColor: color,
  borderColor: darken(color),
  color: contrastTextColor(color),
  '--subsystem-hover': darken(color, 0.24),
} : {
  backgroundColor: '#64748B',
  borderColor: '#475569',
  color: '#FFFFFF',
  '--subsystem-hover': '#475569',
}

const goToTroubleReport = (subsystemName = null) => {
  router.get('/trouble-report', {
    ...(subsystemName ? { subsystem: subsystemName } : {}),
    ...(props.selected_area ? { area: props.selected_area } : {}),
  })
}
</script>

<style scoped>
.dashboard-hero {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 1.25rem;
}

/* GANGGUAN TERCATAT STAT CARD */
.failure-stat-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.125rem 1.5rem;
  border-radius: 0.875rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px 0 rgb(15 23 42 / 0.04), 0 1px 2px -1px rgb(15 23 42 / 0.04);
}

.failure-stat-card__icon {
  display: flex;
  height: 2.5rem;
  width: 2.5rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.625rem;
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.failure-stat-card__label {
  display: block;
  font-size: 0.9375rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.25;
}

.failure-stat-card__hint {
  margin-top: 0.125rem;
  font-size: 0.8125rem;
  color: #64748b;
  line-height: 1.25;
}

.failure-stat-card__value {
  font-size: 1.875rem;
  font-weight: 900;
  letter-spacing: -0.03em;
  color: #dc2626;
  line-height: 1;
}

/* FAMILY METRICS (PDSM, PLSM, PDSE, PLSE, CDS) */
.family-metrics {
  border: 1px solid #e2e8f0;
  border-radius: 0.875rem;
  background: #ffffff;
  padding: 1.25rem;
  box-shadow: 0 1px 3px 0 rgb(15 23 42 / 0.04), 0 1px 2px -1px rgb(15 23 42 / 0.04);
}

.family-metrics__heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.875rem;
}

.family-metrics__heading-actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.75rem;
}

.family-metrics__navigation {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.family-metrics__arrow {
  display: inline-flex;
  width: 2.75rem;
  height: 2.75rem;
  align-items: center;
  justify-content: center;
  border: 1px solid #cbd5e1;
  border-radius: 0.5rem;
  background: #ffffff;
  color: #334155;
}

.family-metrics__arrow:not(:disabled):hover {
  border-color: #94a3b8;
  background: #f8fafc;
  color: #0f172a;
}

.family-metrics__arrow:focus-visible {
  outline: 2px solid #f26522;
  outline-offset: 2px;
}

.family-metrics__arrow:disabled {
  cursor: not-allowed;
  border-color: #e2e8f0;
  background: #f8fafc;
  color: #cbd5e1;
}

.family-metrics__track {
  --family-gap: 0.625rem;
  display: grid;
  gap: var(--family-gap);
  padding-top: 0.875rem;
  padding-bottom: 0.25rem;
}

.family-metrics__track--fit {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 11rem), 1fr));
}

.family-metrics__track--scroll {
  grid-auto-flow: column;
  grid-auto-columns: min(82%, 13rem);
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scroll-padding-inline: 0.125rem;
  scroll-snap-type: inline mandatory;
  scrollbar-width: none;
}

.family-metrics__track--scroll::-webkit-scrollbar {
  display: none;
}

.family-metrics__track--scroll:focus-visible {
  outline: 2px solid #f26522;
  outline-offset: 3px;
}

.family-metric {
  display: grid;
  grid-template-rows: 1fr auto;
  min-width: 0;
  border: 1px solid #e2e8f0;
  border-radius: 0.625rem;
  background: #ffffff;
  scroll-snap-align: start;
  scroll-snap-stop: always;
}

.family-metric__header {
  min-height: 4.75rem;
  padding: 0.5rem 0.625rem 0.625rem;
  background: #ffffff;
}

.family-metric__code {
  display: inline-flex;
  min-height: 1.375rem;
  align-items: center;
  border-radius: 0.25rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.75rem;
  font-weight: 900;
  line-height: 1;
  letter-spacing: 0.04em;
}

.family-metric__name {
  display: -webkit-box;
  overflow: hidden;
  margin-top: 0.375rem;
  color: #334155;
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.3;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.family-metrics__latest-badge {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  border: 1px solid #cbd5e1;
  border-radius: 9999px;
  background: #f8fafc;
  padding: 0.25rem 0.5rem;
  color: #334155;
  font-size: 0.75rem;
  font-weight: 800;
  line-height: 1;
  white-space: nowrap;
}

.family-metric__values {
  display: grid;
  gap: 0.375rem;
  border-top: 1px solid #e2e8f0;
  padding: 0.5rem 0.625rem;
  background: #f8fafc;
}

.family-metric__values div {
  display: block;
}

.family-metric__values dt {
  color: #64748b;
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.035em;
}

.family-metric__values dd {
  margin-top: 0.125rem;
  color: #0f172a;
  font-size: 0.8125rem;
  font-weight: 900;
  line-height: 1.2;
  text-align: left;
}

/* ASSET GROUPS ACCORDION */
.asset-group {
  overflow: hidden;
  border: 1px solid #ffffff;
  border-radius: 0.875rem;
  background: #ffffff;
  box-shadow: 0 1px 3px 0 rgb(15 23 42 / 0.04), 0 1px 2px -1px rgb(15 23 42 / 0.04);
}

.asset-group__summary {
  display: flex;
  min-height: 4.25rem;
  cursor: pointer;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  list-style: none;
  background: #ffffff;
}

.asset-group__summary::-webkit-details-marker { display: none; }

.asset-group__summary:focus-visible {
  outline: 2px solid #f26522;
  outline-offset: -2px;
}

.asset-group__chevron { transition: transform 180ms ease; }
.asset-group[open] .asset-group__chevron { transform: rotate(180deg); }

.asset-group__content {
  display: grid;
  gap: 1rem;
  background: #ffffff;
  padding: 1rem;
}

.system-row {
  display: grid;
  gap: 0.875rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #ffffff;
  padding: 1rem;
}

.subsystem-btn {
  display: flex;
  min-height: 5rem;
  cursor: pointer;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border-width: 1px;
  border-radius: 0.625rem;
  padding: 0.875rem;
  box-shadow: 0 1px 3px 0 rgb(15 23 42 / 0.08);
  text-align: left;
  transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.subsystem-btn:hover {
  background-color: var(--subsystem-hover, #475569);
  box-shadow: 0 4px 12px -2px rgb(15 23 42 / 0.2);
  transform: translateY(-1px);
}

.subsystem-btn:focus-visible {
  outline: 2px solid #f26522;
  outline-offset: 2px;
}

.subsystem-btn:active { transform: translateY(0); }

@media (min-width: 640px) {
  .family-metrics__track--scroll {
    grid-auto-columns: calc((100% - 1.875rem) / 4);
  }
}

@media (min-width: 1280px) {
  .family-metrics__track--scroll {
    grid-auto-columns: calc((100% - 3.75rem) / 7);
  }
}

@media (max-width: 639px) {
  .family-metrics {
    padding: 1rem;
  }

  .family-metrics__heading {
    align-items: flex-start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .asset-group__chevron,
  .subsystem-btn { transition: none; }
}
</style>
