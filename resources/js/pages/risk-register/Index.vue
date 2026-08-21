<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { AlertTriangle, Pencil, Plus, Search, ShieldCheck, Trash2, X } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import AreaSelectorBanner from '@/components/dashboard/AreaSelectorBanner.vue'

const props = defineProps({
  selected_area: { type: String, default: null },
  can_choose_unit: { type: Boolean, default: false },
  units: { type: Array, default: () => [] },
  assets: { type: Array, default: () => [] },
  registers: { type: Array, default: () => [] },
})

const search = ref('')
const status = ref('all')
const dialogOpen = ref(false)
const selected = ref(null)
const selectedUnit = computed(() => props.units.find(unit => unit.code === props.selected_area))
const emptyForm = () => ({
  unit_kerja_id: props.can_choose_unit ? selectedUnit.value?.id ?? '' : '',
  asset_id: '', part_number: '', sub: '', risk_event: '', risk_cause: '', impact: '',
  part_name: '', recommendation: '', likelihood: 1, consequence: 1, status: 'open',
})
const form = useForm(emptyForm())

const filtered = computed(() => props.registers.filter((item) => {
  const term = search.value.trim().toLowerCase()
  const matchesStatus = status.value === 'all' || item.status === status.value
  const haystack = [item.risk_event, item.risk_cause, item.part_number, item.asset?.name]
    .filter(Boolean).join(' ').toLowerCase()
  return matchesStatus && (!term || haystack.includes(term))
}))

const counts = computed(() => ({
  total: props.registers.length,
  open: props.registers.filter(item => item.status === 'open').length,
  inProgress: props.registers.filter(item => item.status === 'in_progress').length,
  closed: props.registers.filter(item => item.status === 'closed').length,
}))

const openCreate = () => {
  selected.value = null
  form.defaults(emptyForm())
  form.reset()
  form.clearErrors()
  dialogOpen.value = true
}

const openEdit = (item) => {
  selected.value = item
  form.defaults({
    unit_kerja_id: props.can_choose_unit ? selectedUnit.value?.id ?? '' : '',
    asset_id: item.asset_id,
    part_number: item.part_number || '',
    sub: item.sub || '',
    risk_event: item.risk_event,
    risk_cause: item.risk_cause,
    impact: item.impact || '',
    part_name: item.part_name || '',
    recommendation: item.recommendation || '',
    likelihood: item.likelihood,
    consequence: item.consequence,
    status: item.status,
  })
  form.reset()
  form.clearErrors()
  dialogOpen.value = true
}

const closeDialog = () => {
  if (!form.processing) dialogOpen.value = false
}

const submit = () => {
  const options = { preserveScroll: true, onSuccess: closeDialog }
  if (selected.value) form.put(scopedUrl(`/risk-register/${selected.value.id}`), options)
  else form.post(scopedUrl('/risk-register'), options)
}

const scopedUrl = path => props.can_choose_unit && props.selected_area
  ? `${path}?area=${encodeURIComponent(props.selected_area)}`
  : path

const remove = (item) => {
  if (window.confirm(`Hapus Risk Register “${item.risk_event}”?`)) {
    router.delete(scopedUrl(`/risk-register/${item.id}`), { preserveScroll: true })
  }
}

const ratingTone = (rating) => rating >= 12
  ? 'bg-red-50 text-red-700 border-red-100'
  : rating >= 8
    ? 'bg-orange-50 text-orange-700 border-orange-100'
    : rating >= 4
      ? 'bg-amber-50 text-amber-700 border-amber-100'
      : 'bg-emerald-50 text-emerald-700 border-emerald-100'

const statusLabel = value => ({ open: 'Open', in_progress: 'In Progress', closed: 'Closed' }[value] || value)
</script>

<template>
  <Head><title>Risk Register</title></Head>
  <MainLayout>
    <div class="space-y-6">
      <AreaSelectorBanner v-if="can_choose_unit" :units="units" :selected-area="selected_area" />

      <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orange-600">Risk Management</p>
          <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Risk Register</h1>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Kelola hasil identifikasi risiko dari sheet LxC. Nilai likelihood dan consequence mengikuti skala 1–4 pada workbook KAI.</p>
        </div>
        <button type="button" class="inline-flex h-10 items-center gap-2 rounded-lg bg-orange-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-700" @click="openCreate">
          <Plus :size="17" /> Tambah Risiko
        </button>
      </header>

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="item in [
          ['Total Risiko', counts.total, 'text-slate-900'],
          ['Open', counts.open, 'text-red-700'],
          ['In Progress', counts.inProgress, 'text-orange-700'],
          ['Closed', counts.closed, 'text-emerald-700'],
        ]" :key="item[0]" class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item[0] }}</p>
          <p class="mt-2 text-2xl font-bold" :class="item[2]">{{ item[1] }}</p>
        </div>
      </div>

      <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
          <label for="risk-register-search" class="relative min-w-[240px] flex-1">
            <span class="sr-only">Cari risiko atau aset</span>
            <Search class="pointer-events-none absolute left-3 top-2.5 text-slate-400" :size="18" />
            <input id="risk-register-search" v-model="search" type="search" placeholder="Cari risiko atau aset…" class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" />
          </label>
          <label for="risk-register-status" class="sr-only">Status risiko</label>
          <select id="risk-register-status" v-model="status" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-orange-400">
            <option value="all">Semua Status</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="closed">Closed</option>
          </select>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-white text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3 font-semibold">Risiko</th>
                <th class="px-5 py-3 font-semibold">Aset</th>
                <th class="px-5 py-3 font-semibold">L × C</th>
                <th class="px-5 py-3 font-semibold">Status</th>
                <th class="px-5 py-3 font-semibold">Sumber</th>
                <th class="px-5 py-3 text-right font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in filtered" :key="item.id" class="align-top hover:bg-slate-50/70">
                <td class="max-w-md px-5 py-4">
                  <p class="font-semibold text-slate-950">{{ item.risk_event }}</p>
                  <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-600">{{ item.risk_cause }}</p>
                  <p v-if="item.part_number" class="mt-1 font-mono text-[11px] text-slate-400">{{ item.part_number }}</p>
                </td>
                <td class="px-5 py-4">
                  <p class="font-medium text-slate-800">{{ item.asset?.name || '-' }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ item.asset?.unit?.code }}</p>
                </td>
                <td class="whitespace-nowrap px-5 py-4">
                  <span class="inline-flex rounded-lg border px-2.5 py-1 font-bold" :class="ratingTone(item.rating)">{{ item.likelihood }} × {{ item.consequence }} = {{ item.rating }}</span>
                </td>
                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ statusLabel(item.status) }}</span></td>
                <td class="px-5 py-4"><span class="text-xs font-semibold" :class="item.source === 'excel' ? 'text-blue-700' : 'text-slate-500'">{{ item.source === 'excel' ? 'Excel LxC' : 'Manual' }}</span></td>
                <td class="px-5 py-4">
                  <div class="flex justify-end gap-1">
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-blue-50 hover:text-blue-700" aria-label="Edit risk register" @click="openEdit(item)"><Pencil :size="16" /></button>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-700" aria-label="Hapus risk register" @click="remove(item)"><Trash2 :size="16" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="filtered.length === 0"><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">Belum ada Risk Register yang sesuai filter.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div v-if="dialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="closeDialog">
      <section class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
          <div class="flex items-center gap-3"><span class="rounded-lg bg-orange-50 p-2 text-orange-700"><ShieldCheck :size="20" /></span><div><h2 class="font-bold text-slate-950">{{ selected ? 'Edit Risk Register' : 'Tambah Risk Register' }}</h2><p class="text-xs text-slate-500">Data Excel dapat diperbarui kembali saat workbook diimpor ulang.</p></div></div>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="closeDialog"><X :size="19" /></button>
        </header>
        <form class="grid gap-4 p-6 md:grid-cols-2" @submit.prevent="submit">
          <label class="md:col-span-2"><span class="mb-1.5 block text-sm font-semibold text-slate-700">Aset</span><select v-model="form.asset_id" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="">Pilih aset</option><option v-for="asset in assets" :key="asset.id" :value="asset.id">{{ asset.unit?.code }} · {{ asset.name }}</option></select><span v-if="form.errors.asset_id" class="mt-1 block text-xs text-red-600">{{ form.errors.asset_id }}</span></label>
          <label><span class="mb-1.5 block text-sm font-semibold text-slate-700">Part Number</span><input v-model="form.part_number" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" /></label>
          <label><span class="mb-1.5 block text-sm font-semibold text-slate-700">Subsystem/Sub</span><input v-model="form.sub" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" /></label>
          <label class="md:col-span-2"><span class="mb-1.5 block text-sm font-semibold text-slate-700">Peristiwa Risiko</span><input v-model="form.risk_event" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" /><span v-if="form.errors.risk_event" class="mt-1 block text-xs text-red-600">{{ form.errors.risk_event }}</span></label>
          <label class="md:col-span-2"><span class="mb-1.5 block text-sm font-semibold text-slate-700">Penyebab</span><textarea v-model="form.risk_cause" required rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
          <label><span class="mb-1.5 block text-sm font-semibold text-slate-700">Likelihood (1–4)</span><input v-model.number="form.likelihood" type="number" min="1" max="4" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" /></label>
          <label><span class="mb-1.5 block text-sm font-semibold text-slate-700">Consequence (1–4)</span><input v-model.number="form.consequence" type="number" min="1" max="4" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" /></label>
          <label class="md:col-span-2"><span class="mb-1.5 block text-sm font-semibold text-slate-700">Dampak</span><textarea v-model="form.impact" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
          <label><span class="mb-1.5 block text-sm font-semibold text-slate-700">Nama Part</span><input v-model="form.part_name" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" /></label>
          <label><span class="mb-1.5 block text-sm font-semibold text-slate-700">Status</span><select v-model="form.status" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"><option value="open">Open</option><option value="in_progress">In Progress</option><option value="closed">Closed</option></select></label>
          <label class="md:col-span-2"><span class="mb-1.5 block text-sm font-semibold text-slate-700">Rekomendasi</span><textarea v-model="form.recommendation" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
          <div class="md:col-span-2 flex items-center justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" class="h-10 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="closeDialog">Batal</button><button type="submit" :disabled="form.processing" class="h-10 rounded-lg bg-orange-600 px-5 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</button></div>
        </form>
      </section>
    </div>
  </MainLayout>
</template>
