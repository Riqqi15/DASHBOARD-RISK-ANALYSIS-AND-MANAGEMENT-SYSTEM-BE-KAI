<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { AlertTriangle, ArrowRight, X } from 'lucide-vue-next'

const props = defineProps({
  open: Boolean,
  spareParts: { type: Array, default: () => [] },
  stocks: { type: Array, default: () => [] },
  units: { type: Array, default: () => [] },
  canChooseUnit: Boolean,
  initialPart: { type: Object, default: null },
  initialStock: { type: Object, default: null },
  correction: { type: Object, default: null },
})

const emit = defineEmits(['close', 'success'])
const isCorrection = computed(() => Boolean(props.correction))
const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' })
const form = useForm(props.correction ? {
  direction: props.correction.direction === 'out' ? 'in' : 'out',
  quantity: props.correction.quantity,
  movement_date: today,
  notes: '',
  idempotency_key: '',
} : {
  unit_kerja_id: '',
  spare_part_id: '',
  type: 'in',
  direction: 'in',
  quantity: 1,
  movement_date: today,
  reference_number: '',
  notes: '',
  idempotency_key: '',
})

const localError = ref('')
const confirmingOut = ref(false)
const closeButton = ref(null)
const dialogPanel = ref(null)
const confirmationPanel = ref(null)

const selectedPart = computed(() => isCorrection.value
  ? props.correction.spare_part
  : props.spareParts.find((part) => String(part.id) === String(form.spare_part_id)))
const selectedUnitId = computed(() => isCorrection.value
  ? props.correction.unit.id
  : props.canChooseUnit ? form.unit_kerja_id : (props.initialStock?.unit_kerja_id ?? props.stocks.find((row) => String(row.spare_part_id) === String(form.spare_part_id))?.unit_kerja_id))
const matchingStock = computed(() => props.stocks.find((row) =>
  String(row.spare_part_id) === String(isCorrection.value ? props.correction.spare_part_id : form.spare_part_id)
  && String(row.unit_kerja_id) === String(selectedUnitId.value)))
const balanceKnown = computed(() => isCorrection.value || Boolean(matchingStock.value))
const stockBefore = computed(() => Number(isCorrection.value
  ? props.correction.current_stock
  : (matchingStock.value?.quantity ?? 0)))
const quantity = computed(() => Number(form.quantity || 0))
const direction = computed(() => isCorrection.value ? form.direction : (form.type === 'out' ? 'out' : 'in'))
const projectedStock = computed(() => stockBefore.value + (direction.value === 'out' ? -quantity.value : quantity.value))
const unitLabel = computed(() => selectedPart.value?.unit_of_measure ?? 'unit')
const canUseOut = computed(() => balanceKnown.value && stockBefore.value > 0)
const canUseOpening = computed(() => balanceKnown.value && stockBefore.value === 0)

const resetValues = () => {
  localError.value = ''
  confirmingOut.value = false
  form.clearErrors?.()
  form.idempotency_key = crypto.randomUUID()
  form.movement_date = today
  form.notes = ''
  form.quantity = props.correction?.quantity ?? 1

  if (isCorrection.value) {
    form.direction = props.correction.direction === 'out' ? 'in' : 'out'
    return
  }

  form.unit_kerja_id = props.canChooseUnit && props.initialStock?.unit_kerja_id ? String(props.initialStock.unit_kerja_id) : ''
  form.spare_part_id = props.initialPart?.id ? String(props.initialPart.id) : (props.initialStock?.spare_part_id ? String(props.initialStock.spare_part_id) : '')
  form.type = 'in'
  form.direction = 'in'
  form.reference_number = ''
}

watch(() => props.open, (open, previous) => {
  if (open && !previous) {
    resetValues()
    nextTick(() => closeButton.value?.focus())
  }
}, { immediate: true })

watch(() => form.type, (type) => {
  if (!isCorrection.value) form.direction = type === 'out' ? 'out' : 'in'
  localError.value = ''
  confirmingOut.value = false
})
watch([() => form.quantity, () => form.spare_part_id, () => form.unit_kerja_id, () => form.direction], () => {
  localError.value = ''
  confirmingOut.value = false
})
watch(canUseOpening, (allowed) => {
  if (!allowed && !isCorrection.value && form.type === 'opening') form.type = 'in'
})
watch(canUseOut, (allowed) => {
  if (!allowed && !isCorrection.value && form.type === 'out') form.type = 'in'
})

const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
const activePanel = () => confirmationPanel.value ?? dialogPanel.value
const focusables = () => [...(activePanel()?.querySelectorAll(focusableSelector) ?? [])].filter((element) => element.tabIndex >= 0)
const focusFirst = () => (focusables()[0] ?? activePanel())?.focus()
const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    if (confirmingOut.value) dismissOutConfirmation()
    else close()
    return
  }
  if (event.key !== 'Tab') return
  const available = focusables()
  if (!available.length) { event.preventDefault(); activePanel()?.focus(); return }
  const first = available[0]
  const last = available[available.length - 1]
  if (!activePanel()?.contains(document.activeElement)) { event.preventDefault(); first.focus() }
  else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
watch(confirmingOut, (open) => { if (open) nextTick(focusFirst) })
onMounted(() => document.addEventListener('keydown', handleKeydown, true))
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown, true))

const close = () => {
  if (!form.processing) emit('close')
}
const dismissOutConfirmation = () => {
  confirmingOut.value = false
  nextTick(() => closeButton.value?.focus())
}

const postMovement = () => {
  const url = isCorrection.value
    ? `/inventory/movements/${props.correction.id}/corrections`
    : '/inventory/movements'
  form.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      emit('success')
      emit('close')
    },
  })
}

const submit = () => {
  localError.value = ''
  if (direction.value === 'out' && projectedStock.value < 0) {
    localError.value = `Stok tidak mencukupi. Maksimal ${stockBefore.value} ${unitLabel.value} dapat dikeluarkan.`
    return
  }
  if (direction.value === 'out') {
    confirmingOut.value = true
    return
  }
  postMovement()
}

const confirmOut = () => {
  confirmingOut.value = false
  postMovement()
}

const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10 disabled:bg-slate-100'
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 p-0 backdrop-blur-[1px] sm:items-center sm:p-4" @click.self="close">
      <section ref="dialogPanel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="movement-dialog-title" class="max-h-[94vh] w-full overflow-y-auto rounded-t-2xl bg-white outline-none shadow-2xl sm:max-w-3xl sm:rounded-2xl">
        <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
          <div>
            <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[#2d2a70]">{{ isCorrection ? `Ledger / sumber #${correction.id}` : 'Ledger / transaksi baru' }}</p>
            <h2 id="movement-dialog-title" class="mt-1 text-lg font-semibold text-slate-950">{{ isCorrection ? `Koreksi transaksi #${correction.id}` : 'Catat transaksi stok' }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ isCorrection ? 'Koreksi dicatat sebagai transaksi baru yang tertaut; transaksi sumber tidak diubah.' : 'Pastikan unit, barang, jumlah, dan tanggal operasional sudah tepat.' }}</p>
          </div>
          <button ref="closeButton" type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-slate-100 focus:ring-2 focus:ring-[#2d2a70]" aria-label="Tutup dialog transaksi" @click="close"><X :size="20" aria-hidden="true" /></button>
        </header>

        <form class="space-y-5 p-5 sm:p-6" @submit.prevent="submit">
          <div v-if="isCorrection" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900"><span class="font-mono text-xs text-[#2d2a70]">{{ correction.spare_part.code }}</span><span>{{ correction.spare_part.detail_equipment }}</span></div>
            <p class="mt-2 text-xs text-slate-600">{{ correction.unit.code }} · transaksi {{ correction.direction === 'out' ? 'keluar' : 'masuk' }} {{ correction.quantity }} {{ correction.spare_part.unit_of_measure }} pada {{ correction.movement_date }}</p>
          </div>

          <div v-else class="grid gap-4 sm:grid-cols-2">
            <div v-if="canChooseUnit">
              <label for="movement-unit" class="mb-1.5 block text-sm font-medium text-slate-800">Unit kerja <span class="text-red-600">*</span></label>
              <select id="movement-unit" v-model="form.unit_kerja_id" :class="inputClass" required>
                <option value="">Pilih unit kerja</option><option v-for="unit in units" :key="unit.id" :value="String(unit.id)">{{ unit.code }} — {{ unit.name }}</option>
              </select>
              <p v-if="form.errors.unit_kerja_id" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.unit_kerja_id }}</p>
            </div>
            <div :class="canChooseUnit ? '' : 'sm:col-span-2'">
              <label for="movement-part" class="mb-1.5 block text-sm font-medium text-slate-800">Suku cadang <span class="text-red-600">*</span></label>
              <select id="movement-part" v-model="form.spare_part_id" :class="inputClass" required>
                <option value="">Pilih suku cadang</option><option v-for="part in spareParts.filter((item) => item.is_active !== false)" :key="part.id" :value="String(part.id)">{{ part.code }} — {{ part.detail_equipment }}</option>
              </select>
              <p v-if="form.errors.spare_part_id" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.spare_part_id }}</p>
            </div>
            <div>
              <label for="movement-type" class="mb-1.5 block text-sm font-medium text-slate-800">Jenis transaksi <span class="text-red-600">*</span></label>
              <select id="movement-type" v-model="form.type" :class="inputClass" required>
                <option value="in">Masuk</option><option v-if="canUseOut" value="out">Keluar</option><option v-if="canUseOpening" value="opening">Saldo awal</option>
              </select>
            </div>
            <div>
              <label for="movement-reference" class="mb-1.5 block text-sm font-medium text-slate-800">Nomor referensi <span class="font-normal text-slate-400">(opsional)</span></label>
              <input id="movement-reference" v-model="form.reference_number" maxlength="100" :class="inputClass" placeholder="BAST, tiket, atau dokumen" />
              <p v-if="form.errors.reference_number" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.reference_number }}</p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div v-if="isCorrection">
              <label for="movement-direction" class="mb-1.5 block text-sm font-medium text-slate-800">Arah koreksi <span class="text-red-600">*</span></label>
              <select id="movement-direction" v-model="form.direction" :class="inputClass" required><option value="in">Tambah stok</option><option value="out">Kurangi stok</option></select>
              <p v-if="form.errors.direction" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.direction }}</p>
            </div>
            <div>
              <label for="movement-quantity" class="mb-1.5 block text-sm font-medium text-slate-800">Jumlah <span class="text-red-600">*</span></label>
              <div class="relative"><input id="movement-quantity" v-model="form.quantity" type="number" min="1" step="1" :class="[inputClass, 'pr-20 font-mono tabular-nums']" required /><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500">{{ unitLabel }}</span></div>
              <p v-if="form.errors.quantity" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.quantity }}</p>
            </div>
            <div>
              <label for="movement-date" class="mb-1.5 block text-sm font-medium text-slate-800">Tanggal operasional <span class="text-red-600">*</span></label>
              <input id="movement-date" v-model="form.movement_date" type="date" :max="today" :class="inputClass" required />
              <p v-if="form.errors.movement_date" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.movement_date }}</p>
            </div>
          </div>

          <div>
            <label for="movement-notes" class="mb-1.5 block text-sm font-medium text-slate-800">Catatan <span class="font-normal text-slate-400">(opsional)</span></label>
            <textarea id="movement-notes" v-model="form.notes" rows="3" maxlength="1000" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-950 outline-none focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10" placeholder="Kondisi barang atau alasan koreksi" />
            <p v-if="form.errors.notes" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.notes }}</p>
          </div>

          <section class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4" aria-label="Dampak transaksi">
            <p v-if="balanceKnown" class="sr-only">Stok setelah transaksi: {{ projectedStock }} {{ unitLabel }}</p>
            <p v-else class="sr-only">Saldo belum terverifikasi pada halaman ini</p>
            <div data-stock-before><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Sebelum</p><p class="mt-1 font-mono text-xl font-semibold tabular-nums text-slate-900">{{ balanceKnown ? stockBefore : '—' }} <span v-if="balanceKnown" class="text-xs font-normal text-slate-500">{{ unitLabel }}</span></p></div>
            <ArrowRight :size="18" class="text-slate-400" aria-hidden="true" />
            <div class="text-right"><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Setelah transaksi</p><p class="mt-1 font-mono text-xl font-semibold tabular-nums" :class="projectedStock < 0 ? 'text-red-700' : 'text-slate-900'">{{ balanceKnown ? projectedStock : '—' }} <span v-if="balanceKnown" class="text-xs font-normal text-slate-500">{{ unitLabel }}</span></p></div>
          </section>
          <p v-if="!balanceKnown && selectedPart" class="text-xs text-slate-500">Saldo belum terverifikasi pada halaman ini. Untuk stok keluar atau saldo awal, mulai dari aksi pada baris stok.</p>

          <p v-if="localError" data-stock-error class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700" role="alert">{{ localError }}</p>
          <p v-if="form.errors.idempotency_key" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ form.errors.idempotency_key }}</p>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
            <button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 outline-none hover:bg-slate-50 focus:ring-2 focus:ring-[#2d2a70]" @click="close">Batal</button>
            <button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-[#f26522] px-5 text-sm font-semibold text-white outline-none hover:bg-[#d95418] focus:ring-2 focus:ring-[#f26522] focus:ring-offset-2 disabled:opacity-50">{{ form.processing ? 'Menyimpan…' : isCorrection ? 'Catat koreksi' : 'Catat transaksi' }}</button>
          </div>
        </form>
      </section>

      <div v-if="confirmingOut" data-out-confirmation class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4">
        <section ref="confirmationPanel" tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="out-confirm-title" class="w-full max-w-md rounded-2xl bg-white p-6 outline-none shadow-2xl">
          <span class="flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-700"><AlertTriangle :size="21" aria-hidden="true" /></span>
          <h3 id="out-confirm-title" class="mt-4 text-lg font-semibold text-slate-950">Konfirmasi stok keluar</h3>
          <p class="mt-2 text-sm leading-6 text-slate-600">Keluarkan <strong>{{ quantity }} {{ unitLabel }}</strong> {{ selectedPart?.detail_equipment }}? Stok setelah transaksi menjadi <strong>{{ projectedStock }} {{ unitLabel }}</strong>.</p>
          <div class="mt-5 flex justify-end gap-3"><button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="dismissOutConfirmation">Periksa lagi</button><button data-confirm-out type="button" class="min-h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white outline-none hover:bg-red-700 focus:ring-2 focus:ring-red-600 focus:ring-offset-2" @click="confirmOut">Ya, catat keluar</button></div>
        </section>
      </div>
    </div>
  </Teleport>
</template>
