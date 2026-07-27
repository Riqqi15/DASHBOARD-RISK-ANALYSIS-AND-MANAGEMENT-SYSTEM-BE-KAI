<template>
  <MainLayout>
    <div class="space-y-6 pb-12">
      <!-- Premium Header Banner -->
      <div class="relative bg-gradient-to-r from-emerald-800 to-teal-700 rounded-2xl p-6 overflow-hidden shadow-lg border border-emerald-600">
        <div class="absolute top-0 left-0 -mt-10 -ml-10 w-40 h-40 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-10 w-32 h-32 bg-teal-300 opacity-20 rounded-full blur-2xl"></div>
        
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <div class="p-2 bg-emerald-900/50 rounded-lg backdrop-blur-sm border border-emerald-600">
                <BoxIcon class="w-6 h-6 text-emerald-400" />
              </div>
              <h2 class="text-2xl font-extrabold text-white tracking-tight">Predictive Inventory</h2>
            </div>
            <p class="text-emerald-100 text-sm max-w-xl">
              Pemantauan ketersediaan suku cadang secara *real-time* dengan proyeksi kebutuhan cerdas berdasarkan tren kerusakan (*Historical Data*).
            </p>
          </div>
          <BaseButton variant="primary" class="bg-emerald-500 hover:bg-emerald-600 text-white shadow-lg shadow-emerald-500/20 border-none flex items-center gap-2 whitespace-nowrap">
            <TrendingUpIcon class="w-4 h-4" />
            Generate Forecast
          </BaseButton>
        </div>
      </div>

      <!-- KPI Dashboard Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Card 1 -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
          <div class="absolute top-0 right-0 p-4 opacity-5">
            <LayersIcon class="w-16 h-16" />
          </div>
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Total Suku Cadang</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-black text-slate-800">425</span>
            <span class="text-xs font-semibold text-emerald-500 mb-1 flex items-center"><TrendingUpIcon class="w-3 h-3 mr-0.5"/> +12 Item</span>
          </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
          <div class="absolute top-0 right-0 p-4 opacity-5 text-rose-500">
            <AlertCircleIcon class="w-16 h-16" />
          </div>
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Stok Kritis (< 5 Unit)</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-black text-rose-600">8</span>
            <span class="text-xs font-semibold text-rose-500 mb-1">SKU</span>
          </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
          <div class="absolute top-0 right-0 p-4 opacity-5 text-amber-500">
            <ClockIcon class="w-16 h-16" />
          </div>
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Prediksi Defisit (30 Hari)</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-black text-amber-600">14</span>
            <span class="text-xs font-semibold text-slate-500 mb-1">Item akan habis</span>
          </div>
        </div>

        <!-- Card 4 -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
          <div class="absolute top-0 right-0 p-4 opacity-5 text-blue-500">
            <TruckIcon class="w-16 h-16" />
          </div>
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Dalam Pengiriman</p>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-black text-blue-600">3</span>
            <span class="text-xs font-semibold text-slate-500 mb-1">Pesanan (PO)</span>
          </div>
        </div>
      </div>

      <!-- Inventory Table with Progress Bars -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
          <h3 class="font-bold text-slate-800 flex items-center gap-2">
            <DatabaseIcon class="w-4 h-4 text-slate-500" />
            Daftar Inventaris & Proyeksi Kebutuhan
          </h3>
          <div class="flex gap-2">
            <div class="relative w-64">
              <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" />
              <input type="text" placeholder="Cari nama barang..." class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-emerald-500 outline-none" />
            </div>
            <button class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1 transition-colors">
              <FilterIcon class="w-3 h-3" /> Kategori
            </button>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-700 border-collapse">
            <thead class="bg-slate-50/50 border-b border-slate-200 text-[11px] text-slate-500 uppercase font-bold tracking-wider">
              <tr>
                <th class="py-3 px-5">Kode / Suku Cadang</th>
                <th class="py-3 px-5">Kategori Subsystem</th>
                <th class="py-3 px-5 text-center">Sisa Stok</th>
                <th class="py-3 px-5 text-center">Proyeksi (30 Hari)</th>
                <th class="py-3 px-5 w-64">Visualisasi Kapasitas (Stok vs Proyeksi)</th>
                <th class="py-3 px-5 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in inventoryData" :key="item.id" class="hover:bg-emerald-50/30 transition-colors">
                <td class="py-4 px-5">
                  <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ item.code }}</span>
                  <p class="font-bold text-slate-800 mt-1">{{ item.name }}</p>
                </td>
                <td class="py-4 px-5 text-xs font-semibold text-slate-600">{{ item.category }}</td>
                <td class="py-4 px-5 text-center font-black text-lg text-slate-700">{{ item.stock }}</td>
                <td class="py-4 px-5 text-center font-bold text-slate-500">{{ item.predicted_need }}</td>
                
                <!-- Progress Bar Column -->
                <td class="py-4 px-5">
                  <div class="w-full bg-slate-100 rounded-full h-2.5 mb-1 border border-slate-200 overflow-hidden relative">
                    <!-- Ratio Bar -->
                    <div class="h-2.5 rounded-full transition-all duration-500" :class="getProgressBarClass(item.stock, item.predicted_need)" :style="{ width: getPercentage(item.stock, Math.max(item.stock, item.predicted_need * 2)) + '%' }"></div>
                    
                    <!-- Predicted Need Marker (Red Line) -->
                    <div class="absolute top-0 bottom-0 w-0.5 bg-rose-500 z-10" :style="{ left: getPercentage(item.predicted_need, Math.max(item.stock, item.predicted_need * 2)) + '%' }" title="Batas Proyeksi Kebutuhan"></div>
                  </div>
                  <div class="flex justify-between text-[10px] font-bold text-slate-400 mt-1">
                    <span>Aman</span>
                    <span class="text-rose-500 flex items-center gap-1"><ArrowUpIcon class="w-2 h-2"/> Proyeksi</span>
                  </div>
                </td>

                <td class="py-4 px-5 text-center">
                  <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold shadow-sm" :class="getStatusBadgeClass(item.stock, item.predicted_need)">
                    {{ getStatusText(item.stock, item.predicted_need) }}
                  </span>
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
import { ref } from 'vue'
import MainLayout from '@/layouts/MainLayout.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { 
  BoxIcon, TrendingUpIcon, LayersIcon, AlertCircleIcon, ClockIcon, TruckIcon,
  DatabaseIcon, SearchIcon, FilterIcon, ArrowUpIcon
} from 'lucide-vue-next'

const inventoryData = ref([
  { id: 1, code: 'SP-TC-001', name: 'Relay Track', category: 'Track Circuit', stock: 12, predicted_need: 8 },
  { id: 2, code: 'SP-TC-004', name: 'Resistor 10 Ohm', category: 'Track Circuit', stock: 120, predicted_need: 30 },
  { id: 3, code: 'SP-IE-001', name: 'Modul CPU Interlocking', category: 'Interlocking Elektrik', stock: 1, predicted_need: 0 },
  { id: 4, code: 'SP-IE-002', name: 'Relay 24V DC', category: 'Interlocking Elektrik', stock: 3, predicted_need: 5 },
  { id: 5, code: 'SP-SU-001', name: 'Lampu LED Sinyal', category: 'Peraga Sinyal Utama', stock: 35, predicted_need: 12 },
  { id: 6, code: 'SP-PM-001', name: 'Kawat Tarik Wesel', category: 'Penggerak Wesel Mekanik', stock: 50, predicted_need: 15 },
  { id: 7, code: 'SP-SU-002', name: 'Lensa Sinyal', category: 'Peraga Sinyal Utama', stock: 2, predicted_need: 4 },
  { id: 8, code: 'SP-PE-001', name: 'Motor Point Wesel', category: 'Penggerak Wesel Elektrik', stock: 2, predicted_need: 1 },
])

const getPercentage = (value, max) => {
  if (max === 0) return 0
  return Math.min(100, Math.max(0, (value / max) * 100))
}

const getProgressBarClass = (stock, predicted) => {
  if (stock < predicted) return 'bg-rose-500' // Defisit
  if (stock < predicted * 1.5) return 'bg-amber-400' // Warning
  return 'bg-emerald-400' // Safe
}

const getStatusBadgeClass = (stock, predicted) => {
  if (stock < predicted) return 'bg-rose-100 text-rose-700 border border-rose-200'
  if (stock < predicted * 1.5) return 'bg-amber-100 text-amber-700 border border-amber-200'
  return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
}

const getStatusText = (stock, predicted) => {
  if (stock < predicted) return 'Defisit (Kritis)'
  if (stock < predicted * 1.5) return 'Warning'
  return 'Aman'
}
</script>
