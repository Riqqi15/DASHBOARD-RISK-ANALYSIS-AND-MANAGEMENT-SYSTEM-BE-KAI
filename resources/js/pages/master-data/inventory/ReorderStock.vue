<template>
  <MainLayout>
    <AreaSelectorBanner :units="units" :selected-area="selected_area" />

    <div class="space-y-6 pb-12">
      <div class="relative bg-gradient-to-r from-violet-800 to-fuchsia-700 rounded-2xl p-6 overflow-hidden shadow-lg border border-violet-600">
        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="relative z-10 flex items-center gap-3">
          <div class="p-2 bg-violet-900/50 rounded-lg border border-violet-600">
            <ShoppingCartIcon class="w-6 h-6 text-fuchsia-400" />
          </div>
          <div>
            <h2 class="text-2xl font-extrabold text-white tracking-tight">Reorder Stock (Pengadaan)</h2>
            <p class="text-violet-100 text-sm">Usulan kuantitas berdasarkan stok aktual dan reorder point pada database lokal.</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg">
              <ListChecksIcon class="w-5 h-5 text-violet-600" />
              Keranjang Usulan Pengadaan
            </h3>
            <button @click="toggleAll" class="text-xs font-bold text-violet-600 hover:text-violet-800 underline">
              {{ allSelected ? 'Batal Pilih' : 'Pilih Semua' }}
            </button>
          </div>

          <div v-if="reorderItems.length === 0" class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500 shadow-sm">
            Tidak ada stok yang mencapai reorder point.
          </div>

          <div
            v-for="item in reorderItems"
            :key="item.id"
            class="bg-white rounded-xl border-l-4 border-t border-r border-b border-slate-200 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:shadow-md transition-shadow"
            :class="item.quantity < item.predicted_need ? 'border-l-rose-500' : 'border-l-amber-500'"
          >
            <div class="flex items-start gap-4">
              <input v-model="item.selected" type="checkbox" class="mt-1 w-4 h-4 text-violet-600 rounded border-slate-300 focus:ring-violet-500" />
              <div>
                <span
                  class="inline-block px-2 py-0.5 rounded text-[10px] font-bold mb-1"
                  :class="item.quantity < item.predicted_need ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'"
                >
                  {{ item.quantity < item.predicted_need ? 'KRITIS' : 'MENIPIS' }}
                </span>
                <p class="font-bold text-slate-800 text-base">{{ item.name }}</p>
                <p class="text-xs text-slate-500">{{ item.code }} &bull; {{ item.subsystem }}</p>
                <div class="flex items-center gap-4 mt-2">
                  <p class="text-[11px] font-semibold text-slate-600">Sisa Stok: <span class="text-rose-600 font-bold">{{ item.quantity }} Unit</span></p>
                  <p class="text-[11px] font-semibold text-slate-600">Reorder Point: <span class="font-bold">{{ item.reorder_point }} Unit</span></p>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div class="flex flex-col items-center">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Qty Pengajuan</span>
                <div class="flex items-center mt-1">
                  <button @click="decreaseQuantity(item)" class="w-8 h-8 rounded-l border border-slate-300 bg-slate-50 hover:bg-slate-100">-</button>
                  <input v-model.number="item.request_quantity" type="number" min="1" class="w-14 h-8 border-t border-b border-slate-300 text-center text-sm font-bold text-slate-800 outline-none" />
                  <button @click="item.request_quantity += 1" class="w-8 h-8 rounded-r border border-slate-300 bg-slate-50 hover:bg-slate-100">+</button>
                </div>
              </div>
              <button @click="removeProposal(item.id)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors mt-4">
                <Trash2Icon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="bg-white rounded-xl border border-slate-200 shadow-lg sticky top-6 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
              <FileTextIcon class="w-5 h-5 text-slate-700" />
              <h3 class="font-bold text-slate-800">Ringkasan Pengajuan</h3>
            </div>

            <div class="p-5 space-y-4">
              <div class="flex justify-between items-center text-sm">
                <span class="text-slate-600">Total Item Terpilih</span>
                <span class="font-bold text-slate-800">{{ selectedItems.length }} SKU</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <span class="text-slate-600">Total Kuantitas</span>
                <span class="font-bold text-slate-800">{{ totalRequestQuantity }} Unit</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <span class="text-slate-600">Tingkat Urgensi</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">TINGGI</span>
              </div>
            </div>

            <div class="p-5 bg-slate-50 border-t border-slate-200">
              <button disabled class="w-full py-3 bg-slate-400 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 cursor-not-allowed">
                <SendIcon class="w-4 h-4" />
                Endpoint Pengadaan Belum Tersedia
              </button>
              <p class="text-center text-[10px] text-slate-500 mt-3">Data stok sudah nyata; penyimpanan purchase request belum diaktifkan.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'
import { ShoppingCartIcon, ListChecksIcon, Trash2Icon, FileTextIcon, SendIcon } from 'lucide-vue-next'

const props = defineProps({
  selected_area: { type: String, default: null },
  units: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
})

const reorderItems = ref([])

watch(
  () => props.items,
  items => {
    reorderItems.value = items.map(item => ({
      ...item,
      quantity: Number(item.quantity),
      predicted_need: Number(item.predicted_need),
      reorder_point: Number(item.reorder_point),
      selected: true,
      request_quantity: Math.max(1, Number(item.reorder_point) - Number(item.quantity)),
    }))
  },
  { immediate: true },
)

const selectedItems = computed(() => reorderItems.value.filter(item => item.selected))
const totalRequestQuantity = computed(() => selectedItems.value.reduce((total, item) => total + Math.max(1, Number(item.request_quantity) || 1), 0))
const allSelected = computed(() => reorderItems.value.length > 0 && selectedItems.value.length === reorderItems.value.length)

const toggleAll = () => {
  const nextValue = !allSelected.value
  reorderItems.value.forEach(item => { item.selected = nextValue })
}

const decreaseQuantity = (item) => {
  item.request_quantity = Math.max(1, Number(item.request_quantity) - 1)
}

const removeProposal = (id) => {
  reorderItems.value = reorderItems.value.filter(item => item.id !== id)
}
</script>
