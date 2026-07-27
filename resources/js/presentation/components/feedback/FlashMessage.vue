<script setup>
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next'
import { ref, watch } from 'vue'

const props = defineProps({
  success: { type: String, default: null },
  error: { type: String, default: null },
})

const visible = ref(Boolean(props.success || props.error))

watch(() => [props.success, props.error], () => {
  visible.value = Boolean(props.success || props.error)
})
</script>

<template>
  <div v-if="visible && (success || error)" class="mb-5">
    <div
      v-if="success"
      role="status"
      class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
    >
      <CircleCheck :size="19" class="mt-0.5 shrink-0 text-emerald-600" aria-hidden="true" />
      <p class="flex-1 leading-5">{{ success }}</p>
      <button type="button" class="rounded p-0.5 text-emerald-700 hover:bg-emerald-100" aria-label="Tutup pemberitahuan" @click="visible = false">
        <X :size="16" aria-hidden="true" />
      </button>
    </div>

    <div
      v-else
      role="alert"
      class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
    >
      <CircleAlert :size="19" class="mt-0.5 shrink-0 text-red-600" aria-hidden="true" />
      <p class="flex-1 leading-5">{{ error }}</p>
      <button type="button" class="rounded p-0.5 text-red-700 hover:bg-red-100" aria-label="Tutup pemberitahuan" @click="visible = false">
        <X :size="16" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>
