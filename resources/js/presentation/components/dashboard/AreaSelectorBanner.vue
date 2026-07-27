<template>
  <div v-if="currentUser.isPusat()" class="bg-[#BCE4F9] rounded-xl border-2 border-black overflow-hidden mb-8 shadow-md">
    <div class="bg-[#BCE4F9] py-2 border-b-2 border-black text-center">
      <h2 class="text-xl font-extrabold text-black uppercase tracking-widest">Area Lintas</h2>
    </div>
    <div class="p-4 flex flex-col items-center gap-4">
      
      <!-- Lintas Utama (Blue) -->
      <div class="flex flex-wrap justify-center gap-3 md:gap-5 w-full">
        <button 
          v-for="lintas in lintasUtama" 
          :key="lintas.id"
          class="bg-[#4A72B2] hover:bg-[#3A5A8F] text-white font-bold py-3 px-4 rounded-lg border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] text-xs md:text-sm transition-transform active:translate-y-1 active:shadow-none min-w-[140px] text-center"
        >
          {{ lintas.name }}
        </button>
      </div>

      <!-- Daop & Divre (Purple) -->
      <div class="flex flex-wrap justify-center gap-2 md:gap-3 w-full mt-2">
        <button 
          @click="selectArea(null)"
          class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-3 rounded-lg border-2 border-black text-xs md:text-sm transition-all"
          :class="{'ring-4 ring-offset-2 ring-blue-500 shadow-[0_0_15px_rgba(59,130,246,0.5)]': activeArea === null}"
        >
          Nasional (Pusat)
        </button>
        <button 
          v-for="area in daopDivreList" 
          :key="area.id"
          @click="selectArea(area.id)"
          class="bg-[#7030A0] hover:bg-[#582182] text-white font-bold py-2 px-3 rounded-lg border-2 border-black text-xs md:text-sm transition-all min-w-[80px] text-center"
          :class="{'ring-4 ring-offset-2 ring-blue-500 shadow-[0_0_15px_rgba(59,130,246,0.5)]': activeArea === area.id}"
        >
          {{ area.name }}
        </button>
      </div>
    </div>
    <div class="bg-[#BCE4F9] py-4 border-t-2 border-black text-center mt-2">
      <h1 class="text-2xl md:text-3xl font-extrabold text-black uppercase tracking-wider">Dashboard Risk Analysis and Management System</h1>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuth } from '@/application/composables/useAuth'

const { currentUser, activeArea, setArea } = useAuth()

const lintasUtama = [
  { id: 'L1', name: 'Semarang Tawang -\nSurabaya Pasar Turi' },
  { id: 'L2', name: 'Jakarta Kota - Gambir' },
  { id: 'L3', name: 'Muara Enim - Prabumulih' },
  { id: 'L4', name: 'Kota Bumi - Tarahan' }
]

const daopDivreList = [
  { id: 'DAOP1', name: 'Daop 1' },
  { id: 'DAOP2', name: 'Daop 2' },
  { id: 'DAOP3', name: 'Daop 3' },
  { id: 'DAOP4', name: 'Daop 4' },
  { id: 'DAOP5', name: 'Daop 5' },
  { id: 'DAOP6', name: 'Daop 6' },
  { id: 'DAOP7', name: 'Daop 7' },
  { id: 'DAOP8', name: 'Daop 8' },
  { id: 'DAOP9', name: 'Daop 9' },
  { id: 'DIVRE1', name: 'Divre I' },
  { id: 'DIVRE2', name: 'Divre II' },
  { id: 'DIVRE3', name: 'Divre III' },
  { id: 'DIVRE4', name: 'Divre IV' }
]

const selectArea = (id) => {
  setArea(id)
}
</script>
