<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { LockKeyhole, ShieldCheck, TrainFront } from 'lucide-vue-next'
import BaseButton from '@/components/base/BaseButton.vue'
import kaiLogo from '@/assets/logo-kai.png'

const form = useForm({
  username: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <Head><title>Masuk</title></Head>

  <main class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(32rem,0.95fr)]">
    <section class="relative hidden overflow-hidden bg-[#171650] px-12 py-10 text-white lg:flex lg:flex-col">
      <div class="absolute inset-0 opacity-[0.08]" aria-hidden="true">
        <div class="absolute -left-24 top-32 h-96 w-96 rounded-full border-[72px] border-white" />
        <div class="absolute -right-40 -top-36 h-[34rem] w-[34rem] rounded-full border-[96px] border-[#f97316]" />
      </div>

      <div class="relative flex items-center gap-3">
        <div class="rounded-xl bg-white px-4 py-3 shadow-sm">
          <img :src="kaiLogo" alt="Kereta Api Indonesia" class="h-7 w-auto" />
        </div>
        <div class="h-8 w-px bg-white/25" />
        <div>
          <p class="text-sm font-semibold tracking-[0.18em]">RAMS</p>
          <p class="text-xs text-blue-100">Risk Analysis &amp; Management</p>
        </div>
      </div>

      <div class="relative my-auto max-w-xl py-16">
        <p class="mb-5 text-xs font-semibold uppercase tracking-[0.24em] text-orange-300">Sistem keselamatan terpadu</p>
        <h1 class="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
          Keputusan risiko yang lebih cepat, jelas, dan dapat ditelusuri.
        </h1>
        <p class="mt-6 max-w-lg text-base leading-7 text-blue-100/90">
          Pantau kondisi aset, evaluasi risiko, dan tindak lanjuti temuan operasional dari satu ruang kerja yang konsisten.
        </p>

        <ol class="mt-10 space-y-5" aria-label="Alur RAMS">
          <li class="flex items-center gap-4">
            <span class="flex h-9 w-9 items-center justify-center rounded-full border border-orange-300/70 bg-orange-400/10 text-sm font-semibold text-orange-200">01</span>
            <span class="text-sm text-blue-50">Identifikasi kondisi dan potensi gangguan</span>
          </li>
          <li class="ml-4 h-5 w-px bg-gradient-to-b from-orange-300/70 to-blue-200/30" aria-hidden="true" />
          <li class="flex items-center gap-4">
            <span class="flex h-9 w-9 items-center justify-center rounded-full border border-blue-200/50 bg-white/5 text-sm font-semibold text-blue-100">02</span>
            <span class="text-sm text-blue-50">Analisis dampak dan prioritas penanganan</span>
          </li>
          <li class="ml-4 h-5 w-px bg-gradient-to-b from-blue-200/30 to-blue-200/10" aria-hidden="true" />
          <li class="flex items-center gap-4">
            <span class="flex h-9 w-9 items-center justify-center rounded-full border border-blue-200/50 bg-white/5 text-sm font-semibold text-blue-100">03</span>
            <span class="text-sm text-blue-50">Kendalikan tindak lanjut berbasis data</span>
          </li>
        </ol>
      </div>

      <p class="relative text-xs text-blue-200/75">PT Kereta Api Indonesia (Persero) · Internal Operational System</p>
    </section>

    <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-16">
      <div class="w-full max-w-md">
        <div class="mb-10 flex items-center justify-between lg:hidden">
          <img :src="kaiLogo" alt="Kereta Api Indonesia" class="h-8 w-auto" />
          <span class="rounded-md bg-[#171650] px-2.5 py-1 text-xs font-semibold tracking-[0.15em] text-white">RAMS</span>
        </div>

        <div class="mb-8">
          <div class="mb-5 flex h-11 w-11 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
            <TrainFront :size="22" aria-hidden="true" />
          </div>
          <p class="text-sm font-medium text-orange-600">Selamat datang kembali</p>
          <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Masuk ke KAI RAMS</h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">Gunakan akun yang telah diberikan oleh administrator Pusat.</p>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
          <div>
            <label for="username" class="mb-2 block text-sm font-medium text-slate-800">Username</label>
            <input
              id="username"
              v-model="form.username"
              type="text"
              autocomplete="username"
              required
              autofocus
              placeholder="Masukkan username"
              :aria-invalid="Boolean(form.errors.username)"
              :aria-describedby="form.errors.username ? 'username-error' : undefined"
              class="h-12 w-full rounded-lg border bg-white px-3.5 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10"
              :class="form.errors.username ? 'border-red-400' : 'border-slate-300'"
            />
            <p v-if="form.errors.username" id="username-error" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.username }}</p>
          </div>

          <div>
            <label for="password" class="mb-2 block text-sm font-medium text-slate-800">Kata sandi</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              placeholder="Masukkan kata sandi"
              :aria-invalid="Boolean(form.errors.password)"
              :aria-describedby="form.errors.password ? 'password-error' : undefined"
              class="h-12 w-full rounded-lg border bg-white px-3.5 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10"
              :class="form.errors.password ? 'border-red-400' : 'border-slate-300'"
            />
            <p v-if="form.errors.password" id="password-error" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.password }}</p>
          </div>

          <label class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-slate-700">
            <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 accent-[#2d2a70]" />
            Ingat saya di perangkat ini
          </label>

          <BaseButton type="submit" variant="primary" class="h-12 w-full rounded-lg" :loading="form.processing">
            <LockKeyhole :size="17" class="mr-2" aria-hidden="true" />
            {{ form.processing ? 'Memeriksa akun…' : 'Masuk ke sistem' }}
          </BaseButton>
        </form>

        <div class="mt-8 flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
          <ShieldCheck :size="19" class="mt-0.5 shrink-0 text-emerald-600" aria-hidden="true" />
          <p class="leading-5">Akses dibatasi untuk pengguna berwenang. Aktivitas masuk tercatat untuk keamanan sistem.</p>
        </div>
      </div>
    </section>
  </main>
</template>
