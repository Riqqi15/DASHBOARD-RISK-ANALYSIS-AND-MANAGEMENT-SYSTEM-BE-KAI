<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center h-9 px-4 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed',
      variantClasses[variant]
    ]"
    @click="$emit('click', $event)"
  >
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <slot />
  </button>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary' // 'primary' (Oranye KAI), 'secondary' (Putih border), 'danger' (Merah)
  },
  type: {
    type: String,
    default: 'button'
  },
  disabled: Boolean,
  loading: Boolean
})

defineEmits(['click'])

const variantClasses = {
  // Tombol Utama (Simpan/Tambah): Oranye KAI solid, font-medium, rounded-md, no bold (Anti AI Slop)
  primary: 'bg-[#EA580C] text-white hover:bg-[#C2410C] focus:ring-[#EA580C]',
  
  // Tombol Sekunder (Kembali/Batal): Latar putih solid, border abu-abu tipis, teks gelap
  secondary: 'bg-white text-gray-800 border border-gray-300 hover:bg-gray-50 focus:ring-gray-400',

  // Tombol Aksen Biru KAI
  blue: 'bg-[#1D4ED8] text-white hover:bg-[#1E40AF] focus:ring-[#1D4ED8]',

  // Tombol Bahaya / Hapus
  danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-600'
}
</script>
