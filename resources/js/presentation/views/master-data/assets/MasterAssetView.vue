<template>
  <MainLayout>
    <div class="space-y-6 pb-12">
      
      <!-- Premium Header Banner -->
      <div class="relative bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6] rounded-2xl p-6 overflow-hidden shadow-lg border border-blue-200">
        <!-- Abstract Decorative Elements -->
        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-20 w-32 h-32 bg-blue-300 opacity-20 rounded-full blur-2xl"></div>
        
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                <DatabaseIcon class="w-6 h-6 text-white" />
              </div>
              <h2 class="text-2xl font-extrabold text-white tracking-tight">Master Aset Prasarana</h2>
            </div>
            <p class="text-blue-100 text-sm max-w-xl">
              Pusat pengelolaan data hierarki System, Subsystem, dan inventaris fisik peralatan Sintel & LAA di seluruh wilayah kerja PT KAI.
            </p>
          </div>
          <BaseButton variant="primary" class="bg-white text-blue-700 hover:bg-blue-50 shadow-md border-none flex items-center gap-2 whitespace-nowrap">
            <PlusIcon class="w-4 h-4" />
            Tambah Aset Baru
          </BaseButton>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="p-3 bg-blue-50 text-blue-600 rounded-lg">
            <ServerIcon class="w-6 h-6" />
          </div>
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Aset</p>
            <p class="text-2xl font-bold text-slate-800">{{ assets.length }}</p>
          </div>
        </div>
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="p-3 bg-emerald-50 text-emerald-600 rounded-lg">
            <CheckCircleIcon class="w-6 h-6" />
          </div>
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Aset Aktif</p>
            <p class="text-2xl font-bold text-slate-800">{{ assets.length }}</p>
          </div>
        </div>
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="p-3 bg-amber-50 text-amber-600 rounded-lg">
            <SettingsIcon class="w-6 h-6" />
          </div>
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Subsystem</p>
            <p class="text-2xl font-bold text-slate-800">5</p>
          </div>
        </div>
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex items-center gap-4">
          <div class="p-3 bg-indigo-50 text-indigo-600 rounded-lg">
            <MapPinIcon class="w-6 h-6" />
          </div>
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Unit Kerja</p>
            <p class="text-2xl font-bold text-slate-800">Daop/Divre</p>
          </div>
        </div>
      </div>

      <!-- Toolbar & Filters -->
      <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <div class="relative w-full sm:w-96">
          <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Cari nama aset, lokasi, atau subsystem..." 
            class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
          />
        </div>
        <div class="flex gap-2 w-full sm:w-auto">
          <button class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-2 transition-colors">
            <FilterIcon class="w-4 h-4" /> Filter
          </button>
          <button class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-2 transition-colors">
            <DownloadIcon class="w-4 h-4" /> Export
          </button>
        </div>
      </div>

      <!-- Data Table -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-700 border-collapse">
            <thead class="bg-slate-50/80 border-b border-slate-200 text-xs text-slate-500 uppercase font-bold tracking-wider">
              <tr>
                <th class="py-4 px-5">Unit Kerja</th>
                <th class="py-4 px-5">System / Subsystem</th>
                <th class="py-4 px-5">Nama Aset & Lokasi</th>
                <th class="py-4 px-5 text-center">Jumlah Unit</th>
                <th class="py-4 px-5 text-center">Status</th>
                <th class="py-4 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="filteredAssets.length === 0">
                <td colspan="6" class="py-12 text-center text-slate-500">
                  <div class="flex flex-col items-center justify-center">
                    <DatabaseIcon class="w-10 h-10 text-slate-300 mb-3" />
                    <p class="text-base font-semibold">Tidak ada aset ditemukan</p>
                    <p class="text-xs mt-1">Coba sesuaikan pencarian Anda.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="asset in filteredAssets" :key="asset.id" class="hover:bg-blue-50/30 transition-colors group">
                <td class="py-4 px-5">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-blue-100 text-blue-800">
                    {{ asset.unit_kerja_id }}
                  </span>
                </td>
                <td class="py-4 px-5">
                  <p class="font-semibold text-slate-800">{{ asset.system }}</p>
                  <p class="text-xs text-slate-500 mt-0.5">{{ asset.subsystem }}</p>
                </td>
                <td class="py-4 px-5">
                  <div class="flex items-start gap-3">
                    <div class="p-2 bg-slate-100 rounded-lg text-slate-500 mt-0.5 group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                      <BoxIcon class="w-4 h-4" />
                    </div>
                    <div>
                      <p class="font-bold text-slate-800">{{ asset.nama_aset }}</p>
                      <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                        <MapPinIcon class="w-3 h-3" /> {{ asset.lokasi }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-5 text-center">
                  <span class="font-mono bg-slate-100 px-2 py-1 rounded text-slate-700 font-semibold">{{ asset.jumlah_unit }}</span>
                </td>
                <td class="py-4 px-5 text-center">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                  </span>
                </td>
                <td class="py-4 px-5 text-right whitespace-nowrap">
                  <button class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors mr-1" title="Edit">
                    <Edit2Icon class="w-4 h-4" />
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors mr-1" title="Hapus">
                    <Trash2Icon class="w-4 h-4" />
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors" title="Lainnya">
                    <MoreVerticalIcon class="w-4 h-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination Dummy -->
        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 flex items-center justify-between">
          <p class="text-xs text-slate-500">Menampilkan <span class="font-bold text-slate-700">{{ filteredAssets.length }}</span> data</p>
          <div class="flex gap-1">
            <button class="px-3 py-1 border border-slate-200 rounded text-xs font-semibold text-slate-400 bg-white cursor-not-allowed">Prev</button>
            <button class="px-3 py-1 border border-blue-500 rounded text-xs font-semibold text-white bg-blue-600">1</button>
            <button class="px-3 py-1 border border-slate-200 rounded text-xs font-semibold text-slate-600 bg-white hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>
      
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import MainLayout from '@/presentation/layouts/MainLayout.vue'
import BaseButton from '@/presentation/components/base/BaseButton.vue'
import { 
  DatabaseIcon, PlusIcon, ServerIcon, CheckCircleIcon, 
  SettingsIcon, MapPinIcon, SearchIcon, FilterIcon, 
  DownloadIcon, BoxIcon, Edit2Icon, Trash2Icon, MoreVerticalIcon 
} from 'lucide-vue-next'
import { MockAssetRepository } from '@/infrastructure/repositories/mock/mock-asset.repository'
import { GetAssetsUseCase } from '@/application/use-cases/get-assets.use-case'
import { useAuth } from '@/application/composables/useAuth'

const { currentUser, currentArea } = useAuth()
const assets = ref([])
const searchQuery = ref('')
const assetRepo = new MockAssetRepository()
const useCase = new GetAssetsUseCase(assetRepo)

onMounted(async () => {
  // Pass the actual current user
  assets.value = await useCase.execute(currentUser.value)
})

const filteredAssets = computed(() => {
  if (!searchQuery.value) return assets.value
  const query = searchQuery.value.toLowerCase()
  return assets.value.filter(a => 
    a.nama_aset.toLowerCase().includes(query) || 
    a.lokasi.toLowerCase().includes(query) ||
    a.subsystem.toLowerCase().includes(query)
  )
})
</script>
