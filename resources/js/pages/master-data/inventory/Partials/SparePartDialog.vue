<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Archive, X } from 'lucide-vue-next'
import CategorySelectFields from '@/pages/master-data/assets/Partials/CategorySelectFields.vue'

const props = defineProps({
  open: Boolean,
  part: { type: Object, default: null },
  categories: { type: Array, required: true },
})
const emit = defineEmits(['close', 'success'])

const value = (key, fallback = '') => props.part?.[key] ?? fallback
const form = useForm({
  asset_subsystem_id: value('asset_subsystem_id', null),
  code: value('code'),
  equipment: value('equipment'),
  detail_equipment: value('detail_equipment'),
  unit_of_measure: value('unit_of_measure'),
  severity: value('severity'),
  max_yearly_failure: value('max_yearly_failure'),
  average_yearly_failure: value('average_yearly_failure'),
  max_lead_time_months: value('max_lead_time_months'),
  average_lead_time_months: value('average_lead_time_months'),
  safety_stock: value('safety_stock'),
  lead_time_demand: value('lead_time_demand'),
  reorder_point: value('reorder_point'),
})
const deactivateForm = useForm({})
const confirmingDeactivate = ref(false)
const dialogPanel = ref(null)
const confirmationPanel = ref(null)
const deactivateButton = ref(null)

const close = () => {
  if (!form.processing && !deactivateForm.processing) emit('close')
}
const success = () => { emit('success'); emit('close') }
const submit = () => {
  const options = { preserveScroll: true, onSuccess: success }
  if (props.part) form.put(`/admin/spare-parts/${props.part.id}`, options)
  else form.post('/admin/spare-parts', options)
}
const deactivate = () => deactivateForm.delete(`/admin/spare-parts/${props.part.id}`, {
  preserveScroll: true,
  onSuccess: success,
})
const dismissDeactivateConfirmation = () => {
  confirmingDeactivate.value = false
  nextTick(() => deactivateButton.value?.focus())
}
const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
const activePanel = () => confirmationPanel.value ?? dialogPanel.value
const focusables = () => [...(activePanel()?.querySelectorAll(focusableSelector) ?? [])].filter((element) => element.tabIndex >= 0)
const focusFirst = () => (focusables()[0] ?? activePanel())?.focus()
const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    if (confirmingDeactivate.value) dismissDeactivateConfirmation()
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
watch(confirmingDeactivate, (open) => { if (open) nextTick(focusFirst) })
onMounted(() => { document.addEventListener('keydown', handleKeydown, true); nextTick(focusFirst) })
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown, true))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 backdrop-blur-[1px] sm:items-center sm:p-4" @click.self="close">
      <section ref="dialogPanel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="part-dialog-title" class="max-h-[96vh] w-full overflow-y-auto rounded-t-2xl bg-white outline-none shadow-2xl sm:max-w-5xl sm:rounded-2xl">
        <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
          <div><p class="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[#2d2a70]">Master / suku cadang</p><h2 id="part-dialog-title" class="mt-1 text-lg font-semibold text-slate-950">{{ part ? 'Ubah suku cadang' : 'Tambah suku cadang' }}</h2><p class="mt-1 text-xs text-slate-500">Data ini berlaku lintas unit. Nilai opsional dapat dilengkapi bertahap.</p></div>
          <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-slate-100 focus:ring-2 focus:ring-[#2d2a70]" aria-label="Tutup dialog suku cadang" @click="close"><X :size="20" aria-hidden="true" /></button>
        </header>

        <form class="space-y-6 p-5 sm:p-6" @submit.prevent="submit">
          <section aria-labelledby="identity-title" class="rounded-xl border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3"><h3 id="identity-title" class="text-sm font-semibold text-slate-950">Identitas</h3><p class="mt-0.5 text-xs text-slate-500">Kode, nama, kategori hierarkis, dan satuan pencatatan.</p></div>
            <div class="space-y-4 p-4">
              <CategorySelectFields v-model="form.asset_subsystem_id" :categories="categories" :errors="form.errors" />
              <div class="grid gap-4 md:grid-cols-2">
                <div><label for="part-code" class="mb-1.5 block text-sm font-medium text-slate-800">Kode suku cadang <span class="text-red-600">*</span></label><input id="part-code" v-model="form.code" maxlength="50" :class="inputClass" required /><p v-if="form.errors.code" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.code }}</p></div>
                <div><label for="part-unit" class="mb-1.5 block text-sm font-medium text-slate-800">Satuan pencatatan <span class="text-red-600">*</span></label><input id="part-unit" v-model="form.unit_of_measure" maxlength="30" :class="inputClass" placeholder="buah" required /><p class="mt-1 text-xs text-slate-500">Gunakan satuan operasional; contoh: buah, set, meter.</p><p v-if="form.errors.unit_of_measure" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.unit_of_measure }}</p></div>
                <div><label for="part-equipment" class="mb-1.5 block text-sm font-medium text-slate-800">Peralatan <span class="font-normal text-slate-400">(opsional)</span></label><input id="part-equipment" v-model="form.equipment" maxlength="255" :class="inputClass" /><p v-if="form.errors.equipment" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.equipment }}</p></div>
                <div><label for="part-name" class="mb-1.5 block text-sm font-medium text-slate-800">Nama / detail peralatan <span class="text-red-600">*</span></label><input id="part-name" v-model="form.detail_equipment" maxlength="255" :class="inputClass" required /><p v-if="form.errors.detail_equipment" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.detail_equipment }}</p></div>
                <div><label for="part-severity" class="mb-1.5 block text-sm font-medium text-slate-800">Severity <span class="font-normal text-slate-400">(opsional)</span></label><input id="part-severity" v-model="form.severity" maxlength="100" :class="inputClass" /><p v-if="form.errors.severity" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.severity }}</p></div>
              </div>
            </div>
          </section>

          <div class="grid gap-5 lg:grid-cols-3">
            <section aria-labelledby="failure-title" class="rounded-xl border border-slate-200 p-4"><h3 id="failure-title" class="text-sm font-semibold text-slate-950">Kegagalan</h3><p class="mt-1 text-xs text-slate-500">Frekuensi per tahun, maksimal dua desimal.</p><div class="mt-4 space-y-4"><div><label for="part-failure-max" class="mb-1.5 block text-xs font-medium text-slate-700">Maksimum <span class="text-slate-400">(opsional)</span></label><input id="part-failure-max" v-model="form.max_yearly_failure" type="number" min="0" step="0.01" :class="inputClass" /><p v-if="form.errors.max_yearly_failure" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.max_yearly_failure }}</p></div><div><label for="part-failure-average" class="mb-1.5 block text-xs font-medium text-slate-700">Rata-rata <span class="text-slate-400">(opsional)</span></label><input id="part-failure-average" v-model="form.average_yearly_failure" type="number" min="0" step="0.01" :class="inputClass" /><p v-if="form.errors.average_yearly_failure" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.average_yearly_failure }}</p></div></div></section>
            <section aria-labelledby="lead-title" class="rounded-xl border border-slate-200 p-4"><h3 id="lead-title" class="text-sm font-semibold text-slate-950">Lead time</h3><p class="mt-1 text-xs text-slate-500">Durasi pengadaan dalam bulan.</p><div class="mt-4 space-y-4"><div><label for="part-lead-max" class="mb-1.5 block text-xs font-medium text-slate-700">Maksimum <span class="text-slate-400">(opsional)</span></label><input id="part-lead-max" v-model="form.max_lead_time_months" type="number" min="0" step="0.01" :class="inputClass" /><p v-if="form.errors.max_lead_time_months" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.max_lead_time_months }}</p></div><div><label for="part-lead-average" class="mb-1.5 block text-xs font-medium text-slate-700">Rata-rata <span class="text-slate-400">(opsional)</span></label><input id="part-lead-average" v-model="form.average_lead_time_months" type="number" min="0" step="0.01" :class="inputClass" /><p v-if="form.errors.average_lead_time_months" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.average_lead_time_months }}</p></div></div></section>
            <section aria-labelledby="reorder-title" class="rounded-xl border border-slate-200 p-4"><h3 id="reorder-title" class="text-sm font-semibold text-slate-950">Reorder</h3><p class="mt-1 text-xs text-slate-500">Ambang kendali stok dalam satuan barang.</p><div class="mt-4 space-y-4"><div><label for="part-safety" class="mb-1.5 block text-xs font-medium text-slate-700">Safety stock <span class="text-slate-400">(opsional)</span></label><input id="part-safety" v-model="form.safety_stock" type="number" min="0" step="1" :class="inputClass" /><p v-if="form.errors.safety_stock" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.safety_stock }}</p></div><div><label for="part-demand" class="mb-1.5 block text-xs font-medium text-slate-700">Kebutuhan lead time <span class="text-slate-400">(opsional)</span></label><input id="part-demand" v-model="form.lead_time_demand" type="number" min="0" step="1" :class="inputClass" /><p v-if="form.errors.lead_time_demand" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.lead_time_demand }}</p></div><div><label for="part-reorder" class="mb-1.5 block text-xs font-medium text-slate-700">Reorder point <span class="text-slate-400">(opsional)</span></label><input id="part-reorder" v-model="form.reorder_point" type="number" min="0" step="1" :class="inputClass" /><p v-if="form.errors.reorder_point" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.reorder_point }}</p></div></div></section>
          </div>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <button v-if="part?.is_active" ref="deactivateButton" data-deactivate-part type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold text-red-700 outline-none hover:bg-red-50 focus:ring-2 focus:ring-red-600" @click="confirmingDeactivate = true"><Archive :size="17" aria-hidden="true" /> Nonaktifkan</button><span v-else />
            <div class="flex flex-col-reverse gap-3 sm:flex-row"><button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="close">Batal</button><button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-[#f26522] px-5 text-sm font-semibold text-white outline-none hover:bg-[#d95418] focus:ring-2 focus:ring-[#f26522] focus:ring-offset-2 disabled:opacity-50">{{ form.processing ? 'Menyimpan…' : 'Simpan suku cadang' }}</button></div>
          </div>
        </form>
      </section>

      <div v-if="confirmingDeactivate" data-deactivate-confirmation class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4">
        <section ref="confirmationPanel" tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="deactivate-title" class="w-full max-w-md rounded-2xl bg-white p-6 outline-none shadow-2xl"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-700"><Archive :size="21" aria-hidden="true" /></span><h3 id="deactivate-title" class="mt-4 text-lg font-semibold text-slate-950">Nonaktifkan suku cadang?</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ part.detail_equipment }} tidak dapat dipilih pada transaksi baru. Riwayat dan stok yang sudah tercatat tetap utuh.</p><div class="mt-5 flex justify-end gap-3"><button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="dismissDeactivateConfirmation">Batal</button><button data-confirm-deactivate type="button" class="min-h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white" @click="deactivate">Ya, nonaktifkan</button></div></section>
      </div>
    </div>
  </Teleport>
</template>
