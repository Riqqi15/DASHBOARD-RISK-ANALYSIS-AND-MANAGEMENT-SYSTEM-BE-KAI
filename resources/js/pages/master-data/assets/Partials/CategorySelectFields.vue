<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  categories: { type: Array, required: true },
  modelValue: { type: [Number, String, null], default: null },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const selectedGroupId = ref('')
const selectedSystemId = ref('')
const selectedSubsystemId = ref('')

const selectedGroup = computed(() => props.categories.find(
  (group) => String(group.id) === String(selectedGroupId.value),
))
const systems = computed(() => selectedGroup.value?.systems ?? [])
const selectedSystem = computed(() => systems.value.find(
  (system) => String(system.id) === String(selectedSystemId.value),
))
const subsystems = computed(() => selectedSystem.value?.subsystems ?? [])

const findPath = (subsystemId) => {
  if (subsystemId === null || subsystemId === undefined || subsystemId === '') return null

  for (const group of props.categories) {
    for (const system of group.systems ?? []) {
      const subsystem = (system.subsystems ?? []).find(
        (item) => String(item.id) === String(subsystemId),
      )
      if (subsystem) return { group, system, subsystem }
    }
  }

  return null
}

const isCurrentPathNode = (node, level) => {
  const path = findPath(props.modelValue)
  return path ? String(path[level]?.id) === String(node.id) : false
}
const optionLabel = (option) => `${option.name}${option.is_active === false ? ' (nonaktif)' : ''}`
const optionDisabled = (option, level) => option.is_active === false && !isCurrentPathNode(option, level)

const syncFromModel = (value) => {
  if (value === null || value === undefined || value === '') {
    selectedSubsystemId.value = ''
    return
  }

  const path = findPath(value)
  if (!path) {
    selectedGroupId.value = ''
    selectedSystemId.value = ''
    selectedSubsystemId.value = ''
    emit('update:modelValue', null)
    return
  }

  selectedGroupId.value = String(path.group.id)
  selectedSystemId.value = String(path.system.id)
  selectedSubsystemId.value = String(path.subsystem.id)
}

watch(
  () => [props.modelValue, props.categories],
  ([value]) => syncFromModel(value),
  { immediate: true, deep: true },
)

const onGroupChange = () => {
  selectedSystemId.value = ''
  selectedSubsystemId.value = ''
  emit('update:modelValue', null)
}

const onSystemChange = () => {
  selectedSubsystemId.value = ''
  emit('update:modelValue', null)
}

const onSubsystemChange = () => {
  const subsystem = subsystems.value.find(
    (item) => String(item.id) === String(selectedSubsystemId.value),
  )

  if (!subsystem || optionDisabled(subsystem, 'subsystem')) {
    selectedSubsystemId.value = ''
    emit('update:modelValue', null)
    return
  }

  emit('update:modelValue', subsystem.id)
}

const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
</script>

<template>
  <div class="grid gap-6 md:grid-cols-3">
    <div>
      <label for="asset-group-id" class="mb-2 block text-sm font-medium text-slate-800">Aset Prasarana Sintel</label>
      <select
        id="asset-group-id"
        v-model="selectedGroupId"
        name="asset_group_id"
        :class="inputClass"
        @change="onGroupChange"
      >
        <option value="">Pilih aset prasarana</option>
        <option
          v-for="group in categories"
          :key="group.id"
          :value="String(group.id)"
          :disabled="optionDisabled(group, 'group')"
        >
          {{ optionLabel(group) }}
        </option>
      </select>
    </div>

    <div>
      <label for="asset-system-id" class="mb-2 block text-sm font-medium text-slate-800">System</label>
      <select
        id="asset-system-id"
        v-model="selectedSystemId"
        name="asset_system_id"
        :class="inputClass"
        :disabled="!selectedGroupId"
        @change="onSystemChange"
      >
        <option value="">Pilih system</option>
        <option
          v-for="system in systems"
          :key="system.id"
          :value="String(system.id)"
          :disabled="optionDisabled(system, 'system')"
        >
          {{ optionLabel(system) }}
        </option>
      </select>
    </div>

    <div>
      <label for="asset-subsystem-id" class="mb-2 block text-sm font-medium text-slate-800">Subsystem</label>
      <select
        id="asset-subsystem-id"
        v-model="selectedSubsystemId"
        name="asset_subsystem_id"
        :class="inputClass"
        :disabled="!selectedSystemId"
        :aria-invalid="errors.asset_subsystem_id ? 'true' : undefined"
        :aria-describedby="errors.asset_subsystem_id ? 'asset-subsystem-error' : undefined"
        required
        @change="onSubsystemChange"
      >
        <option value="">Pilih subsystem</option>
        <option
          v-for="subsystem in subsystems"
          :key="subsystem.id"
          :value="String(subsystem.id)"
          :disabled="optionDisabled(subsystem, 'subsystem')"
        >
          {{ optionLabel(subsystem) }}
        </option>
      </select>
      <p
        v-if="errors.asset_subsystem_id"
        id="asset-subsystem-error"
        class="mt-2 text-sm text-red-600"
        role="alert"
      >
        {{ errors.asset_subsystem_id }}
      </p>
    </div>
  </div>
</template>
