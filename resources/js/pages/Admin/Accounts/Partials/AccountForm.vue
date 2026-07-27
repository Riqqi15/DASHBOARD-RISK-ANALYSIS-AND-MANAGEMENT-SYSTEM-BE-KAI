<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps({ account: { type: Object, default: null }, units: { type: Array, required: true }, submitLabel: { type: String, required: true } })
const form = useForm({ name: props.account?.name ?? '', username: props.account?.username ?? '', email: props.account?.email ?? '', unit_kerja_id: props.account?.unit_kerja_id ?? '', password: '', password_confirmation: '' })
const submit = () => props.account ? form.put(`/admin/accounts/${props.account.id}`) : form.post('/admin/accounts')
const input = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm outline-none focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
</script>
<template>
  <form class="space-y-5" @submit.prevent="submit">
    <div class="grid gap-5 md:grid-cols-2">
      <div><label for="name" class="mb-2 block text-sm font-medium">Nama pengguna</label><input id="name" v-model="form.name" :class="input" required autocomplete="name"><p v-if="form.errors.name" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.name }}</p></div>
      <div><label for="username" class="mb-2 block text-sm font-medium">Username</label><input id="username" v-model="form.username" type="text" :class="input" required autocomplete="username"><p class="mt-1 text-xs text-slate-500">Huruf kecil, angka, titik, garis bawah, atau tanda hubung.</p><p v-if="form.errors.username" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.username }}</p></div>
    </div>
    <div><label for="email" class="mb-2 block text-sm font-medium">Email kontak <span class="font-normal text-slate-400">(opsional)</span></label><input id="email" v-model="form.email" type="email" :class="input" autocomplete="email"><p v-if="form.errors.email" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.email }}</p></div>
    <div><label for="unit" class="mb-2 block text-sm font-medium">Unit kerja</label><select id="unit" v-model="form.unit_kerja_id" :class="input" required><option value="" disabled>Pilih unit aktif</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }} — {{ unit.name }}</option></select><p v-if="form.errors.unit_kerja_id" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.unit_kerja_id }}</p></div>
    <div v-if="!account" class="grid gap-5 md:grid-cols-2">
      <div><label for="password" class="mb-2 block text-sm font-medium">Kata sandi</label><input id="password" v-model="form.password" type="password" :class="input" minlength="12" required autocomplete="new-password"><p class="mt-1 text-xs text-slate-500">Minimal 12 karakter.</p><p v-if="form.errors.password" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.password }}</p></div>
      <div><label for="confirmation" class="mb-2 block text-sm font-medium">Konfirmasi kata sandi</label><input id="confirmation" v-model="form.password_confirmation" type="password" :class="input" minlength="12" required autocomplete="new-password"></div>
    </div>
    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end"><Link href="/admin/accounts" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 px-5 text-sm font-medium hover:bg-slate-50">Batal</Link><BaseButton type="submit" variant="primary" class="h-11 rounded-lg" :loading="form.processing">{{ submitLabel }}</BaseButton></div>
  </form>
</template>
