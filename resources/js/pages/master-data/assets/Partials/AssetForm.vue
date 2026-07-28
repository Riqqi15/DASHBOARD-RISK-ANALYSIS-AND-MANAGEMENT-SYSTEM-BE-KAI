<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { Info, Save } from 'lucide-vue-next'
import BaseButton from '@/components/base/BaseButton.vue'
import CategorySelectFields from './CategorySelectFields.vue'

const props = defineProps({
  asset: { type: Object, default: null },
  units: { type: Array, required: true },
  categories: { type: Array, required: true },
  statusOptions: { type: Array, required: true },
  can: { type: Object, required: true },
  submitLabel: { type: String, required: true },
})

const form = useForm({
  unit_kerja_id: props.asset?.unit_kerja_id ?? '',
  nama_aset: props.asset?.nama_aset ?? '',
  asset_subsystem_id: props.asset?.asset_subsystem_id ?? null,
  lokasi: props.asset?.lokasi ?? '',
  jumlah_unit: props.asset?.jumlah_unit ?? 0,
  tanggal_pemasangan: props.asset?.tanggal_pemasangan ?? '',
  status: props.asset?.status ?? 'aktif',
})

const submit = () => {
  const options = { preserveScroll: true }

  if (props.asset) {
    form.put(`/master-asset/${props.asset.id}`, options)
    return
  }

  form.post('/master-asset', options)
}

const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
const today = new Date().toISOString().slice(0, 10)
</script>

<template>
  <form class="space-y-8" @submit.prevent="submit">
    <section aria-labelledby="asset-identity-title">
      <div class="mb-5 border-b border-slate-200 pb-4">
        <h3 id="asset-identity-title" class="text-base font-semibold text-slate-950">Identitas aset</h3>
        <p class="mt-1 text-sm text-slate-500">Nama yang mudah dikenali dan wilayah tempat aset tercatat.</p>
      </div>

      <div class="grid gap-6 md:grid-cols-2">
        <div v-if="can.choose_unit">
          <label for="unit-kerja" class="mb-2 block text-sm font-medium text-slate-800">Unit kerja</label>
          <select id="unit-kerja" v-model="form.unit_kerja_id" :class="inputClass" required>
            <option value="" disabled>Pilih unit kerja</option>
            <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }} — {{ unit.name }}</option>
          </select>
          <p v-if="form.errors.unit_kerja_id" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.unit_kerja_id }}</p>
        </div>
        <div v-else class="rounded-lg border border-blue-100 bg-blue-50/70 p-4 md:col-span-2">
          <div class="flex gap-3">
            <Info :size="18" class="mt-0.5 shrink-0 text-[#2d2a70]" aria-hidden="true" />
            <div>
              <p class="text-sm font-medium text-[#171650]">Unit kerja mengikuti akun Anda</p>
              <p class="mt-1 text-xs leading-5 text-slate-600">Aset otomatis disimpan ke wilayah akun yang sedang digunakan.</p>
            </div>
          </div>
        </div>

        <div :class="can.choose_unit ? '' : 'md:col-span-2'">
          <label for="nama-aset" class="mb-2 block text-sm font-medium text-slate-800">Nama aset</label>
          <input id="nama-aset" v-model="form.nama_aset" :class="inputClass" maxlength="255" required placeholder="Contoh: Track Circuit Stasiun Gambir" autocomplete="off" />
          <p class="mt-1.5 text-xs leading-5 text-slate-500">Saat impor pertama, nama diambil dari subsystem dan tetap dapat disunting tanpa ditimpa impor berikutnya.</p>
          <p v-if="form.errors.nama_aset" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.nama_aset }}</p>
        </div>

        <div>
          <label for="lokasi" class="mb-2 block text-sm font-medium text-slate-800">Lokasi <span class="font-normal text-slate-400">(opsional)</span></label>
          <input id="lokasi" v-model="form.lokasi" :class="inputClass" maxlength="255" placeholder="Stasiun, lintas, atau lokasi pemasangan" autocomplete="off" />
          <p v-if="form.errors.lokasi" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.lokasi }}</p>
        </div>
      </div>
    </section>

    <section aria-labelledby="asset-classification-title">
      <div class="mb-5 border-b border-slate-200 pb-4">
        <h3 id="asset-classification-title" class="text-base font-semibold text-slate-950">Klasifikasi teknis</h3>
        <p class="mt-1 text-sm text-slate-500">Struktur pengelompokan sesuai sumber data RAMS.</p>
      </div>

      <CategorySelectFields
        v-model="form.asset_subsystem_id"
        :categories="categories"
        :errors="form.errors"
      />
    </section>

    <section aria-labelledby="asset-operation-title">
      <div class="mb-5 border-b border-slate-200 pb-4">
        <h3 id="asset-operation-title" class="text-base font-semibold text-slate-950">Data operasional</h3>
        <p class="mt-1 text-sm text-slate-500">Jumlah, waktu pemasangan, dan kondisi penggunaan aset.</p>
      </div>

      <div class="grid gap-6 md:grid-cols-3">
        <div>
          <label for="jumlah-unit" class="mb-2 block text-sm font-medium text-slate-800">Jumlah unit</label>
          <input id="jumlah-unit" v-model.number="form.jumlah_unit" type="number" min="0" step="1" :class="inputClass" required />
          <p v-if="form.errors.jumlah_unit" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.jumlah_unit }}</p>
        </div>

        <div>
          <label for="tanggal-pemasangan" class="mb-2 block text-sm font-medium text-slate-800">Tanggal pemasangan <span class="font-normal text-slate-400">(opsional)</span></label>
          <input id="tanggal-pemasangan" v-model="form.tanggal_pemasangan" type="date" :max="today" :class="inputClass" />
          <p v-if="form.errors.tanggal_pemasangan" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.tanggal_pemasangan }}</p>
        </div>

        <div>
          <label for="status" class="mb-2 block text-sm font-medium text-slate-800">Status</label>
          <select id="status" v-model="form.status" :class="inputClass" required>
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="form.errors.status" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.status }}</p>
        </div>
      </div>
    </section>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
      <Link href="/master-asset" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Batal</Link>
      <BaseButton type="submit" variant="primary" class="h-11 rounded-lg px-5" :loading="form.processing">
        <Save :size="17" class="mr-2" aria-hidden="true" />
        {{ submitLabel }}
      </BaseButton>
    </div>
  </form>
</template>
