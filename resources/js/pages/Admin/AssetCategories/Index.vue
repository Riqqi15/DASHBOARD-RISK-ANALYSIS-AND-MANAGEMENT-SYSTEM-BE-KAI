<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ArrowLeft, ChevronRight } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import CategoryPanel from './Partials/CategoryPanel.vue'
import CategoryDialog from './Partials/CategoryDialog.vue'
import DeactivateCategoryDialog from './Partials/DeactivateCategoryDialog.vue'
import DeleteCategoryDialog from './Partials/DeleteCategoryDialog.vue'

const props = defineProps({
  groups: { type: Array, required: true },
  selectedGroupId: { type: Number, default: null },
  selectedSystemId: { type: Number, default: null },
  capabilities: { type: Object, required: true },
})

const activeGroupId = ref(props.selectedGroupId)
const activeSystemId = ref(props.selectedSystemId)
const categoryDialog = ref(null)
const categoryForm = ref(null)
const statusDialog = ref(null)
const statusForm = ref(null)
const deleteDialog = ref(null)
const deleteForm = ref(null)
const mobileLevel = ref('group')

watch(() => props.selectedGroupId, (value) => { activeGroupId.value = value })
watch(() => props.selectedSystemId, (value) => { activeSystemId.value = value })

const selectedGroup = computed(() => props.groups.find((group) => group.id === activeGroupId.value) ?? null)
const systems = computed(() => selectedGroup.value?.systems ?? [])
const selectedSystem = computed(() => systems.value.find((system) => system.id === activeSystemId.value) ?? null)
const subsystems = computed(() => selectedSystem.value?.subsystems ?? [])
const canManage = computed(() => props.capabilities.manage === true)

watch(canManage, (value) => {
  if (value) return
  categoryDialog.value = null
  categoryForm.value = null
  statusDialog.value = null
  statusForm.value = null
  deleteDialog.value = null
  deleteForm.value = null
})

const visitSelection = () => router.get('/admin/asset-categories', {
  group: activeGroupId.value,
  system: activeSystemId.value,
}, { preserveState: true, preserveScroll: true, replace: true })

const selectGroup = (group) => {
  activeGroupId.value = group.id
  activeSystemId.value = group.systems[0]?.id ?? null
  mobileLevel.value = 'system'
  visitSelection()
}

const selectSystem = (system) => {
  activeSystemId.value = system.id
  mobileLevel.value = 'subsystem'
  visitSelection()
}

const levelDetails = {
  group: { label: 'kategori', endpoint: '/admin/asset-groups' },
  system: { label: 'system', endpoint: '/admin/asset-systems' },
  subsystem: { label: 'subsystem', endpoint: '/admin/asset-subsystems' },
}

const openCreate = (level) => {
  if (!canManage.value) return
  const data = { name: '', sort_order: 0 }
  if (level === 'system') data.asset_group_id = activeGroupId.value
  if (level === 'subsystem') data.asset_system_id = activeSystemId.value
  categoryForm.value = useForm(data)
  categoryDialog.value = { mode: 'create', level }
}

const openEdit = (level, category) => {
  if (!canManage.value) return
  categoryForm.value = useForm({ name: category.name, sort_order: category.sort_order })
  categoryDialog.value = { mode: 'edit', level, category }
}

const closeCategoryDialog = () => {
  if (!categoryForm.value?.processing) categoryDialog.value = null
}

const submitCategory = () => {
  if (!canManage.value) return
  const details = levelDetails[categoryDialog.value.level]
  const method = categoryDialog.value.mode === 'edit' ? 'put' : 'post'
  const endpoint = categoryDialog.value.mode === 'edit' ? `${details.endpoint}/${categoryDialog.value.category.id}` : details.endpoint
  categoryForm.value[method](endpoint, {
    preserveScroll: true,
    onSuccess: closeCategoryDialog,
  })
}

const openStatus = (level, category) => {
  if (!canManage.value) return
  statusForm.value = useForm({ is_active: !category.is_active })
  statusDialog.value = { level, category, activate: !category.is_active }
}

const submitStatus = () => {
  if (!canManage.value) return
  const endpoint = `${levelDetails[statusDialog.value.level].endpoint}/${statusDialog.value.category.id}/status`
  statusForm.value.patch(endpoint, {
    preserveScroll: true,
    onSuccess: () => { statusDialog.value = null },
  })
}

const openDelete = (level, category) => {
  if (!canManage.value) return
  deleteForm.value = useForm({})
  deleteDialog.value = { level, category }
}

const submitDelete = () => {
  if (!canManage.value) return
  const endpoint = `${levelDetails[deleteDialog.value.level].endpoint}/${deleteDialog.value.category.id}`
  deleteForm.value.delete(endpoint, {
    preserveScroll: true,
    onSuccess: () => { deleteDialog.value = null },
  })
}
</script>

<template>
  <Head title="Kategori Aset" />
  <MainLayout>
    <div class="mb-6">
      <p class="text-sm font-medium text-orange-600">Taksonomi aset global</p>
      <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Kategori Aset</h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Jaga susunan kategori aset yang digunakan bersama oleh seluruh Daop dan Divre.</p>
    </div>

    <nav aria-label="Jalur kategori" class="mb-4 flex min-h-11 items-center gap-1 overflow-x-auto rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-600 md:hidden">
      <button
        v-if="mobileLevel !== 'group'"
        type="button"
        class="inline-flex min-h-11 shrink-0 items-center gap-1 rounded-md px-2 font-medium text-[#171650] outline-none focus-visible:ring-2 focus-visible:ring-[#171650]"
        aria-label="Kembali ke kategori"
        @click="mobileLevel = 'group'"
      >
        <ArrowLeft :size="15" aria-hidden="true" /> Kategori
      </button>
      <span v-else class="px-2 font-semibold text-[#171650]">Kategori</span>
      <template v-if="mobileLevel !== 'group' && selectedGroup">
        <ChevronRight :size="14" class="shrink-0 text-slate-400" aria-hidden="true" />
        <button
          v-if="mobileLevel === 'subsystem'"
          type="button"
          class="min-h-11 max-w-48 truncate rounded-md px-2 font-medium text-[#171650] outline-none focus-visible:ring-2 focus-visible:ring-[#171650]"
          aria-label="Kembali ke system"
          @click="mobileLevel = 'system'"
        >{{ selectedGroup.name }}</button>
        <span v-else class="max-w-48 truncate px-2 font-semibold text-slate-800">{{ selectedGroup.name }}</span>
      </template>
      <template v-if="mobileLevel === 'subsystem' && selectedSystem">
        <ChevronRight :size="14" class="shrink-0 text-slate-400" aria-hidden="true" />
        <span class="max-w-48 truncate px-2 font-semibold text-slate-800">{{ selectedSystem.name }}</span>
      </template>
    </nav>

    <div class="relative overflow-x-auto pb-3">
      <div class="pointer-events-none absolute left-[30%] right-[30%] top-9 hidden h-px bg-[#D7E0EA] xl:block" aria-hidden="true" />
      <div class="relative md:flex md:min-w-max md:gap-4 xl:grid xl:min-w-0 xl:grid-cols-3">
        <div data-level="group" :data-mobile-active="mobileLevel === 'group'" :class="mobileLevel === 'group' ? 'block' : 'hidden'" class="md:flex md:min-w-[20rem] md:flex-1">
          <CategoryPanel
            title="Kategori"
            level="group"
            :items="groups"
            :selected-id="activeGroupId"
            search-label="Cari kategori"
            add-label="Tambah kategori"
            empty-title="Belum ada kategori aset"
            empty-description="Tambahkan kategori pertama untuk memulai susunan aset global."
            :can-manage="canManage"
            @select="selectGroup"
            @add="openCreate('group')"
            @edit="openEdit('group', $event)"
            @toggle="openStatus('group', $event)"
            @delete="openDelete('group', $event)"
          />
        </div>
        <div data-level="system" :data-mobile-active="mobileLevel === 'system'" :class="mobileLevel === 'system' ? 'block' : 'hidden'" class="md:flex md:min-w-[20rem] md:flex-1">
          <CategoryPanel
            title="System"
            level="system"
            :items="systems"
            :selected-id="activeSystemId"
            search-label="Cari system"
            add-label="Tambah system"
            :add-disabled="!selectedGroup?.is_active"
            :parent-selected="Boolean(selectedGroup)"
            parent-prompt="Pilih kategori terlebih dahulu"
            empty-title="Belum ada system"
            empty-description="Tambahkan system pertama di bawah kategori ini."
            :can-manage="canManage"
            disabled-title="Kategori ini nonaktif"
            disabled-description="Aktifkan kategori untuk menambah system."
            @select="selectSystem"
            @add="openCreate('system')"
            @edit="openEdit('system', $event)"
            @toggle="openStatus('system', $event)"
            @delete="openDelete('system', $event)"
          />
        </div>
        <div data-level="subsystem" :data-mobile-active="mobileLevel === 'subsystem'" :class="mobileLevel === 'subsystem' ? 'block' : 'hidden'" class="md:flex md:min-w-[20rem] md:flex-1">
          <CategoryPanel
            title="Subsystem"
            level="subsystem"
            :items="subsystems"
            search-label="Cari subsystem"
            add-label="Tambah subsystem"
            :add-disabled="!selectedSystem?.is_active"
            :parent-selected="Boolean(selectedSystem)"
            parent-prompt="Pilih system terlebih dahulu"
            empty-title="Belum ada subsystem"
            empty-description="Tambahkan subsystem pertama di bawah system ini."
            :can-manage="canManage"
            disabled-title="System ini nonaktif"
            disabled-description="Aktifkan system untuk menambah subsystem."
            @add="openCreate('subsystem')"
            @edit="openEdit('subsystem', $event)"
            @toggle="openStatus('subsystem', $event)"
            @delete="openDelete('subsystem', $event)"
          />
        </div>
      </div>
    </div>

    <CategoryDialog
      v-if="canManage && categoryDialog && categoryForm"
      :title="`${categoryDialog.mode === 'edit' ? 'Ubah' : 'Tambah'} ${levelDetails[categoryDialog.level].label}`"
      :level-label="levelDetails[categoryDialog.level].label"
      :description="categoryDialog.mode === 'edit' ? 'Perbarui nama dan urutan tanpa mengubah data yang sudah terhubung.' : categoryDialog.level === 'group' ? 'Kategori baru tersedia untuk seluruh wilayah.' : `Tambahkan di bawah ${categoryDialog.level === 'system' ? selectedGroup?.name : selectedSystem?.name}.`"
      :form="categoryForm"
      @close="closeCategoryDialog"
      @submit="submitCategory"
    />
    <DeactivateCategoryDialog
      v-if="canManage && statusDialog && statusForm"
      :category="statusDialog.category"
      :activate="statusDialog.activate"
      :processing="statusForm.processing"
      @close="statusDialog = null"
      @confirm="submitStatus"
    />
    <DeleteCategoryDialog
      v-if="canManage && deleteDialog && deleteForm"
      :category="deleteDialog.category"
      :form="deleteForm"
      @close="deleteDialog = null"
      @confirm="submitDelete"
    />
  </MainLayout>
</template>
