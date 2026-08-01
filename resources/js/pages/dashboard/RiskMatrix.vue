<template>
  <MainLayout>
    <AreaSelectorBanner :units="units" :selected-area="selected_area" />
    <div class="space-y-6 pb-12">
      <!-- Premium Header Banner -->
      <div class="relative bg-gradient-to-r from-slate-800 to-slate-900 rounded-2xl p-6 overflow-hidden shadow-lg border border-slate-700">
        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-white opacity-5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-20 w-32 h-32 bg-amber-500 opacity-10 rounded-full blur-2xl"></div>
        
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <div class="p-2 bg-slate-700/50 rounded-lg backdrop-blur-sm border border-slate-600">
                <AlertTriangleIcon class="w-6 h-6 text-amber-400" />
              </div>
              <h2 class="text-2xl font-extrabold text-white tracking-tight">Risk Matrix & Risk Register</h2>
            </div>
            <p class="text-slate-300 text-sm max-w-xl">
              Pemetaan tingkat risiko keamanan dan operasional aset berdasarkan analisis <span class="font-bold text-amber-400">Likelihood</span> (Kemungkinan) dan <span class="font-bold text-rose-400">Consequence</span> (Dampak).
            </p>
          </div>
          <BaseButton variant="primary" class="bg-amber-500 hover:bg-amber-600 text-white shadow-lg shadow-amber-500/20 border-none flex items-center gap-2 whitespace-nowrap">
            <PlusIcon class="w-4 h-4" />
            Asesmen Risiko Baru
          </BaseButton>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Risk Matrix 4x4 Visual (Takes up 2 columns on large screens) -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
              <GridIcon class="w-4 h-4 text-slate-500" />
              Matriks LxC 4x4
            </h3>
            <div class="flex gap-3 text-[10px] font-bold">
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-400"></span> Rendah</div>
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-yellow-400"></span> Sedang</div>
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-400"></span> Tinggi</div>
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-rose-600"></span> Ekstrem</div>
            </div>
          </div>
          
          <div class="p-6 flex-1 flex items-center justify-center relative overflow-x-auto">
            
            <div class="flex">
              <!-- Y-Axis Label (Likelihood) -->
              <div class="flex flex-col justify-center mr-4">
                <span class="text-xs font-bold text-slate-500 -rotate-90 uppercase tracking-widest whitespace-nowrap">Likelihood (Frekuensi)</span>
              </div>
              
              <!-- Matrix Grid -->
              <div class="relative">
                <div class="grid grid-cols-5 gap-1 w-max">
                  <!-- Empty top left -->
                  <div class="w-12 h-12"></div>
                  <!-- X-Axis Headers (Consequence) -->
                  <div class="w-20 h-12 flex flex-col items-center justify-end pb-2" v-for="c in 4" :key="'ch-'+c">
                    <span class="text-xs font-bold text-slate-700">C{{ c }}</span>
                    <span class="text-[9px] text-slate-400 leading-none text-center">{{ consequenceLabels[c-1] }}</span>
                  </div>
                  
                  <!-- Rows -->
                  <template v-for="l in 4" :key="'row-'+l">
                    <!-- Y-Axis Header -->
                    <div class="w-12 h-16 flex flex-col items-end justify-center pr-2">
                      <span class="text-xs font-bold text-slate-700">L{{ 5-l }}</span>
                      <span class="text-[9px] text-slate-400 leading-none text-right">{{ likelihoodLabels[4-l] }}</span>
                    </div>
                    
                    <!-- Cells -->
                    <div v-for="c in 4" :key="'cell-'+l+'-'+c"
                         class="w-20 h-16 rounded border border-white flex flex-col items-center justify-center transition-transform hover:scale-105 cursor-pointer shadow-sm relative group"
                         :class="getRiskColorClass(5-l, c)">
                      <span class="text-xl font-bold text-white/90 drop-shadow-md">{{ (5-l) * c }}</span>
                      
                      <!-- Tooltip (Count of assets in this cell) -->
                      <div v-if="getAssetCountInCell(5-l, c) > 0" class="absolute -top-2 -right-2 w-6 h-6 bg-slate-900 rounded-full text-white text-[10px] flex items-center justify-center font-bold shadow-md border-2 border-white z-10">
                        {{ getAssetCountInCell(5-l, c) }}
                      </div>
                      
                      <!-- Hover Detail -->
                      <div class="absolute invisible group-hover:visible opacity-0 group-hover:opacity-100 bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 bg-slate-800 text-white p-2 rounded text-xs shadow-xl z-20 pointer-events-none transition-all">
                        <p class="font-bold text-amber-400 mb-1 border-b border-slate-600 pb-1">Skor: {{ (5-l) * c }} ({{ getRiskLevelName(5-l, c) }})</p>
                        <p>L{{5-l}}: {{ likelihoodLabels[4-l] }}</p>
                        <p>C{{c}}: {{ consequenceLabels[c-1] }}</p>
                        <p class="mt-1 pt-1 border-t border-slate-600 text-emerald-400">{{ getAssetCountInCell(5-l, c) }} Aset dalam kategori ini</p>
                      </div>
                    </div>
                  </template>
                </div>
                
                <!-- X-Axis Bottom Label -->
                <div class="flex justify-center mt-4 pl-12">
                  <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Consequence (Dampak)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Risk Summary & Stats -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
              <PieChartIcon class="w-4 h-4 text-slate-500" />
              Distribusi Risiko
            </h3>
          </div>
          <div class="p-5 flex-1 flex flex-col gap-4">
            
            <div class="flex items-center justify-between p-3 rounded-lg bg-rose-50 border border-rose-100 cursor-pointer hover:bg-rose-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-10 bg-rose-500 rounded-full"></div>
                <div>
                  <p class="text-xs font-bold text-rose-800 uppercase">Extreme Risk</p>
                  <p class="text-[10px] text-rose-600">Kombinasi L/C ekstrem</p>
                </div>
              </div>
              <span class="text-2xl font-black text-rose-600">{{ getCountByRiskLevel('Extreme') }}</span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-lg bg-orange-50 border border-orange-100 cursor-pointer hover:bg-orange-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-10 bg-orange-400 rounded-full"></div>
                <div>
                  <p class="text-xs font-bold text-orange-800 uppercase">High Risk</p>
                  <p class="text-[10px] text-orange-600">Kombinasi L/C tinggi</p>
                </div>
              </div>
              <span class="text-2xl font-black text-orange-600">{{ getCountByRiskLevel('High') }}</span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-lg bg-yellow-50 border border-yellow-100 cursor-pointer hover:bg-yellow-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-10 bg-yellow-400 rounded-full"></div>
                <div>
                  <p class="text-xs font-bold text-yellow-800 uppercase">Medium Risk</p>
                  <p class="text-[10px] text-yellow-600">Kombinasi L/C sedang</p>
                </div>
              </div>
              <span class="text-2xl font-black text-yellow-600">{{ getCountByRiskLevel('Medium') }}</span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-lg bg-emerald-50 border border-emerald-100 cursor-pointer hover:bg-emerald-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-10 bg-emerald-400 rounded-full"></div>
                <div>
                  <p class="text-xs font-bold text-emerald-800 uppercase">Low Risk</p>
                  <p class="text-[10px] text-emerald-600">Kombinasi L/C rendah</p>
                </div>
              </div>
              <span class="text-2xl font-black text-emerald-600">{{ getCountByRiskLevel('Low') }}</span>
            </div>

          </div>
        </div>
      </div>

      <!-- Risk Register Table -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
          <h3 class="font-bold text-slate-800 flex items-center gap-2">
            <ListIcon class="w-4 h-4 text-slate-500" />
            Risk Register (Subsystem Terdaftar)
          </h3>
          <div class="relative w-64">
            <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" />
            <input type="text" placeholder="Cari subsystem..." class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-amber-500 outline-none" />
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-700 border-collapse">
            <thead class="bg-slate-50/50 border-b border-slate-200 text-[11px] text-slate-500 uppercase font-bold tracking-wider">
              <tr>
                <th class="py-3 px-5">ID / System</th>
                <th class="py-3 px-5">Subsystem</th>
                <th class="py-3 px-5 text-center">Likelihood (L)</th>
                <th class="py-3 px-5 text-center">Consequence (C)</th>
                <th class="py-3 px-5 text-center">Total Skor</th>
                <th class="py-3 px-5">Kategori Risiko</th>
                <th class="py-3 px-5 text-right">Update Terakhir</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="riskAssets.length === 0">
                <td colspan="7" class="py-8 px-5 text-center text-slate-500">Belum ada hasil asesmen risiko.</td>
              </tr>
              <tr v-for="asset in riskAssets" :key="asset.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3 px-5">
                  <span class="text-xs font-mono text-slate-400">#{{ String(asset.id).padStart(4, '0') }}</span>
                  <p class="font-bold text-slate-700 text-xs mt-0.5">{{ asset.system }}</p>
                </td>
                <td class="py-3 px-5 font-semibold text-slate-800">{{ asset.subsystem }}</td>
                <td class="py-3 px-5 text-center font-bold text-slate-600">L{{ asset.likelihood }}</td>
                <td class="py-3 px-5 text-center font-bold text-slate-600">C{{ asset.consequence }}</td>
                <td class="py-3 px-5 text-center font-black text-lg text-slate-800">{{ asset.likelihood * asset.consequence }}</td>
                <td class="py-3 px-5">
                  <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold shadow-sm" :class="getRiskBadgeClass(asset.level)">
                    {{ asset.level }} Risk
                  </span>
                </td>
                <td class="py-3 px-5 text-right text-xs text-slate-500">
                  {{ asset.last_update }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
    </div>
  </MainLayout>
</template>

<script setup>
import { computed } from 'vue'
import MainLayout from '@/layouts/MainLayout.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'
import { 
  AlertTriangleIcon, PlusIcon, GridIcon, PieChartIcon, 
  ListIcon, SearchIcon
} from 'lucide-vue-next'

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

const getAssetCountInCell = (l, c) => {
  return riskAssets.value.filter(a => a.likelihood === l && a.consequence === c).length
}

const getCountByRiskLevel = (level) => {
  return riskAssets.value.filter(a => (a.level ?? getRiskLevel(a.likelihood, a.consequence)) === level).length
}

const getRiskColorClass = (l, c) => {
  const level = getRiskLevel(l, c)
  if (level === 'Extreme') return 'bg-rose-500 hover:bg-rose-400'
  if (level === 'High') return 'bg-orange-400 hover:bg-orange-300'
  if (level === 'Medium') return 'bg-yellow-400 hover:bg-yellow-300'
  return 'bg-emerald-400 hover:bg-emerald-300'
}

const getRiskLevel = (l, c) => riskLevelByCoordinate[`${l}:${c}`] ?? 'Low'

const getRiskLevelName = (l, c) => {
  return `${getRiskLevel(l, c)} Risk`
}

const getRiskBadgeClass = (level) => {
  if (level === 'Extreme') return 'bg-rose-100 text-rose-700 border border-rose-200'
  if (level === 'High') return 'bg-orange-100 text-orange-700 border border-orange-200'
  if (level === 'Medium') return 'bg-yellow-100 text-yellow-700 border border-yellow-200'
  return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
}
</script>
