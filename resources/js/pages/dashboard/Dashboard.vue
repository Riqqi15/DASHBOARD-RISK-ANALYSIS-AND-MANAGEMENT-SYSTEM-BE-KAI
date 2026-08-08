<template>
  <!--
    THESIS: Dashboard ini membantu pengguna mengambil keputusan operasi tanpa memaksa semua data tampil sekaligus.
    OWN-WORLD: Identitas KAI/RAMS tetap hadir lewat biru tua, jingga, dan warna status Excel pada subsystem.
    STORY: Pilih wilayah, baca empat status utama, buka rincian bila perlu, lalu pilih peralatan.
    FIRST VIEWPORT: Wilayah aktif, ringkasan kinerja, dan status utama menjadi fokus; detail dan aset muncul bertahap.
    FORM: Operate dashboard dengan progressive disclosure; alur Trouble Report dan data hierarchy tetap dipertahankan.
    FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
  -->
  <MainLayout>
    <AreaSelectorBanner :units="units" :selected-area="selected_area" />

    <div class="dashboard-shell space-y-8 pb-12">
      <section class="dashboard-hero" aria-labelledby="dashboard-heading">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-[#f26522]">KAI RAMS</p>
          <h1 id="dashboard-heading" class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
            Ringkasan kinerja persinyalan
          </h1>
          <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">
            Lihat kondisi umum peralatan di wilayah yang sedang dipilih. Buka rincian hanya saat Anda membutuhkannya.
          </p>
        </div>
      </section>

      <section aria-labelledby="quick-status-title">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 id="quick-status-title" class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Status utama</h2>
            <p class="mt-1 text-base leading-7 text-slate-600">Empat angka yang paling sering digunakan untuk membaca kondisi area.</p>
          </div>
          <span class="text-sm font-medium text-slate-500">Mulai operasi: {{ formattedOperatingStartDate }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article class="status-tile status-tile--navy">
            <div class="flex items-start justify-between gap-3">
              <span class="status-tile__label">Keandalan sistem</span>
              <ShieldCheck :size="22" :stroke-width="2" aria-hidden="true" />
            </div>
            <strong class="status-tile__value">{{ formatPercentage(summary?.overallReliability, 2) }}</strong>
            <span class="status-tile__hint">Berjalan tanpa gangguan</span>
          </article>
          <article class="status-tile status-tile--orange">
            <div class="flex items-start justify-between gap-3">
              <span class="status-tile__label">Ketersediaan sistem</span>
              <CircleCheck :size="22" :stroke-width="2" aria-hidden="true" />
            </div>
            <strong class="status-tile__value">{{ formatPercentage(summary?.overallAvailability, 2) }}</strong>
            <span class="status-tile__hint">Siap digunakan saat dibutuhkan</span>
          </article>
          <article class="status-tile status-tile--light">
            <div class="flex items-start justify-between gap-3">
              <span class="status-tile__label">Gangguan tercatat</span>
              <TriangleAlert :size="22" :stroke-width="2" aria-hidden="true" />
            </div>
            <strong class="status-tile__value status-tile__value--danger">{{ formattedFailureCount }}</strong>
            <span class="status-tile__hint">Catatan kegagalan pada area</span>
          </article>
          <article class="status-tile status-tile--light">
            <div class="flex items-start justify-between gap-3">
              <span class="status-tile__label">Lama operasi</span>
              <Clock3 :size="22" :stroke-width="2" aria-hidden="true" />
            </div>
            <strong class="status-tile__value">{{ formattedOperatingDays }}</strong>
            <span class="status-tile__hint">Sejak tanggal mulai operasi</span>
          </article>
        </div>
      </section>

      <section class="family-metrics" aria-labelledby="family-metrics-title">
        <div class="family-metrics__heading">
          <div>
            <h2 id="family-metrics-title" class="text-xl font-bold tracking-tight text-slate-950">Kinerja per kelompok aset</h2>
            <p class="mt-1 text-base leading-7 text-slate-600">Singkatan dan warna mengikuti Aset Prasarana Sintel pada Excel KAI.</p>
          </div>
          <span class="hidden text-sm font-semibold text-slate-500 sm:inline">Reliability · Availability</span>
        </div>

        <div class="family-metrics__grid">
          <article
            v-for="group in reliabilityGroups"
            :key="`family-${group.code}`"
            class="family-metric"
            :data-family-code="group.code"
            :aria-label="`${groupLabel(group.code)}: Reliability ${formatPercentage(group.reliability, 4)}, Availability ${formatPercentage(group.availability, 2)}`"
          >
            <header class="family-metric__header" :style="{ backgroundColor: groupAccent(group.code) }" :class="contrastTextClass(groupAccent(group.code))">
              <strong class="text-base font-extrabold tracking-wide">{{ group.code }}</strong>
              <span class="mt-1 block text-xs font-semibold leading-5 opacity-90">{{ groupLabel(group.code) }}</span>
            </header>
            <dl class="family-metric__values">
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
      </section>

      <section aria-labelledby="asset-list-title">
        <div class="mb-5 flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 id="asset-list-title" class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Peralatan persinyalan</h2>
            <p class="mt-1 max-w-3xl text-base leading-7 text-slate-600">
              Buka kelompok peralatan, lalu pilih subsystem untuk melihat data dan membuat Trouble Report.
            </p>
          </div>
          <span class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
            <Server :size="17" aria-hidden="true" />
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
            <summary class="asset-group__summary">
              <span class="flex min-w-0 items-start gap-3">
                <span class="mt-1 h-4 w-4 shrink-0 rounded-full ring-4 ring-white" :style="{ backgroundColor: groupAccentValue(group.color) }" aria-hidden="true" />
                <span class="min-w-0">
                  <strong class="block text-lg font-bold leading-7 text-slate-950">{{ group.name }}</strong>
                  <span class="mt-1 block text-sm text-slate-600">{{ group.assetCount }} aset · {{ group.unitCount }} unit · {{ group.systems.length }} system</span>
                </span>
              </span>
              <ChevronDown :size="21" class="asset-group__chevron shrink-0 text-slate-500" aria-hidden="true" />
            </summary>

            <div class="asset-group__content">
              <article v-for="system in group.systems" :key="`${group.name}-${system.name}`" class="system-row">
                <div class="min-w-0">
                  <h3 class="text-base font-bold leading-6 text-slate-900">{{ system.name }}</h3>
                  <p class="mt-1 text-sm text-slate-600">{{ system.assetCount }} aset · {{ system.unitCount }} unit</p>
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
                      <span class="block break-words text-base font-bold leading-6">{{ subsystem.name }}</span>
                      <span class="mt-1 block text-sm font-medium opacity-90">{{ subsystem.assetCount }} aset · {{ subsystem.unitCount }} unit</span>
                    </span>
                    <ChevronRight :size="20" class="shrink-0 opacity-75 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                  </button>
                </div>
                <p v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                  Belum ada subsystem aktif.
                </p>
              </article>
            </div>
          </details>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500" aria-hidden="true">
            <Server :size="24" />
          </span>
          <h3 class="mt-4 text-lg font-bold text-slate-900">Belum ada peralatan terhubung</h3>
          <p class="mx-auto mt-2 max-w-xl text-base leading-7 text-slate-600">
            Tambahkan master aset yang terhubung ke kategori, system, dan subsystem agar muncul di dashboard.
          </p>
        </div>
      </section>
    </div>
  </MainLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  ChevronDown,
  ChevronRight,
  CircleCheck,
  Clock3,
  Server,
  ShieldCheck,
  TriangleAlert,
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

const groupOrder = ['PDSM', 'PLSM', 'PDSE', 'PLSE', 'CDS']

const fallbackLabel = 'Tanpa data'
const getLabel = (value) => String(value ?? '').trim() || fallbackLabel

const formattedOperatingStartDate = computed(() => {
  if (!props.summary?.operatingStartDate) return 'Data belum ada'

  const date = new Date(`${props.summary.operatingStartDate}T00:00:00`)
  if (Number.isNaN(date.getTime())) return 'Data belum ada'

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
})

const formatNumber = (value) => {
  const number = Number(value)
  return Number.isFinite(number) ? new Intl.NumberFormat('id-ID').format(number) : 'Data belum ada'
}

const formattedOperatingDays = computed(() => {
  const days = Number(props.summary?.operatingDays)
  return Number.isFinite(days) ? `${formatNumber(days)} hari` : 'Data belum ada'
})

const formattedFailureCount = computed(() => {
  const failures = Number(props.summary?.totalFailure)
  return Number.isFinite(failures) ? `${formatNumber(failures)} kejadian` : 'Data belum ada'
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

const reliabilityGroups = computed(() => {
  const providedGroups = new Map(
    (props.summary?.reliabilityGroups || []).map((group) => [group.code, group]),
  )

  return groupOrder.map((code) => providedGroups.get(code) || {
    code,
    reliability: null,
    availability: null,
  })
})
const groupLabel = (code) => groupNames[code] || code || 'Kelompok aset'
const groupAccent = (code) => groupColors[code] || '#64748B'
const groupAccentValue = (color) => color || '#64748B'

const sumUnits = (assets) => assets.reduce((total, asset) => {
  const units = Number(asset.jumlah_unit ?? 0)
  return total + (Number.isFinite(units) ? units : 0)
}, 0)

const makeNode = (name, id = null, color = null) => ({
  id,
  name,
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
        .filter((subsystem) => subsystem.assets.length > 0)
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
    }).filter((system) => system.assetCount > 0)

    return {
      name: group.name,
      color: group.color,
      systems,
      assetCount: group.assets.length,
      unitCount: sumUnits(group.assets),
    }
  }).filter((group) => group.assetCount > 0)
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

const goToTroubleReport = (subsystemName) => {
  router.get('/trouble-report', {
    subsystem: subsystemName,
    ...(props.selected_area ? { area: props.selected_area } : {}),
  })
}
</script>

<style scoped>
.dashboard-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  border-bottom: 1px solid #dbe3ef;
  padding: 0.5rem 0 1.5rem;
}

.status-tile {
  min-height: 9.5rem;
  border: 1px solid #dbe3ef;
  border-radius: 0.875rem;
  padding: 1.25rem;
  box-shadow: 0 10px 24px -22px rgb(15 23 42 / 55%);
}

.status-tile--navy,
.status-tile--orange {
  color: #fff;
  border-color: transparent;
}

.status-tile--navy { background: #171650; }
.status-tile--orange { background: #f26522; }
.status-tile--light { background: #fff; color: #0f172a; }

.status-tile__label {
  display: block;
  max-width: 13rem;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
}

.status-tile__value {
  display: block;
  margin-top: 1.25rem;
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1;
}

.status-tile__value--danger { color: #be123c; }

.status-tile__hint {
  display: block;
  margin-top: 0.75rem;
  font-size: 0.875rem;
  line-height: 1.4;
  opacity: 0.82;
}

.asset-group {
  overflow: hidden;
  border: 1px solid #dbe3ef;
  border-radius: 0.875rem;
  background: #fff;
  box-shadow: 0 10px 24px -22px rgb(15 23 42 / 55%);
}

.asset-group__summary {
  display: flex;
  min-height: 4.5rem;
  cursor: pointer;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.125rem 1.25rem;
  list-style: none;
}

.asset-group__summary::-webkit-details-marker { display: none; }

.asset-group__summary:focus-visible {
  outline: 3px solid #f26522;
  outline-offset: -3px;
}

.asset-group__chevron { transition: transform 180ms ease; }

.asset-group[open] .asset-group__chevron { transform: rotate(180deg); }

.family-metrics {
  border: 1px solid #dbe3ef;
  border-radius: 0.875rem;
  background: #fff;
  padding: 1.25rem;
  box-shadow: 0 10px 24px -22px rgb(15 23 42 / 55%);
}

.family-metrics__heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid #dbe3ef;
  padding-bottom: 1rem;
}

.family-metrics__grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.75rem;
  padding-top: 1rem;
}

.family-metric {
  overflow: hidden;
  min-width: 0;
  border: 1px solid #dbe3ef;
  border-radius: 0.75rem;
  background: #fff;
}

.family-metric__header {
  min-height: 5.5rem;
  padding: 0.875rem;
}

.family-metric__values {
  display: grid;
  gap: 0.75rem;
  padding: 0.875rem;
}

.family-metric__values div {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
}

.family-metric__values dt {
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 700;
}

.family-metric__values dd {
  color: #0f172a;
  font-size: 0.875rem;
  font-weight: 800;
  text-align: right;
}

.asset-group__summary {
  border-top: 4px solid #64748b;
  background: #f8fafc;
}

.asset-group__content {
  display: grid;
  gap: 1rem;
  border-top: 1px solid #dbe3ef;
  background: #f8fafc;
  padding: 1rem;
}

.system-row {
  display: grid;
  gap: 1rem;
  border: 1px solid #dbe3ef;
  border-radius: 0.75rem;
  background: #fff;
  padding: 1rem;
}

.subsystem-btn {
  display: flex;
  min-height: 5.5rem;
  cursor: pointer;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border-width: 1px;
  border-radius: 0.75rem;
  padding: 1rem;
  box-shadow: 0 5px 12px -8px rgb(15 23 42 / 35%);
  text-align: left;
  transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
  touch-action: manipulation;
}

.subsystem-btn:hover {
  background-color: var(--subsystem-hover, #475569);
  box-shadow: 0 9px 18px -10px rgb(15 23 42 / 55%);
  transform: translateY(-1px);
}

.subsystem-btn:focus-visible {
  outline: 3px solid #f26522;
  outline-offset: 3px;
}

.subsystem-btn:active { transform: translateY(0); }

@media (max-width: 640px) {
  .dashboard-hero {
    align-items: flex-start;
    flex-direction: column;
    padding-top: 0;
  }
  .status-tile { min-height: 8.5rem; }
}

@media (prefers-reduced-motion: reduce) {
  .asset-group__chevron,
  .subsystem-btn { transition: none; }
}

@media (max-width: 1024px) {
  .family-metrics__grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .family-metrics { padding: 1rem; }
  .family-metrics__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
