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
        <button type="button" @click="close" class="text-slate-400 hover:text-slate-600 transition-colors p-2 rounded-full hover:bg-slate-100">
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
                <label for="trouble-report-lokasi" class="block text-xs font-semibold text-slate-700 mb-1">Lokasi</label>
                <input id="trouble-report-lokasi" v-model="form.lokasi" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" required />
              </div>
              <div>
                <label for="trouble-report-resor" class="block text-xs font-semibold text-slate-700 mb-1">Resor</label>
                <input id="trouble-report-resor" v-model="form.resor" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" required />
              </div>
              <div>
                <label for="trouble-report-qc" class="block text-xs font-semibold text-slate-700 mb-1">QC</label>
                <input id="trouble-report-qc" v-model="form.qc" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none" />
              </div>
              <div>
                <label for="trouble-report-failure-event" class="block text-xs font-semibold text-slate-700 mb-1">Failure Event</label>
                <textarea id="trouble-report-failure-event" v-model="form.failure_event" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none resize-none" required></textarea>
              </div>
              <div>
                <label for="trouble-report-penyebab" class="block text-xs font-semibold text-slate-700 mb-1">Penyebab</label>
                <textarea id="trouble-report-penyebab" v-model="form.penyebab" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none resize-none" required></textarea>
              </div>
              <div>
                <label for="trouble-report-tindakan" class="block text-xs font-semibold text-slate-700 mb-1">Tindakan</label>
                <textarea id="trouble-report-tindakan" v-model="form.tindakan" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none resize-none" required></textarea>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label for="trouble-report-penggantian-sparepart" class="block text-xs font-semibold text-slate-700 mb-1">Penggantian Sparepart</label>
                  <select id="trouble-report-penggantian-sparepart" v-model="form.penggantian_sparepart" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none">
                    <option value="Tidak">Tidak</option>
                    <option value="Ya">Ya</option>
                  </select>
                </div>
                <div>
                  <label for="trouble-report-tindak-vandalisme" class="block text-xs font-semibold text-slate-700 mb-1">Tindak Vandalisme</label>
                  <select id="trouble-report-tindak-vandalisme" v-model="form.tindak_vandalisme" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow outline-none">
                    <option value="Tidak">Tidak</option>
                    <option value="Ya">Ya</option>
                  </select>
                </div>
              </div>

              <!-- Extra Fields for Sparepart -->
              <div v-if="form.penggantian_sparepart === 'Ya'" class="grid grid-cols-3 gap-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="col-span-2">
                  <label for="trouble-report-spare-part" class="block text-xs font-semibold text-amber-900 mb-1">Nama Sparepart Diganti</label>
                  <select
                    id="trouble-report-spare-part"
                    v-model.number="form.spare_part_id"
                    class="w-full px-3 py-2 border border-amber-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none" 
                    required 
                  >
                    <option :value="null" disabled>Pilih sparepart</option>
                    <option v-for="sp in availableSpareparts" :key="sp.spare_part_id" :value="sp.spare_part_id">
                      {{ sp.code }} - {{ sp.name }} (stok {{ sp.quantity }})
                    </option>
                  </select>
                  <p v-if="form.spare_part_id && currentSparepartStock !== null" class="mt-1 text-[10px] font-semibold" :class="currentSparepartStock < 5 ? 'text-rose-600' : 'text-emerald-600'">
                    Stock Tersedia: {{ currentSparepartStock }} unit
                  </p>
                </div>
                <div>
                  <label for="trouble-report-jumlah-sparepart" class="block text-xs font-semibold text-amber-900 mb-1">Jumlah</label>
                  <input 
                    id="trouble-report-jumlah-sparepart"
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
                  <label for="trouble-report-tahun-kejadian" class="block text-xs font-semibold text-slate-700 mb-1">Tahun Kejadian</label>
                  <input id="trouble-report-tahun-kejadian" :value="computedTahun" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-100 text-slate-500 cursor-not-allowed" readonly />
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="trouble-report-tanggal-kejadian" class="block text-xs font-semibold text-slate-700 mb-1">Tgl Kejadian</label>
                    <input id="trouble-report-tanggal-kejadian" v-model="form.tanggal_kejadian" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                  <div>
                    <label for="trouble-report-tanggal-penanganan" class="block text-xs font-semibold text-slate-700 mb-1">Tgl Penanganan</label>
                    <input id="trouble-report-tanggal-penanganan" v-model="form.tanggal_penanganan" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label for="trouble-report-mulai" class="block text-xs font-semibold text-slate-700 mb-1">Mulai (Jam)</label>
                    <input id="trouble-report-mulai" v-model="form.mulai" type="time" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
                  </div>
                  <div>
                    <label for="trouble-report-selesai" class="block text-xs font-semibold text-slate-700 mb-1">Selesai (Jam)</label>
                    <input id="trouble-report-selesai" v-model="form.selesai" type="time" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" required />
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
                  <span class="text-slate-600">Downtime (hh:mm):</span>
                  <span class="font-bold text-rose-600">{{ computedDowntimeJam }}</span>
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
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  subsystemName: {
    type: String,
    default: 'Subsystem'
  },
  spareParts: {
    type: Array,
    default: () => []
  },
  log: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'save'])
const availableSpareparts = computed(() => props.spareParts.filter(part => Number(part.quantity) > 0))

const getTodayDate = () => {
  const d = new Date()
  return d.toISOString().split('T')[0]
}

const emptyForm = () => ({
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
  spare_part_id: null,
  jumlah_sparepart: 1
})
const form = ref(emptyForm())

const isYes = (value) => value === 'Y' || value === 'Ya'
const datePart = (date, dateTime) => date || dateTime?.split(' ')[0] || getTodayDate()
const timePart = (time, dateTime) => time || dateTime?.split(' ')[1]?.substring(0, 5) || '00:00'
const formFromLog = (log) => ({
  lokasi: log.lokasi || '',
  resor: log.resor || '',
  qc: log.qc || '',
  failure_event: log.failure_event || '',
  penyebab: log.penyebab || '',
  tindakan: log.tindakan || '',
  penggantian_sparepart: isYes(log.penggantian_sparepart) ? 'Ya' : 'Tidak',
  tindak_vandalisme: isYes(log.tindak_vandalisme) ? 'Ya' : 'Tidak',
  tanggal_kejadian: datePart(log.tanggal_kejadian, log.tanggal_jam_kejadian),
  tanggal_penanganan: datePart(log.tanggal_penanganan, log.tanggal_jam_penanganan),
  mulai: timePart(log.mulai, log.tanggal_jam_kejadian),
  selesai: timePart(log.selesai, log.tanggal_jam_penanganan),
  spare_part_id: log.spare_part_id || null,
  jumlah_sparepart: log.jumlah_sparepart || log.spare_part_quantity || 1
})

const currentSparepartStock = computed(() => {
  if (!form.value.spare_part_id) return null
  const found = availableSpareparts.value.find(part => Number(part.spare_part_id) === Number(form.value.spare_part_id))
  return found ? Number(found.quantity) : null
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
watch(() => form.value.spare_part_id, () => {
  form.value.jumlah_sparepart = 1
  stockWarningMessage.value = ''
})

// Form sudah dipindah ke atas

// Reset form when opened
watch(() => props.isOpen, (newVal) => {
  if (!newVal) return
  form.value = props.log ? formFromLog(props.log) : emptyForm()
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
  return diffMs / (1000 * 60) // in minutes
}

const computedDowntimeMenit = computed(() => calculateDowntime())
const computedDowntimeJam = computed(() => {
  const minutes = calculateDowntime()
  if (minutes <= 0) return '0:00'
  const h = Math.floor(minutes / 60)
  const m = Math.floor(minutes % 60)
  return `${h}:${m.toString().padStart(2, '0')}`
})

const close = () => {
  emit('close')
}

const submitForm = () => {
  if (!form.value.lokasi || !form.value.failure_event || !form.value.penyebab || !form.value.tindakan) {
    alert('Mohon lengkapi semua field wajib.')
    return
  }

  if (form.value.penggantian_sparepart === 'Ya' && !form.value.spare_part_id) {
    alert('Pilih suku cadang yang diganti.')
    return
  }

  if (computedDowntimeMenit.value < 0) {
    alert('Waktu penanganan tidak boleh lebih awal dari waktu kejadian.')
    return
  }

  emit('save', {
    location: form.value.lokasi,
    resort: form.value.resor || null,
    qc: form.value.qc || null,
    failure_event: form.value.failure_event,
    cause: form.value.penyebab,
    action_taken: form.value.tindakan,
    spare_part_replaced: form.value.penggantian_sparepart === 'Ya',
    spare_part_id: form.value.penggantian_sparepart === 'Ya' ? form.value.spare_part_id : null,
    spare_part_quantity: form.value.penggantian_sparepart === 'Ya' ? Number(form.value.jumlah_sparepart) : null,
    vandalism: form.value.tindak_vandalisme === 'Ya',
    started_at: computedTglJamKejadian.value,
    resolved_at: computedTglJamPenanganan.value,
  })
}
</script>
