<template>
  <Head><title>Matriks Risiko</title></Head>
  <MainLayout>
    <AreaSelectorBanner collapsible :units="units" :selected-area="selected_area" />

    <div class="space-y-5 pb-12">
      <header
        data-testid="risk-page-header"
        class="flex flex-col gap-4 rounded-md border border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex min-w-0 items-start gap-3">
          <AlertTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-orange-600" />
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-950">Matriks Risiko</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
              Ringkasan likelihood dan consequence untuk aset pada wilayah yang dipilih.
            </p>
          </div>
        </div>

        <Link
          data-testid="create-risk-assessment"
          :href="riskRegisterCreateUrl"
          class="inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-md bg-orange-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
        >
          <PlusIcon class="h-4 w-4" />
          Asesmen Risiko Baru
        </Link>
      </header>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(280px,0.8fr)]">
        <section class="overflow-hidden rounded-md border border-slate-200 bg-white" aria-labelledby="matrix-title">
          <header class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 id="matrix-title" class="font-semibold text-slate-950">Matriks L × C 4 × 4</h2>
              <p class="mt-1 text-xs text-slate-500">Angka menunjukkan skor risiko. Penanda gelap menunjukkan jumlah asesmen.</p>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600" aria-label="Legenda tingkat risiko">
              <span v-for="item in riskDistribution" :key="item.level" class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5" :class="item.swatchClass"></span>{{ item.label }}
              </span>
            </div>
          </header>

          <div class="overflow-x-auto px-4 py-6 sm:px-6">
            <div class="mx-auto w-max">
              <div class="mb-2 ml-28 grid grid-cols-4 gap-1.5 text-center">
                <div v-for="c in 4" :key="`column-${c}`" class="w-20">
                  <p class="text-xs font-semibold text-slate-800">C{{ c }}</p>
                  <p class="mt-0.5 text-[10px] text-slate-500">{{ consequenceLabels[c - 1] }}</p>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <p class="w-5 -rotate-90 whitespace-nowrap text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                  Likelihood
                </p>
                <div class="space-y-1.5">
                  <div v-for="likelihood in [4, 3, 2, 1]" :key="`row-${likelihood}`" class="flex items-center gap-2">
                    <div class="w-16 text-right">
                      <p class="text-xs font-semibold text-slate-800">L{{ likelihood }}</p>
                      <p class="text-[10px] text-slate-500">{{ likelihoodLabels[likelihood - 1] }}</p>
                    </div>

                    <div class="grid grid-cols-4 gap-1.5">
                      <div
                        v-for="consequence in 4"
                        :key="`cell-${likelihood}-${consequence}`"
                        :data-risk-level="getRiskLevel(likelihood, consequence)"
                        class="relative flex h-16 w-20 items-center justify-center border border-black/5 text-white"
                        :class="getRiskColorClass(likelihood, consequence)"
                        :title="`${getRiskLevelName(likelihood, consequence)} · ${getAssetCountInCell(likelihood, consequence)} asesmen`"
                      >
                        <span class="text-lg font-semibold">{{ likelihood * consequence }}</span>
                        <span
                          v-if="getAssetCountInCell(likelihood, consequence) > 0"
                          class="absolute right-1.5 top-1.5 min-w-5 bg-slate-950 px-1.5 py-0.5 text-center text-[10px] font-semibold text-white"
                        >
                          {{ getAssetCountInCell(likelihood, consequence) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <p class="ml-28 mt-4 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                Consequence (Dampak)
              </p>
            </div>
          </div>
        </section>

        <section class="overflow-hidden rounded-md border border-slate-200 bg-white" aria-labelledby="distribution-title">
          <header class="border-b border-slate-200 px-5 py-4">
            <h2 id="distribution-title" class="font-semibold text-slate-950">Distribusi Risiko</h2>
            <p class="mt-1 text-xs text-slate-500">Jumlah asesmen per tingkat risiko.</p>
          </header>

          <dl class="divide-y divide-slate-200 px-5">
            <div
              v-for="item in riskDistribution"
              :key="item.level"
              data-testid="risk-distribution-row"
              class="flex items-center justify-between gap-4 bg-white py-4"
            >
              <div class="flex items-center gap-3">
                <span class="h-3 w-3 shrink-0" :class="item.swatchClass"></span>
                <div>
                  <dt class="text-sm font-semibold text-slate-900">{{ item.label }}</dt>
                  <p class="mt-0.5 text-xs text-slate-500">{{ item.description }}</p>
                </div>
              </div>
              <dd class="text-xl font-semibold tabular-nums text-slate-950">{{ getCountByRiskLevel(item.level) }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <section class="overflow-hidden rounded-md border border-slate-200 bg-white" aria-labelledby="register-title">
        <header class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 id="register-title" class="font-semibold text-slate-950">Daftar asesmen</h2>
            <p class="mt-1 text-xs text-slate-500">Risiko aset yang membentuk matriks di atas.</p>
          </div>
          <div class="relative w-full sm:w-64">
            <SearchIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <label for="risk-matrix-search" class="sr-only">Cari subsystem</label>
            <input id="risk-matrix-search" type="search" placeholder="Cari subsystem" class="h-9 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-xs outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" />
          </div>
        </header>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[820px] border-collapse text-left text-sm text-slate-700">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3">ID / System</th>
                <th class="px-5 py-3">Subsystem</th>
                <th class="px-5 py-3 text-center">Likelihood</th>
                <th class="px-5 py-3 text-center">Consequence</th>
                <th class="px-5 py-3 text-center">Skor</th>
                <th class="px-5 py-3">Tingkat</th>
                <th class="px-5 py-3 text-right">Pembaruan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="riskAssets.length === 0">
                <td colspan="7" class="px-5 py-10 text-center text-slate-500">Belum ada hasil asesmen risiko.</td>
              </tr>
              <tr v-for="asset in riskAssets" :key="asset.id" class="hover:bg-slate-50/70">
                <td class="px-5 py-3">
                  <span class="font-mono text-xs text-slate-400">#{{ String(asset.id).padStart(4, '0') }}</span>
                  <p class="mt-0.5 text-xs font-semibold text-slate-800">{{ asset.system }}</p>
                </td>
                <td class="px-5 py-3 font-medium text-slate-900">{{ asset.subsystem }}</td>
                <td class="px-5 py-3 text-center font-semibold">L{{ asset.likelihood }}</td>
                <td class="px-5 py-3 text-center font-semibold">C{{ asset.consequence }}</td>
                <td class="px-5 py-3 text-center text-base font-semibold text-slate-950">{{ asset.likelihood * asset.consequence }}</td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-800">
                    <span class="h-2.5 w-2.5" :class="getRiskSwatchClass(asset.level)"></span>
                    {{ getRiskLevelLabel(asset.level) }}
                  </span>
                </td>
                <td class="px-5 py-3 text-right text-xs text-slate-500">{{ asset.last_update }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { AlertTriangleIcon, PlusIcon, SearchIcon } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'

const likelihoodLabels = ['Rare', 'Unlikely', 'Possible', 'Likely']
const consequenceLabels = ['Insignificant', 'Minor', 'Moderate', 'Major']

const riskLevelByCoordinate = {
  '1:1': 'Low', '1:2': 'Low', '1:3': 'Medium', '1:4': 'High',
  '2:1': 'Low', '2:2': 'Medium', '2:3': 'High', '2:4': 'Extreme',
  '3:1': 'Medium', '3:2': 'High', '3:3': 'Extreme', '3:4': 'Extreme',
  '4:1': 'High', '4:2': 'High', '4:3': 'Extreme', '4:4': 'Extreme',
}

const props = defineProps({
  selected_area: { type: String, default: null },
  units: { type: Array, default: () => [] },
  risks: { type: Array, default: () => [] },
})

const riskAssets = computed(() => props.risks)
const riskRegisterCreateUrl = computed(() => {
  const query = new URLSearchParams()
  if (props.selected_area) query.set('area', props.selected_area)
  query.set('create', '1')

  return `/risk-register?${query.toString()}`
})

const riskDistribution = [
  { level: 'Extreme', label: 'Ekstrem', description: 'Perlu penanganan segera', swatchClass: 'bg-rose-600' },
  { level: 'High', label: 'Tinggi', description: 'Perlu rencana mitigasi', swatchClass: 'bg-orange-500' },
  { level: 'Medium', label: 'Sedang', description: 'Perlu pemantauan', swatchClass: 'bg-amber-400' },
  { level: 'Low', label: 'Rendah', description: 'Dalam batas penerimaan', swatchClass: 'bg-emerald-500' },
]

const getAssetCountInCell = (likelihood, consequence) => riskAssets.value.filter(
  asset => asset.likelihood === likelihood && asset.consequence === consequence,
).length

const getCountByRiskLevel = level => riskAssets.value.filter(
  asset => (asset.level ?? getRiskLevel(asset.likelihood, asset.consequence)) === level,
).length

const getRiskLevel = (likelihood, consequence) => riskLevelByCoordinate[`${likelihood}:${consequence}`] ?? 'Low'
const getRiskLevelName = (likelihood, consequence) => `${getRiskLevelLabel(getRiskLevel(likelihood, consequence))} Risk`
const getRiskLevelLabel = level => ({ Extreme: 'Ekstrem', High: 'Tinggi', Medium: 'Sedang', Low: 'Rendah' }[level] ?? level)

const getRiskColorClass = (likelihood, consequence) => ({
  Extreme: 'bg-rose-600',
  High: 'bg-orange-500',
  Medium: 'bg-amber-400',
  Low: 'bg-emerald-500',
}[getRiskLevel(likelihood, consequence)])

const getRiskSwatchClass = level => ({
  Extreme: 'bg-rose-600',
  High: 'bg-orange-500',
  Medium: 'bg-amber-400',
  Low: 'bg-emerald-500',
}[level] ?? 'bg-slate-400')
</script>
