<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
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
})
const deactivateForm = useForm({})
const confirmingDeactivate = ref(false)
const confirmingDiscard = ref(false)
const dialogPanel = ref(null)
const confirmationPanel = ref(null)
const discardPanel = ref(null)
const deactivateButton = ref(null)
const trackedFields = [
  'asset_subsystem_id', 'code', 'equipment', 'detail_equipment', 'unit_of_measure', 'severity',
  'max_yearly_failure', 'average_yearly_failure', 'max_lead_time_months', 'average_lead_time_months',
]
const snapshot = () => JSON.stringify(Object.fromEntries(trackedFields.map((field) => [field, form[field] ?? ''])))
const initialSnapshot = snapshot()
const isDirty = computed(() => snapshot() !== initialSnapshot)
const numberInput = (field) => {
  const input = form[field]
  if (input === null || input === undefined || String(input).trim() === '') return null
  const parsed = Number(input)
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : null
}
const averageFromMaximum = (input) => {
  if (input === null || input === undefined || String(input).trim() === '') return ''
  const parsed = Number(input)
  return Number.isFinite(parsed) && parsed >= 0 ? (parsed / 2).toFixed(2) : ''
}
const reorderCalculation = computed(() => {
  const maxFailure = numberInput('max_yearly_failure')
  const averageFailure = numberInput('average_yearly_failure')
  const maxLead = numberInput('max_lead_time_months')
  const averageLead = numberInput('average_lead_time_months')
  if ([maxFailure, averageFailure, maxLead, averageLead].some((value) => value === null)) return null
  const rawSafetyStock = Math.max(0, (maxFailure * maxLead) - (averageFailure * averageLead))
  const rawLeadTimeDemand = averageFailure * averageLead
  const safetyStock = Math.ceil(rawSafetyStock)
  const leadTimeDemand = Math.ceil(rawLeadTimeDemand)
  return {
    safety_stock: safetyStock,
    lead_time_demand: leadTimeDemand,
    reorder_point: Math.ceil(rawSafetyStock + rawLeadTimeDemand),
  }
})
const calculatedValue = (field) => reorderCalculation.value?.[field] ?? '-'

const close = () => {
  if (form.processing || deactivateForm.processing) return
  if (isDirty.value) {
    confirmingDiscard.value = true
    return
  }
  emit('close')
}
const success = () => { emit('success'); emit('close') }
const submit = () => {
  const options = { preserveScroll: true, onSuccess: success, onError: () => nextTick(focusFirstError) }
  if (props.part) form.put(`/admin/spare-parts/${props.part.id}`, options)
  else form.post('/admin/spare-parts', options)
}
const deactivate = () => {
  if (deactivateForm.processing) return
  deactivateForm.delete(`/admin/spare-parts/${props.part.id}`, {
    preserveScroll: true,
    onSuccess: success,
  })
}
const dismissDeactivateConfirmation = () => {
  confirmingDeactivate.value = false
  nextTick(() => deactivateButton.value?.focus())
}
const dismissDiscardConfirmation = () => {
  confirmingDiscard.value = false
  nextTick(focusFirst)
}
const confirmDiscard = () => emit('close')
const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
const activePanel = () => discardPanel.value ?? confirmationPanel.value ?? dialogPanel.value
const focusables = () => [...(activePanel()?.querySelectorAll(focusableSelector) ?? [])].filter((element) => element.tabIndex >= 0)
const focusFirst = () => (focusables()[0] ?? activePanel())?.focus()
const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    if (confirmingDiscard.value) dismissDiscardConfirmation()
    else if (confirmingDeactivate.value) dismissDeactivateConfirmation()
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
const protectsDirtyDraft = () => props.open && isDirty.value && !form.processing && !deactivateForm.processing
const handleBeforeUnload = (event) => {
  if (!protectsDirtyDraft()) return
  event.preventDefault()
  event.returnValue = ''
}
const handleBeforeVisit = (event) => {
  if (!protectsDirtyDraft() || String(event.detail?.visit?.method ?? '').toLowerCase() !== 'get') return
  if (!window.confirm('Perubahan suku cadang belum disimpan. Tinggalkan halaman?')) event.preventDefault()
}
let unregisterBeforeVisit
watch(confirmingDeactivate, (open) => { if (open) nextTick(focusFirst) })
watch(confirmingDiscard, (open) => { if (open) nextTick(focusFirst) })
watch(() => form.max_yearly_failure, (value) => { form.average_yearly_failure = averageFromMaximum(value) }, { immediate: true })
watch(() => form.max_lead_time_months, (value) => { form.average_lead_time_months = averageFromMaximum(value) }, { immediate: true })
onMounted(() => {
  document.addEventListener('keydown', handleKeydown, true)
  window.addEventListener('beforeunload', handleBeforeUnload)
  unregisterBeforeVisit = router.on?.('before', handleBeforeVisit)
  nextTick(focusFirst)
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown, true)
  window.removeEventListener('beforeunload', handleBeforeUnload)
  unregisterBeforeVisit?.()
})

const fieldIds = {
  asset_subsystem_id: 'asset-subsystem-id', code: 'part-code', unit_of_measure: 'part-unit',
  equipment: 'part-equipment', detail_equipment: 'part-name', severity: 'part-criticality',
  max_yearly_failure: 'part-failure-max', average_yearly_failure: 'part-failure-average',
  max_lead_time_months: 'part-lead-max', average_lead_time_months: 'part-lead-average',
}
const errorAttrs = (field) => ({
  'aria-invalid': form.errors[field] ? 'true' : undefined,
  'aria-describedby': form.errors[field] ? `${fieldIds[field]}-error` : undefined,
})
const focusFirstError = () => Object.keys(fieldIds).find((field) => {
  if (!form.errors[field]) return false
  document.getElementById(fieldIds[field])?.focus()
  return true
})
</script>

<template>
  <Teleport to="body">
    <div v-if="open" data-dialog-backdrop class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 backdrop-blur-[1px] sm:items-center sm:p-4" @click.self="close">
      <section ref="dialogPanel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="part-dialog-title" class="max-h-[96vh] w-full overscroll-contain overflow-y-auto rounded-t-2xl bg-white outline-none shadow-2xl sm:max-w-5xl sm:rounded-2xl">
        <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
          <div><p class="font-mono text-sm font-semibold uppercase tracking-[0.16em] text-[#2d2a70]">Master / suku cadang</p><h2 id="part-dialog-title" class="mt-1 text-lg font-semibold text-slate-950">{{ part ? 'Ubah suku cadang' : 'Tambah suku cadang' }}</h2><p class="mt-1 text-sm text-slate-500">Data manual mengikuti Reorder Stock. Nilai reorder dihitung otomatis.</p></div>
          <button data-close-dialog type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#2d2a70]" aria-label="Tutup dialog suku cadang" @click="close"><X :size="20" aria-hidden="true" /></button>
        </header>

        <form name="spare-part" autocomplete="off" class="space-y-6 p-5 sm:p-6" @submit.prevent="submit">
          <section aria-labelledby="identity-title" class="rounded-xl border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3"><h3 id="identity-title" class="text-sm font-semibold text-slate-950">Identitas</h3><p class="mt-0.5 text-sm text-slate-500">Kode, nama, kategori hierarkis, dan satuan pencatatan.</p></div>
            <div class="space-y-4 p-4">
              <CategorySelectFields v-model="form.asset_subsystem_id" :categories="categories" :errors="form.errors" />
              <div class="grid gap-4 md:grid-cols-2">
                <div><label for="part-code" class="mb-1.5 block text-sm font-medium text-slate-800">Kode suku cadang <span class="text-red-600">*</span></label><input id="part-code" v-model="form.code" v-bind="errorAttrs('code')" name="code" maxlength="50" :class="inputClass" placeholder="Contoh: SP-TC-001…" required /><p v-if="form.errors.code" id="part-code-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.code }}</p></div>
                <div><label for="part-unit" class="mb-1.5 block text-sm font-medium text-slate-800">Satuan pencatatan <span class="text-red-600">*</span></label><input id="part-unit" v-model="form.unit_of_measure" v-bind="errorAttrs('unit_of_measure')" name="unit_of_measure" maxlength="30" :class="inputClass" placeholder="Contoh: buah…" required /><p class="mt-1 text-sm text-slate-500">Gunakan satuan operasional; contoh: buah, set, meter.</p><p v-if="form.errors.unit_of_measure" id="part-unit-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.unit_of_measure }}</p></div>
                <div><label for="part-equipment" class="mb-1.5 block text-sm font-medium text-slate-800">Equipment <span class="text-red-600">*</span></label><input id="part-equipment" v-model="form.equipment" v-bind="errorAttrs('equipment')" name="equipment" maxlength="255" :class="inputClass" placeholder="Contoh: Track circuit…" required /><p v-if="form.errors.equipment" id="part-equipment-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.equipment }}</p></div>
                <div><label for="part-name" class="mb-1.5 block text-sm font-medium text-slate-800">Detail Equipment <span class="text-red-600">*</span></label><input id="part-name" v-model="form.detail_equipment" v-bind="errorAttrs('detail_equipment')" name="detail_equipment" maxlength="255" :class="inputClass" placeholder="Contoh: Relay 24 VDC…" required /><p v-if="form.errors.detail_equipment" id="part-name-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.detail_equipment }}</p></div>
                <div><label for="part-criticality" class="mb-1.5 block text-sm font-medium text-slate-800">Criticality <span class="text-red-600">*</span></label><select id="part-criticality" v-model="form.severity" v-bind="errorAttrs('severity')" name="severity" :class="inputClass" required><option value="">Pilih criticality</option><option value="Desirable">Desirable</option><option value="Essential">Essential</option><option value="Vital">Vital</option></select><p class="mt-1 text-sm text-slate-500">Pilih sesuai kategori Criticality pada file Excel.</p><p v-if="form.errors.severity" id="part-criticality-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ form.errors.severity }}</p></div>
              </div>
            </div>
          </section>

          <div class="grid gap-5 lg:grid-cols-3">
            <section aria-labelledby="failure-title" class="rounded-xl border border-slate-200 p-4"><h3 id="failure-title" class="text-sm font-semibold text-slate-950">Kegagalan</h3><p class="mt-1 text-sm text-slate-500">Frekuensi per tahun, maksimal dua desimal.</p><div class="mt-4 space-y-4"><div><label for="part-failure-max" class="mb-1.5 block text-sm font-medium text-slate-700">Maksimum <span class="text-red-600">*</span></label><input id="part-failure-max" v-model="form.max_yearly_failure" v-bind="errorAttrs('max_yearly_failure')" name="max_yearly_failure" type="number" min="0" step="0.01" :class="inputClass" required /><p v-if="form.errors.max_yearly_failure" id="part-failure-max-error" class="mt-1 text-sm text-red-600" role="alert">{{ form.errors.max_yearly_failure }}</p></div><div><label for="part-failure-average" class="mb-1.5 block text-sm font-medium text-slate-700">Rata-rata <span class="text-red-600">*</span></label><input id="part-failure-average" v-model="form.average_yearly_failure" v-bind="errorAttrs('average_yearly_failure')" name="average_yearly_failure" type="number" min="0" step="0.01" :class="[inputClass, 'bg-slate-50 text-slate-600']" readonly required /><p class="mt-1 text-sm text-slate-500">Otomatis dari maksimum dibagi 2.</p><p v-if="form.errors.average_yearly_failure" id="part-failure-average-error" class="mt-1 text-sm text-red-600" role="alert">{{ form.errors.average_yearly_failure }}</p></div></div></section>
            <section aria-labelledby="lead-title" class="rounded-xl border border-slate-200 p-4"><h3 id="lead-title" class="text-sm font-semibold text-slate-950">Lead time</h3><p class="mt-1 text-sm text-slate-500">Durasi pengadaan dalam bulan.</p><div class="mt-4 space-y-4"><div><label for="part-lead-max" class="mb-1.5 block text-sm font-medium text-slate-700">Maksimum <span class="text-red-600">*</span></label><input id="part-lead-max" v-model="form.max_lead_time_months" v-bind="errorAttrs('max_lead_time_months')" name="max_lead_time_months" type="number" min="0" step="0.01" :class="inputClass" required /><p v-if="form.errors.max_lead_time_months" id="part-lead-max-error" class="mt-1 text-sm text-red-600" role="alert">{{ form.errors.max_lead_time_months }}</p></div><div><label for="part-lead-average" class="mb-1.5 block text-sm font-medium text-slate-700">Rata-rata <span class="text-red-600">*</span></label><input id="part-lead-average" v-model="form.average_lead_time_months" v-bind="errorAttrs('average_lead_time_months')" name="average_lead_time_months" type="number" min="0" step="0.01" :class="[inputClass, 'bg-slate-50 text-slate-600']" readonly required /><p class="mt-1 text-sm text-slate-500">Otomatis dari maksimum dibagi 2.</p><p v-if="form.errors.average_lead_time_months" id="part-lead-average-error" class="mt-1 text-sm text-red-600" role="alert">{{ form.errors.average_lead_time_months }}</p></div></div></section>
            <section aria-labelledby="reorder-title" class="rounded-xl border border-slate-200 p-4"><h3 id="reorder-title" class="text-sm font-semibold text-slate-950">Reorder</h3><p class="mt-1 text-sm text-slate-500">Ambang stok dari rumus Reorder Stock.</p><dl class="mt-4 space-y-3"><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5"><dt class="text-sm font-semibold uppercase text-slate-500">Safety stock</dt><dd data-calculated-safety-stock class="mt-1 font-mono text-lg font-semibold text-slate-950">{{ calculatedValue('safety_stock') }}</dd></div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5"><dt class="text-sm font-semibold uppercase text-slate-500">Kebutuhan lead time</dt><dd data-calculated-lead-time-demand class="mt-1 font-mono text-lg font-semibold text-slate-950">{{ calculatedValue('lead_time_demand') }}</dd></div><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5"><dt class="text-sm font-semibold uppercase text-slate-500">Reorder point</dt><dd data-calculated-reorder-point class="mt-1 font-mono text-lg font-semibold text-slate-950">{{ calculatedValue('reorder_point') }}</dd></div></dl></section>
          </div>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <button v-if="part?.is_active" ref="deactivateButton" data-deactivate-part type="button" :disabled="form.processing || deactivateForm.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold text-red-700 outline-none hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50" @click="confirmingDeactivate = true"><Archive :size="17" aria-hidden="true" /> Nonaktifkan</button><span v-else />
            <div class="flex flex-col-reverse gap-3 sm:flex-row"><button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="close">Batal</button><button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-[#f26522] px-5 text-sm font-semibold text-white outline-none hover:bg-[#d95418] focus:ring-2 focus:ring-[#f26522] focus:ring-offset-2 disabled:opacity-50">{{ form.processing ? 'Menyimpan…' : 'Simpan suku cadang' }}</button></div>
          </div>
        </form>
      </section>

      <div v-if="confirmingDeactivate" data-deactivate-confirmation class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4">
        <section ref="confirmationPanel" tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="deactivate-title" class="w-full max-w-md rounded-2xl bg-white p-6 outline-none shadow-2xl"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-700"><Archive :size="21" aria-hidden="true" /></span><h3 id="deactivate-title" class="mt-4 text-lg font-semibold text-slate-950">Nonaktifkan suku cadang?</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ part.detail_equipment }} tidak dapat dipilih pada transaksi baru. Riwayat dan stok yang sudah tercatat tetap utuh.</p><div class="mt-5 flex justify-end gap-3"><button type="button" :disabled="deactivateForm.processing" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:opacity-50" @click="dismissDeactivateConfirmation">Batal</button><button data-confirm-deactivate type="button" :disabled="deactivateForm.processing" class="min-h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" @click="deactivate">{{ deactivateForm.processing ? 'Menonaktifkan…' : 'Ya, nonaktifkan' }}</button></div></section>
      </div>

      <div v-if="confirmingDiscard" data-discard-confirmation class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4">
        <section ref="discardPanel" tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="discard-part-title" class="w-full max-w-md rounded-2xl bg-white p-6 outline-none shadow-2xl"><Archive :size="24" class="text-amber-700" aria-hidden="true" /><h3 id="discard-part-title" class="mt-4 text-lg font-semibold text-slate-950">Buang perubahan suku cadang?</h3><p class="mt-2 text-sm leading-6 text-slate-600">Data yang belum disimpan akan hilang.</p><div class="mt-5 flex justify-end gap-3"><button data-discard-cancel type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="dismissDiscardConfirmation">Lanjut mengisi</button><button data-confirm-discard type="button" class="min-h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white" @click="confirmDiscard">Buang perubahan</button></div></section>
      </div>
    </div>
  </Teleport>
</template>
