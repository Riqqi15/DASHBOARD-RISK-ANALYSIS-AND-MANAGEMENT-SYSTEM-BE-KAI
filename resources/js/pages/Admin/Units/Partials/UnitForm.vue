<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { Save } from 'lucide-vue-next'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps({
  unit: { type: Object, default: null },
  typeOptions: { type: Array, required: true },
  submitLabel: { type: String, required: true },
})

const form = useForm({
  code: props.unit?.code ?? '',
  name: props.unit?.name ?? '',
  type: props.unit?.type ?? 'daop',
  is_active: props.unit?.is_active ?? true,
  operating_start_date: props.unit?.operating_start_date ?? '',
})

const submit = () => {
  if (props.unit) {
    form.put(`/admin/units/${props.unit.id}`, { preserveScroll: true })
    return
  }

  form.post('/admin/units', { preserveScroll: true })
}

const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
</script>

<template>
  <form class="space-y-6" @submit.prevent="submit">
    <div class="grid gap-6 md:grid-cols-2">
      <div>
        <label for="code" class="mb-2 block text-sm font-medium text-slate-800">Kode unit</label>
        <input id="code" v-model="form.code" :class="inputClass" maxlength="20" required placeholder="Contoh: DAOP-1" autocomplete="off" />
        <p class="mt-1.5 text-xs text-slate-500">Huruf, angka, tanda hubung, atau garis bawah. Kode otomatis menjadi kapital.</p>
        <p v-if="form.errors.code" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.code }}</p>
      </div>

      <div>
        <label for="type" class="mb-2 block text-sm font-medium text-slate-800">Jenis unit</label>
        <select id="type" v-model="form.type" :class="inputClass" required>
          <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="form.errors.type" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.type }}</p>
      </div>
    </div>

    <div>
      <label for="name" class="mb-2 block text-sm font-medium text-slate-800">Nama unit kerja</label>
      <input id="name" v-model="form.name" :class="inputClass" maxlength="255" required placeholder="Nama lengkap unit kerja" autocomplete="organization" />
      <p v-if="form.errors.name" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.name }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <label class="flex cursor-pointer items-start gap-3">
        <input v-model="form.is_active" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-[#2d2a70]" />
        <span>
          <span class="block text-sm font-medium text-slate-800">Unit aktif</span>
          <span class="mt-1 block text-xs leading-5 text-slate-500">Unit aktif dapat dipilih saat administrator membuat atau memindahkan akun wilayah.</span>
        </span>
      </label>
      <p v-if="form.errors.is_active" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.is_active }}</p>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
      <label for="operating_start_date" class="mb-2 block text-sm font-medium text-slate-800">
        Tanggal mulai operasi
        <span class="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">Override</span>
      </label>
      <input
        id="operating_start_date"
        v-model="form.operating_start_date"
        type="date"
        :class="inputClass"
      />
      <p class="mt-1.5 text-xs text-slate-500">
        Jika diisi, tanggal ini akan digunakan sebagai acuan hari operasi di dashboard.
        Jika dikosongkan, sistem akan menggunakan tanggal pemasangan asset tertua secara otomatis.
      </p>
      <p v-if="form.errors.operating_start_date" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.operating_start_date }}</p>
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
      <Link href="/admin/units" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 text-sm font-medium text-slate-700 hover:bg-slate-50">Batal</Link>
      <BaseButton type="submit" variant="primary" class="h-11 rounded-lg px-5" :loading="form.processing">
        <Save :size="17" class="mr-2" aria-hidden="true" />
        {{ submitLabel }}
      </BaseButton>
    </div>
  </form>
</template>
