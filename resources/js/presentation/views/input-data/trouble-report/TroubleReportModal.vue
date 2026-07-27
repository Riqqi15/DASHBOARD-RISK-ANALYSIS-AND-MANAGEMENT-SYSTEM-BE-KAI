<template>
  <div v-if="isOpen" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="close"></div>
    
    <!-- Modal Content -->
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col transform transition-all">
      
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
        <div>
          <h3 class="text-lg font-bold text-slate-800">{{ subsystemName }}</h3>
          <p class="text-xs text-slate-500">Input Data Laporan Gangguan (Trouble Report)</p>
        </div>
        <button @click="close" class="text-slate-400 hover:text-slate-600 transition-colors p-2 rounded-full hover:bg-slate-100">
          <XIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- Body / Form -->
      <div class="p-6 overflow-y-auto flex-1">
        <form @submit.prevent="submitForm" class="space-y-6">
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
            <!-- Left Column -->
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Lokasi</label>
                <input v-model="form.lokasi" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Resor</label>
                <input v-model="form.resor" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">QC</label>
                <input v-model="form.qc" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Failure Event</label>
                <textarea v-model="form.failure_event" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none resize-none" required></textarea>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Penyebab</label>
                <textarea v-model="form.penyebab" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none resize-none" required></textarea>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tindakan</label>
                <textarea v-model="form.tindakan" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none resize-none" required></textarea>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Penggantian Sparepart</label>
                  <select v-model="form.penggantian_sparepart" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none">
                    <option value="Tidak">Tidak</option>
                    <option value="Ya">Ya</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Tindak Vandalisme</label>
                  <select v-model="form.tindak_vandalisme" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none">
                    <option value="Tidak">Tidak</option>
                    <option value="Ya">Ya</option>
                  </select>
                </div>
              </div>

              <!-- Extra Fields for Sparepart -->
              <div v-if="form.penggantian_sparepart === 'Ya'" class="grid grid-cols-3 gap-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-amber-900 mb-1">Nama Sparepart Diganti</label>
                  <input 
                    list="sparepart-list" 
                    v-model="form.nama_sparepart" 
                    type="text" 
                    placeholder="Ketik atau pilih sparepart..." 
                    class="w-full px-3 py-2 border border-amber-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none" 
                    required 
                  />
                  <datalist id="sparepart-list">
                    <option v-for="sp in availableSpareparts" :key="sp.name" :value="sp.name"></option>
                  </datalist>
                  <p v-if="form.nama_sparepart && currentSparepartStock !== null" class="mt-1 text-[10px] font-semibold" :class="currentSparepartStock < 5 ? 'text-rose-600' : 'text-emerald-600'">
                    Stock Tersedia: {{ currentSparepartStock }} unit
                  </p>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-amber-900 mb-1">Jumlah</label>
                  <input 
                    v-model="form.jumlah_sparepart" 
                    type="number" 
                    min="1" 
                    :max="currentSparepartStock || undefined"
                    @input="validateStockAmount"
                    placeholder="Qty" 
                    class="w-full px-3 py-2 border border-amber-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none" 
                    required 
                  />
                  <p v-if="stockWarningMessage" class="mt-1 text-[10px] text-rose-600 font-semibold">{{ stockWarningMessage }}</p>
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-4">
              <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-4">
                <h4 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2 mb-2">Waktu Kejadian & Penanganan</h4>
                
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Tahun Kejadian</label>
                  <input :value="computedTahun" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-100 text-slate-500 cursor-not-allowed" readonly />
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tgl Kejadian</label>
                    <input v-model="form.tanggal_kejadian" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tgl Penanganan</label>
                    <input v-model="form.tanggal_penanganan" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mulai (Jam)</label>
                    <input v-model="form.mulai" type="time" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Selesai (Jam)</label>
                    <input v-model="form.selesai" type="time" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                </div>
              </div>

              <div class="p-4 bg-indigo-50/50 rounded-xl border border-indigo-100 space-y-3 mt-4">
                <h4 class="text-sm font-bold text-indigo-800 border-b border-indigo-100 pb-2 mb-2">Kalkulasi Sistem</h4>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-slate-600">Tgl Jam Kejadian:</span>
                  <span class="font-medium text-slate-800">{{ computedTglJamKejadian || '-' }}</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-slate-600">Tgl Jam Penanganan:</span>
                  <span class="font-medium text-slate-800">{{ computedTglJamPenanganan || '-' }}</span>
                </div>
                <div class="flex justify-between items-center text-sm pt-2 border-t border-indigo-100">
                  <span class="text-slate-600">Downtime (jam):</span>
                  <span class="font-bold text-rose-600">{{ computedDowntimeJam }} Jam</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-slate-600">Konversi ke Menit:</span>
                  <span class="font-bold text-rose-600">{{ computedDowntimeMenit }} Menit</span>
                </div>
              </div>

            </div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-3">
        <BaseButton variant="secondary" @click="close">Keluar</BaseButton>
        <BaseButton variant="primary" @click="submitForm">Simpan Data</BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XIcon } from 'lucide-vue-next'
import BaseButton from '@/presentation/components/base/BaseButton.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  subsystemName: {
    type: String,
    default: 'Subsystem'
  }
})

const emit = defineEmits(['close', 'save'])

// Dictionary Sparepart beserta simulasi stok saat ini (Kategori)
const sparepartDictionary = {
  'Track Circuit': [
    { name: 'Relay Track', stock: 12 }, { name: 'Kabel Konektor', stock: 45 }, { name: 'Isolasi Rel', stock: 8 }, { name: 'Resistor', stock: 120 }, { name: 'Kapasitor', stock: 3 }
  ],
  'Axle Counter': [
    { name: 'Sensor Roda', stock: 5 }, { name: 'Modul Evaluator', stock: 2 }, { name: 'Kabel Transmisi', stock: 30 }, { name: 'Surge Protector', stock: 15 }
  ],
  'Interlocking Mekanik': [
    { name: 'Kawat Tarik', stock: 50 }, { name: 'Handle Mekanik', stock: 4 }, { name: 'Roda Gigi', stock: 8 }, { name: 'Rantai', stock: 12 }, { name: 'Tuas Sentral', stock: 2 }, { name: 'Bandul', stock: 6 }
  ],
  'Interlocking Elektrik': [
    { name: 'Modul CPU', stock: 1 }, { name: 'Relay 24V', stock: 25 }, { name: 'Power Supply', stock: 3 }, { name: 'Konektor I/O', stock: 40 }, { name: 'Fuse', stock: 100 }
  ],
  'Penggerak Wesel Mekanik': [
    { name: 'Kawat Tarik', stock: 50 }, { name: 'Roda Kawat', stock: 15 }, { name: 'Pena Wesel', stock: 7 }, { name: 'Pelumas', stock: 20 }
  ],
  'Penggerak Wesel Elektrik': [
    { name: 'Motor Point', stock: 2 }, { name: 'Kontak Wesel', stock: 10 }, { name: 'Kabel Motor', stock: 25 }, { name: 'Limit Switch', stock: 14 }
  ],
  'Peraga Sinyal Utama': [
    { name: 'Lampu LED Sinyal', stock: 35 }, { name: 'Lensa Sinyal', stock: 18 }, { name: 'Kabel Sinyal', stock: 60 }, { name: 'Tiang Sinyal', stock: 2 }
  ],
  'Peraga Sinyal Pembantu': [
    { name: 'Lampu LED', stock: 40 }, { name: 'Kabel', stock: 60 }, { name: 'Reflektor', stock: 22 }
  ],
  'Pengaman wesel setempat': [
    { name: 'Gembok Wesel', stock: 15 }, { name: 'Rantai', stock: 12 }, { name: 'Kunci Sentral', stock: 4 }
  ],
  'Kontak Deteksi': [
    { name: 'Limit Switch', stock: 14 }, { name: 'Kabel Kontak', stock: 25 }, { name: 'Tuas Kontak', stock: 8 }
  ]
}

const availableSpareparts = computed(() => {
  // Ambil sparepart berdasarkan subsystem.
  const key = Object.keys(sparepartDictionary).find(k => props.subsystemName.toLowerCase().includes(k.toLowerCase()))
  return key ? sparepartDictionary[key] : []
})

const getTodayDate = () => {
  const d = new Date()
  return d.toISOString().split('T')[0]
}

const form = ref({
  lokasi: '',
  resor: '',
  qc: '',
  failure_event: '',
  penyebab: '',
  tindakan: '',
  penggantian_sparepart: 'Tidak',
  tindak_vandalisme: 'Tidak',
  tanggal_kejadian: getTodayDate(),
  tanggal_penanganan: getTodayDate(),
  mulai: '00:00',
  selesai: '00:00',
  nama_sparepart: '',
  jumlah_sparepart: 1
})

const currentSparepartStock = computed(() => {
  if (!form.value.nama_sparepart) return null
  const parts = availableSpareparts.value
  const found = parts.find(p => p.name.toLowerCase() === form.value.nama_sparepart.toLowerCase())
  return found ? found.stock : null // return null jika custom input (tidak ada di list)
})

const stockWarningMessage = ref('')

const validateStockAmount = () => {
  stockWarningMessage.value = ''
  if (currentSparepartStock.value !== null) {
    if (form.value.jumlah_sparepart > currentSparepartStock.value) {
      form.value.jumlah_sparepart = currentSparepartStock.value
      stockWarningMessage.value = `Maksimal stok: ${currentSparepartStock.value}`
    } else if (form.value.jumlah_sparepart < 1 && form.value.jumlah_sparepart !== '') {
      form.value.jumlah_sparepart = 1
    }
  }
}

// Reset jumlah saat ganti sparepart
watch(() => form.value.nama_sparepart, () => {
  form.value.jumlah_sparepart = 1
  stockWarningMessage.value = ''
})

// Form sudah dipindah ke atas

// Reset form when opened
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    form.value = {
      lokasi: '',
      resor: '',
      qc: '',
      failure_event: '',
      penyebab: '',
      tindakan: '',
      penggantian_sparepart: 'Tidak',
      tindak_vandalisme: 'Tidak',
      tanggal_kejadian: getTodayDate(),
      tanggal_penanganan: getTodayDate(),
      mulai: '00:00',
      selesai: '00:00'
    }
  }
})

const computedTahun = computed(() => {
  if (form.value.tanggal_kejadian) {
    return form.value.tanggal_kejadian.split('-')[0]
  }
  return ''
})

const formatDateTime = (dateStr, timeStr) => {
  if (!dateStr || !timeStr) return ''
  return `${dateStr} ${timeStr}:00`
}

const computedTglJamKejadian = computed(() => formatDateTime(form.value.tanggal_kejadian, form.value.mulai))
const computedTglJamPenanganan = computed(() => formatDateTime(form.value.tanggal_penanganan, form.value.selesai))

const calculateDowntime = () => {
  if (!form.value.tanggal_kejadian || !form.value.mulai || !form.value.tanggal_penanganan || !form.value.selesai) return 0
  const start = new Date(`${form.value.tanggal_kejadian}T${form.value.mulai}`)
  const end = new Date(`${form.value.tanggal_penanganan}T${form.value.selesai}`)
  const diffMs = end - start
  if (diffMs < 0) return 0 // Invalid time travel
  return diffMs / (1000 * 60) // in minutes
}

const computedDowntimeMenit = computed(() => calculateDowntime())
const computedDowntimeJam = computed(() => (calculateDowntime() / 60).toFixed(2))

const close = () => {
  emit('close')
}

const submitForm = () => {
  if (!form.value.lokasi || !form.value.failure_event || !form.value.tanggal_kejadian) {
    alert("Mohon lengkapi field yang wajib (Lokasi, Failure Event, Tanggal)")
    return
  }

  // Construct final object
  const newLog = {
    ...form.value,
    tahun_kejadian: computedTahun.value,
    tanggal_jam_kejadian: computedTglJamKejadian.value,
    tanggal_jam_penanganan: computedTglJamPenanganan.value,
    downtime_jam: Number(computedDowntimeJam.value),
    downtime_menit: computedDowntimeMenit.value,
  }

  emit('save', newLog)
}
</script>
