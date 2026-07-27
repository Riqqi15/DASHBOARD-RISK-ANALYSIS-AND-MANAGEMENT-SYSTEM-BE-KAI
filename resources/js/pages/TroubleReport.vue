<template>
  <TroubleReportView :subsystem="subsystem" />
</template>

<script setup>
import TroubleReportView from '../presentation/views/input-data/trouble-report/TroubleReportView.vue'

defineProps({
  subsystem: {
    type: String,
    default: 'Subsystem Tidak Diketahui',
  },
})
</script>