<template>
  <MainLayout>
    <AreaSelectorBanner />
    <div class="space-y-8 pb-10">
      <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 pb-5 gap-4">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Pilih Subsystem</h2>
          <p class="text-sm text-slate-500 mt-1">
            Pilih aset di bawah ini untuk melihat data keandalan dan mengisi formulir Trouble Report.
          </p>
        </div>
      </div>

      <!-- Hirarki Aset (Modern Card Layout) -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        
        <!-- PDSM -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col">
          <div class="bg-gradient-to-r from-[#7CB342] to-[#558B2F] px-5 py-3">
            <h3 class="text-white font-bold text-lg flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-white/50"></span>
              Peralatan Dalam Sinyal Mekanik (PDSM)
            </h3>
          </div>
          <div class="p-5 flex-1 bg-slate-50/50">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Interlocking Mekanik</h4>
              <div class="flex flex-wrap gap-3">
                <button @click="goToTroubleReport('Interlocking Mekanik')" class="subsystem-btn">
                  Interlocking Mekanik
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- CDS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col">
          <div class="bg-gradient-to-r from-red-600 to-red-800 px-5 py-3">
            <h3 class="text-white font-bold text-lg flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-white/50"></span>
              Catu Daya Sinyal (CDS)
            </h3>
          </div>
          <div class="p-5 flex-1 bg-slate-50/50">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Catu Daya Sinyal</h4>
              <div class="flex flex-wrap gap-3">
                <button @click="goToTroubleReport('Catu Daya Sinyal')" class="subsystem-btn">
                  Catu Daya Sinyal
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PLSM -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300">
        <div class="bg-gradient-to-r from-[#0288D1] to-[#01579B] px-5 py-3">
          <h3 class="text-white font-bold text-lg flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-white/50"></span>
            Peralatan Luar Sinyal Mekanik (PLSM)
          </h3>
        </div>
        <div class="p-5 bg-slate-50/50">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pendeteksi Sarana</h4>
              <button @click="goToTroubleReport('Kontak Deteksi')" class="subsystem-btn w-full">Kontak Deteksi</button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pengaman Wesel</h4>
              <button @click="goToTroubleReport('Pengaman Wesel Setempat Mekanik')" class="subsystem-btn w-full">Pengaman wesel setempat</button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pengontrol Kedudukan</h4>
              <button @click="goToTroubleReport('Pengontrol dan Petunjuk Kedudukan Wesel Mekanik')" class="subsystem-btn w-full">Pengontrol & Petunjuk Wesel</button>
            </div>
            
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Penggerak Wesel</h4>
              <button @click="goToTroubleReport('Penggerak Wesel Mekanik')" class="subsystem-btn w-full">Penggerak Wesel Mekanik</button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 xl:col-span-1 md:col-span-2">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Peraga Sinyal</h4>
              <div class="flex flex-col gap-2">
                <button @click="goToTroubleReport('Peraga Sinyal Mekanik Utama')" class="subsystem-btn w-full">Utama</button>
                <button @click="goToTroubleReport('Peraga Sinyal Mekanik Pembantu')" class="subsystem-btn w-full">Pembantu</button>
                <button @click="goToTroubleReport('Peraga Sinyal Mekanik Pelengkap')" class="subsystem-btn w-full">Pelengkap</button>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- PDSE & PLSE -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        
        <!-- PDSE -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col xl:col-span-1">
          <div class="bg-gradient-to-r from-[#FBC02D] to-[#F57F17] px-5 py-3">
            <h3 class="text-white font-bold text-lg flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-white/50"></span>
              Peralatan Dalam Sinyal Elektrik (PDSE)
            </h3>
          </div>
          <div class="p-5 flex-1 bg-slate-50/50">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
              <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Interlocking Elektrik</h4>
              <button @click="goToTroubleReport('Interlocking Elektrik')" class="subsystem-btn w-full">Interlocking Elektrik</button>
            </div>
          </div>
        </div>

        <!-- PLSE -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300 xl:col-span-2 flex flex-col">
          <div class="bg-gradient-to-r from-[#FBC02D] to-[#F57F17] px-5 py-3">
            <h3 class="text-white font-bold text-lg flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-white/50"></span>
              Peralatan Luar Sinyal Elektrik (PLSE)
            </h3>
          </div>
          <div class="p-5 flex-1 bg-slate-50/50">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              
              <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Penggerak Wesel</h4>
                <button @click="goToTroubleReport('Penggerak Wesel Elektrik')" class="subsystem-btn w-full">Penggerak Wesel Elektrik</button>
              </div>

              <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Peraga Sinyal</h4>
                <div class="flex flex-col gap-2">
                  <button @click="goToTroubleReport('Peraga Sinyal Elektrik Utama')" class="subsystem-btn w-full">Utama</button>
                  <button @click="goToTroubleReport('Peraga Sinyal Elektrik Pembantu')" class="subsystem-btn w-full">Pembantu</button>
                  <button @click="goToTroubleReport('Peraga Sinyal Elektrik Pelengkap')" class="subsystem-btn w-full">Pelengkap</button>
                </div>
              </div>

              <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pengaman Wesel</h4>
                <button @click="goToTroubleReport('Pengaman Wesel Setempat Elektrik')" class="subsystem-btn w-full">Pengaman Wesel Setempat</button>
              </div>

              <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Deteksi Sarana</h4>
                <div class="flex flex-col gap-2">
                  <button @click="goToTroubleReport('Track Circuit')" class="subsystem-btn w-full">Track Circuit</button>
                  <button @click="goToTroubleReport('Axle Counter')" class="subsystem-btn w-full">Axle Counter</button>
                </div>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, watchEffect } from 'vue'
import { router } from '@inertiajs/vue3'
import { useAuth } from '@/application/composables/useAuth'
import { DummyRamsRepository } from '@/infrastructure/repositories/dummy-rams.repository'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'
import { 
  TrainTrackIcon, SettingsIcon, GitMergeIcon,
  VideoIcon, ActivityIcon, PhoneIcon, BellIcon, RouterIcon
} from 'lucide-vue-next'

const { currentUser, currentArea } = useAuth()
const repository = new DummyRamsRepository()

const assets = ref([])

const loadAssets = async () => {
  try {
    const unitKerjaId = currentArea.value
    assets.value = await repository.getAssets(unitKerjaId)
  } catch (error) {
    console.error("Failed to load assets", error)
  }
}

onMounted(() => {
  loadAssets()
})

// Auto reload assets if currentArea changes
watchEffect(() => {
  if (currentArea.value !== undefined) {
    loadAssets()
  }
})

const goToTroubleReport = (subsystemName) => {
  // Navigate to trouble report view with the subsystem name as a query/param
  // We use query parameter here for simplicity and to handle spaces easily
  router.visit(`/trouble-report?subsystem=${encodeURIComponent(subsystemName)}`)
}
</script>

<style scoped>
@reference "../../../css/app.css";
.subsystem-btn {
  @apply bg-[#8CB85C] hover:bg-[#7a9d51] text-white text-xs font-semibold py-2 px-3 rounded shadow-sm transition-colors text-center shadow-md border border-[#7a9d51];
  min-height: 48px;
}
</style>
