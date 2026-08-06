<template>
  <MainLayout>
    <AreaSelectorBanner :units="units" :selected-area="selected_area" />
    <div class="space-y-8 pb-10">
      <section class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col xl:flex-row xl:items-stretch">
        <div class="flex-1 flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between border-b xl:border-b-0 xl:border-r border-slate-200">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Operational {{ selected_area || 'DAOP/DIVRE' }}</p>
            <h2 class="mt-1 text-lg font-extrabold text-slate-800">Tahun awal pemasangan equipment</h2>
          </div>
          <div class="rounded-lg bg-lime-100 px-4 py-3 text-center ring-1 ring-lime-200">
            <p class="text-xs font-semibold text-slate-700">Mulai operasi</p>
            <p class="mt-1 text-2xl font-extrabold text-red-600">{{ formattedOperatingStartDate }}</p>
          </div>
        </div>
        <div class="flex-1 grid grid-cols-2 divide-x divide-slate-200 bg-slate-50">
          <div class="px-5 py-5 flex flex-col justify-center items-center text-center">
            <p class="text-sm font-bold text-slate-800 mb-1">Operating Days</p>
            <div class="inline-flex items-baseline gap-1">
              <span class="text-3xl font-black text-[#171650]">{{ summary?.operatingDays ?? '-' }}</span>
              <span class="text-sm font-semibold text-slate-500">Days</span>
            </div>
          </div>
          <div class="px-5 py-5 flex flex-col justify-center items-center text-center">
            <p class="text-sm font-bold text-slate-800 mb-1">Failure Count</p>
            <div class="inline-flex items-baseline gap-1">
              <span class="text-3xl font-black text-red-600">{{ summary?.totalFailure ?? 0 }}</span>
              <span class="text-sm font-semibold text-slate-500">times</span>
            </div>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <!-- RELIABILITY -->
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
          <div class="bg-slate-100 px-4 py-2 border-b border-slate-200 text-center">
            <h3 class="text-base font-extrabold text-slate-800 uppercase tracking-widest">Reliability</h3>
          </div>
          <div class="flex divide-x divide-white">
            <div 
              v-for="group in (summary?.reliabilityGroups || [])" 
              :key="group.code"
              class="flex-1 flex flex-col items-center justify-center p-3 text-center"
              :class="groupColor(group.code)"
            >
              <div class="text-sm font-bold bg-white/90 px-2 py-0.5 rounded shadow-sm text-slate-800 mb-2 whitespace-nowrap">
                {{ formatPercentage(group.reliability, 4, 100) }}
              </div>
              <div class="text-sm font-extrabold uppercase">{{ group.code }}</div>
            </div>
          </div>
          <div class="bg-black text-white px-4 py-3 text-center flex flex-col items-center justify-center">
            <div class="text-3xl font-black mb-1">
              {{ formatPercentage(summary?.overallReliability, 2, 100) }}
            </div>
            <div class="text-sm font-bold uppercase tracking-[0.2em]">Overall System</div>
          </div>
        </div>

        <!-- AVAILABILITY -->
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
          <div class="bg-slate-100 px-4 py-2 border-b border-slate-200 text-center">
            <h3 class="text-base font-extrabold text-slate-800 uppercase tracking-widest">Availability</h3>
          </div>
          <div class="flex divide-x divide-white">
            <div 
              v-for="group in (summary?.reliabilityGroups || [])" 
              :key="group.code"
              class="flex-1 flex flex-col items-center justify-center p-3 text-center"
              :class="groupColor(group.code)"
            >
              <div class="text-sm font-bold bg-white/90 px-2 py-0.5 rounded shadow-sm text-slate-800 mb-2 whitespace-nowrap">
                {{ formatPercentage(group.availability, 2) }}
              </div>
              <div class="text-sm font-extrabold uppercase">{{ group.code }}</div>
            </div>
          </div>
          <div class="bg-black text-white px-4 py-3 text-center flex flex-col items-center justify-center">
            <div class="text-3xl font-black mb-1">
              {{ formatPercentage(summary?.overallAvailability, 2) }}
            </div>
            <div class="text-sm font-bold uppercase tracking-[0.2em]">Overall System</div>
          </div>
        </div>
      </section>

      <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 pb-5 gap-4">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Pilih Subsystem</h2>
          <p class="text-sm text-slate-500 mt-1">
            Pilih aset di bawah ini untuk melihat data keandalan dan mengisi formulir Trouble Report.
          </p>
        </div>
      </div>

      <div v-if="assetGroups.length" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section
          v-for="(group, groupIndex) in assetGroups"
          :key="group.name"
          class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col"
        >
          <div class="px-5 py-3 text-white" :style="groupHeaderStyle(groupIndex)">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
              <h3 class="font-bold text-lg flex items-center gap-2 min-w-0">
                <span class="w-2 h-2 rounded-full bg-white/60 shrink-0"></span>
                <span class="break-words">{{ group.name }}</span>
              </h3>
              <span class="text-[11px] font-semibold uppercase bg-white/15 border border-white/20 rounded px-2 py-1 whitespace-nowrap">
                {{ group.systems.length }} system
              </span>
            </div>
            <p class="text-xs text-white/80 mt-1">{{ group.assetCount }} aset - {{ group.unitCount }} unit</p>
          </div>

          <div class="p-5 flex-1 bg-slate-50/50">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div
                v-for="system in group.systems"
                :key="`${group.name}-${system.name}`"
                class="bg-white p-4 rounded-lg shadow-sm border border-slate-100 min-w-0"
              >
                <div class="flex items-start justify-between gap-3 mb-3">
                  <div class="min-w-0">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider break-words">{{ system.name }}</h4>
                    <p class="text-xs text-slate-500 mt-1">{{ system.assetCount }} aset - {{ system.unitCount }} unit</p>
                  </div>
                  <span class="text-[11px] font-semibold text-slate-500 bg-slate-100 rounded px-2 py-1 whitespace-nowrap">
                    {{ system.subsystems.length }} subsystem
                  </span>
                </div>

                <div v-if="system.subsystems.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <button
                    v-for="subsystem in system.subsystems"
                    :key="`${group.name}-${system.name}-${subsystem.name}`"
                    type="button"
                    class="subsystem-btn w-full"
                    :data-subsystem-name="subsystem.name"
                    @click="goToTroubleReport(subsystem.name)"
                  >
                    <span class="block font-semibold break-words">{{ subsystem.name }}</span>
                    <span class="block text-[11px] font-medium opacity-90 mt-1">{{ subsystem.assetCount }} aset - {{ subsystem.unitCount }} unit</span>
                  </button>
                </div>
                <p v-else class="text-xs text-slate-500 bg-slate-50 border border-dashed border-slate-200 rounded p-3">
                  Belum ada subsystem aktif.
                </p>
              </div>
            </div>
            <p v-if="!group.systems.length" class="text-sm text-slate-500 bg-white border border-dashed border-slate-200 rounded-lg p-4">
              Belum ada system atau subsystem aktif.
            </p>
          </div>
        </section>
      </div>

      <div v-else class="bg-white rounded-lg border border-dashed border-slate-300 p-8 text-center shadow-sm">
        <h3 class="text-base font-bold text-slate-800">Belum ada aset terhubung</h3>
        <p class="text-sm text-slate-500 mt-2">
          Tambahkan Master Asset yang terhubung ke kategori aset, system, dan subsystem untuk menampilkannya di dashboard.
        </p>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'
const props = defineProps({
  units: {
    type: Array,
    default: () => [],
  },
  selected_area: {
    type: String,
    default: null,
  },
  summary: {
    type: Object,
    default: () => ({}),
  },
  assets: {
    type: Array,
    default: () => [],
  },
  asset_categories: {
    type: Array,
    default: () => [],
  },
})

const fallbackLabel = 'Tanpa data'
const colors = [
  ['#7CB342', '#558B2F'],
  ['#D50000', '#B00020'],
  ['#0288D1', '#01579B'],
  ['#F9A825', '#EF6C00'],
  ['#6D28D9', '#4C1D95'],
  ['#0F766E', '#115E59'],
]

const getLabel = (value) => {
  const label = String(value ?? '').trim()
  return label || fallbackLabel
}

const formattedOperatingStartDate = computed(() => {
  if (!props.summary?.operatingStartDate) {
    return 'Data belum ada'
  }

  const date = new Date(`${props.summary.operatingStartDate}T00:00:00`)
  if (Number.isNaN(date.getTime())) {
    return 'Data belum ada'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
})

const formattedOperatingDays = computed(() => {
  const days = Number(props.summary?.operatingDays)

  return Number.isFinite(days) ? `${days} hari operasi` : 'Data belum ada'
})

const formatPercentage = (val, maxDecimals = 4, defaultValue = null) => {
  if (val === null || val === undefined) {
    if (defaultValue !== null) {
      return new Intl.NumberFormat('id-ID', { style: 'percent', minimumFractionDigits: maxDecimals, maximumFractionDigits: maxDecimals }).format(defaultValue / 100)
    }
    return '#DIV/0!'
  }
  return new Intl.NumberFormat('id-ID', { style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: maxDecimals }).format(val)
}

const groupColor = (code) => {
  const map = {
    PDSM: 'bg-[#92D050] text-white',
    PLSM: 'bg-[#4BACC6] text-white',
    PDSE: 'bg-[#FFFF00] text-slate-800',
    PLSE: 'bg-[#FFC000] text-slate-800',
    CDS: 'bg-[#FF0000] text-white',
  }
  return map[code] || 'bg-slate-500 text-white'
}

const sumUnits = (assets) => assets.reduce((total, asset) => {
  const units = Number(asset.jumlah_unit ?? 0)
  return total + (Number.isFinite(units) ? units : 0)
}, 0)

const makeNode = (name, id = null) => ({
  id,
  name,
  assets: [],
  children: new Map(),
})

const ensureChild = (children, name, id = null) => {
  if (!children.has(name)) {
    children.set(name, makeNode(name, id))
  }

  return children.get(name)
}

const assetGroups = computed(() => {
  const groups = new Map()

  props.asset_categories.forEach((category) => {
    const group = ensureChild(groups, getLabel(category.name), category.id)

    ;(category.systems ?? []).forEach((categorySystem) => {
      const system = ensureChild(group.children, getLabel(categorySystem.name), categorySystem.id)

      ;(categorySystem.subsystems ?? []).forEach((categorySubsystem) => {
        ensureChild(system.children, getLabel(categorySubsystem.name), categorySubsystem.id)
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
      const subsystems = Array.from(system.children.values()).map((subsystem) => ({
        name: subsystem.name,
        assetCount: subsystem.assets.length,
        unitCount: sumUnits(subsystem.assets),
      }))

      return {
        name: system.name,
        subsystems,
        assetCount: system.assets.length,
        unitCount: sumUnits(system.assets),
      }
    })

    return {
      name: group.name,
      systems,
      assetCount: group.assets.length,
      unitCount: sumUnits(group.assets),
    }
  })
})

const groupHeaderStyle = (index) => {
  const [from, to] = colors[index % colors.length]
  return {
    background: `linear-gradient(90deg, ${from}, ${to})`,
  }
}

const goToTroubleReport = (subsystemName) => {
  router.get('/trouble-report', {
    subsystem: subsystemName,
    ...(props.selected_area ? { area: props.selected_area } : {}),
  })
}
</script>

<style scoped>
.subsystem-btn {
  padding: 0.5rem 0.75rem;
  min-height: 48px;
  border: 1px solid #7a9d51;
  border-radius: 0.25rem;
  background-color: #8cb85c;
  color: #fff;
  box-shadow:
    0 4px 6px -1px rgb(0 0 0 / 10%),
    0 2px 4px -2px rgb(0 0 0 / 10%);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1rem;
  text-align: center;
  transition:
    color 150ms cubic-bezier(0.4, 0, 0.2, 1),
    background-color 150ms cubic-bezier(0.4, 0, 0.2, 1),
    border-color 150ms cubic-bezier(0.4, 0, 0.2, 1);
}

.subsystem-btn:hover {
  background-color: #7a9d51;
}
</style>
