<template>
  <div class="min-h-screen bg-[#F9FAFB] flex flex-col justify-center items-center p-4">
    <!-- Brand Logo -->
    <div class="mb-6 text-center">
      <div class="flex items-center justify-center space-x-2">
        <span class="font-extrabold text-3xl text-[#1E3A8A] tracking-wider">KAI</span>
        <span class="text-xs px-2.5 py-1 bg-[#EA580C] text-white font-medium rounded">RAMS</span>
      </div>
      <p class="text-sm text-gray-600 mt-2">Risk Analysis and Management System</p>
    </div>

    <!-- Login Card (Solid Enterprise Style, No Glassmorphism, No AI Slop) -->
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
      <h2 class="text-lg font-bold text-gray-900 mb-1">Masuk ke Sistem</h2>
      <p class="text-xs text-gray-500 mb-6">Pilih simulasi peran akun untuk memulai sesi latihan</p>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Peran Akun (Simulasi 2 Role)</label>
          <select 
            v-model="selectedRole" 
            class="w-full h-10 px-3 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#1D4ED8]"
          >
            <option value="PUSAT">Akun Pusat (Akses Semua Daop/Divre)</option>
            <option value="DAOP1">Akun Daop 1 Jakarta (Terisolasi Wilayah)</option>
            <option value="DIVRE3">Akun Divre III Palembang (Terisolasi Wilayah)</option>
          </select>
        </div>

        <div class="pt-2">
          <BaseButton type="submit" variant="primary" class="w-full">
            Masuk ke Dashboard
          </BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import BaseButton from '@/presentation/components/base/BaseButton.vue'

const selectedRole = ref('DAOP1')

const handleLogin = () => {
  // Store role in local storage for the demo auth state
  localStorage.setItem('rams_mock_role', selectedRole.value)
  router.visit('/dashboard')
}
</script>
