<template>
  <MainLayout>
    <div class="space-y-6 pb-12">
      <!-- Premium Header Banner -->
      <div class="relative bg-gradient-to-r from-violet-800 to-fuchsia-700 rounded-2xl p-6 overflow-hidden shadow-lg border border-violet-600">
        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-20 w-32 h-32 bg-fuchsia-300 opacity-20 rounded-full blur-2xl"></div>
        
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <div class="p-2 bg-violet-900/50 rounded-lg backdrop-blur-sm border border-violet-600">
                <ShoppingCartIcon class="w-6 h-6 text-fuchsia-400" />
              </div>
              <h2 class="text-2xl font-extrabold text-white tracking-tight">Reorder Stock (Pengadaan)</h2>
            </div>
            <p class="text-violet-100 text-sm max-w-xl">
              Daftar usulan pengajuan pembelian suku cadang berdasarkan analisis level minimum (*Reorder Point*) dan *Predictive Deficit*.
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Reorder List (Takes 2 Columns) -->
        <div class="lg:col-span-2 space-y-4">
          
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg">
              <ListChecksIcon class="w-5 h-5 text-violet-600" />
              Keranjang Usulan Pengadaan
            </h3>
            <button class="text-xs font-bold text-violet-600 hover:text-violet-800 underline">Pilih Semua</button>
          </div>

          <!-- Item Card 1: Critical -->
          <div class="bg-white rounded-xl border-l-4 border-l-rose-500 border-t border-r border-b border-slate-200 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 group hover:shadow-md transition-shadow">
            <div class="flex items-start gap-4">
              <input type="checkbox" checked class="mt-1 w-4 h-4 text-violet-600 rounded border-slate-300 focus:ring-violet-500" />
              <div>
                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 mb-1">KRITIS</span>
                <p class="font-bold text-slate-800 text-base">Modul CPU Interlocking</p>
                <p class="text-xs text-slate-500">SP-IE-001 • Interlocking Elektrik</p>
                <div class="flex items-center gap-4 mt-2">
                  <p class="text-[11px] font-semibold text-slate-600">Sisa Stok: <span class="text-rose-600 font-bold">1 Unit</span></p>
                  <p class="text-[11px] font-semibold text-slate-600">Kebutuhan Proyeksi: <span class="font-bold">3 Unit</span></p>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="flex flex-col items-center">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Qty Pengajuan</span>
                <div class="flex items-center mt-1">
                  <button class="w-8 h-8 rounded-l border border-slate-300 bg-slate-50 flex items-center justify-center hover:bg-slate-100">-</button>
                  <input type="text" value="5" class="w-12 h-8 border-t border-b border-slate-300 text-center text-sm font-bold text-slate-800 outline-none" />
                  <button class="w-8 h-8 rounded-r border border-slate-300 bg-slate-50 flex items-center justify-center hover:bg-slate-100">+</button>
                </div>
              </div>
              <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors mt-4">
                <Trash2Icon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Item Card 2: Warning -->
          <div class="bg-white rounded-xl border-l-4 border-l-amber-500 border-t border-r border-b border-slate-200 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 group hover:shadow-md transition-shadow">
            <div class="flex items-start gap-4">
              <input type="checkbox" checked class="mt-1 w-4 h-4 text-violet-600 rounded border-slate-300 focus:ring-violet-500" />
              <div>
                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 mb-1">MENIPIS</span>
                <p class="font-bold text-slate-800 text-base">Relay 24V DC</p>
                <p class="text-xs text-slate-500">SP-IE-002 • Interlocking Elektrik</p>
                <div class="flex items-center gap-4 mt-2">
                  <p class="text-[11px] font-semibold text-slate-600">Sisa Stok: <span class="text-amber-600 font-bold">3 Unit</span></p>
                  <p class="text-[11px] font-semibold text-slate-600">Kebutuhan Proyeksi: <span class="font-bold">5 Unit</span></p>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="flex flex-col items-center">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Qty Pengajuan</span>
                <div class="flex items-center mt-1">
                  <button class="w-8 h-8 rounded-l border border-slate-300 bg-slate-50 flex items-center justify-center hover:bg-slate-100">-</button>
                  <input type="text" value="20" class="w-12 h-8 border-t border-b border-slate-300 text-center text-sm font-bold text-slate-800 outline-none" />
                  <button class="w-8 h-8 rounded-r border border-slate-300 bg-slate-50 flex items-center justify-center hover:bg-slate-100">+</button>
                </div>
              </div>
              <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors mt-4">
                <Trash2Icon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Item Card 3: Warning -->
          <div class="bg-white rounded-xl border-l-4 border-l-amber-500 border-t border-r border-b border-slate-200 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 group hover:shadow-md transition-shadow">
            <div class="flex items-start gap-4">
              <input type="checkbox" checked class="mt-1 w-4 h-4 text-violet-600 rounded border-slate-300 focus:ring-violet-500" />
              <div>
                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 mb-1">MENIPIS</span>
                <p class="font-bold text-slate-800 text-base">Motor Point Wesel</p>
                <p class="text-xs text-slate-500">SP-PE-001 • Penggerak Wesel Elektrik</p>
                <div class="flex items-center gap-4 mt-2">
                  <p class="text-[11px] font-semibold text-slate-600">Sisa Stok: <span class="text-amber-600 font-bold">2 Unit</span></p>
                  <p class="text-[11px] font-semibold text-slate-600">Kebutuhan Proyeksi: <span class="font-bold">1 Unit</span></p>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="flex flex-col items-center">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Qty Pengajuan</span>
                <div class="flex items-center mt-1">
                  <button class="w-8 h-8 rounded-l border border-slate-300 bg-slate-50 flex items-center justify-center hover:bg-slate-100">-</button>
                  <input type="text" value="10" class="w-12 h-8 border-t border-b border-slate-300 text-center text-sm font-bold text-slate-800 outline-none" />
                  <button class="w-8 h-8 rounded-r border border-slate-300 bg-slate-50 flex items-center justify-center hover:bg-slate-100">+</button>
                </div>
              </div>
              <button class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors mt-4">
                <Trash2Icon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <button class="w-full py-4 border-2 border-dashed border-slate-300 rounded-xl text-slate-500 font-bold hover:border-violet-400 hover:text-violet-600 hover:bg-violet-50 transition-all flex items-center justify-center gap-2">
            <PlusIcon class="w-5 h-5" />
            Tambah Item Manual
          </button>

        </div>

        <!-- Order Summary (Sidebar) -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-xl border border-slate-200 shadow-lg sticky top-6 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
              <FileTextIcon class="w-5 h-5 text-slate-700" />
              <h3 class="font-bold text-slate-800">Ringkasan Pengajuan</h3>
            </div>
            
            <div class="p-5 space-y-4">
              <div class="flex justify-between items-center text-sm">
                <span class="text-slate-600">Total Item Terpilih</span>
                <span class="font-bold text-slate-800">3 SKU</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <span class="text-slate-600">Total Kuantitas</span>
                <span class="font-bold text-slate-800">35 Unit</span>
              </div>
              <div class="flex justify-between items-center text-sm">
                <span class="text-slate-600">Tingkat Urgensi</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">TINGGI</span>
              </div>

              <div class="pt-4 border-t border-slate-200">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                <textarea rows="3" placeholder="Misal: Mohon prioritaskan CPU Interlocking..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-none"></textarea>
              </div>
            </div>

            <div class="p-5 bg-slate-50 border-t border-slate-200">
              <button class="w-full py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-violet-500/30 flex items-center justify-center gap-2 transition-all transform hover:scale-[1.02]">
                <SendIcon class="w-4 h-4" />
                Generate Purchase Request
              </button>
              <p class="text-center text-[10px] text-slate-500 mt-3">Sistem akan membuat dokumen PR resmi untuk diteruskan ke Divisi Pengadaan.</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/layouts/MainLayout.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { 
  ShoppingCartIcon, ListChecksIcon, Trash2Icon, PlusIcon, 
  FileTextIcon, SendIcon
} from 'lucide-vue-next'
</script>
