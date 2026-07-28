<script setup>
import { computed, ref, watch } from 'vue'
import { ChevronRight, Pencil, Plus, Power, Search, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  title: { type: String, required: true },
  level: { type: String, required: true },
  items: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
  searchLabel: { type: String, required: true },
  addLabel: { type: String, required: true },
  addDisabled: { type: Boolean, default: false },
  parentSelected: { type: Boolean, default: true },
  parentPrompt: { type: String, default: '' },
  emptyTitle: { type: String, required: true },
  emptyDescription: { type: String, required: true },
  canManage: { type: Boolean, default: false },
  disabledTitle: { type: String, default: '' },
  disabledDescription: { type: String, default: '' },
  selectable: { type: Boolean, default: true },
  scopeKey: { type: [String, Number], default: null },
})

defineEmits(['select', 'add', 'edit', 'toggle', 'delete'])

const query = ref('')
watch(() => props.scopeKey, () => { query.value = '' })
const visibleItems = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('id')
  return needle ? props.items.filter((item) => item.name.toLocaleLowerCase('id').includes(needle)) : props.items
})

const selectLabel = (item) => props.level === 'group'
  ? `Pilih kategori ${item.name}`
  : `Pilih ${props.level} ${item.name}`

const dependencyLabel = (item) => {
  const count = props.level === 'group' ? item.systems_count : props.level === 'system' ? item.subsystems_count : item.assets_count
  const noun = props.level === 'group' ? 'system' : props.level === 'system' ? 'subsystem' : 'aset'
  const aliases = item.aliases_count ? ` · ${item.aliases_count} alias sumber` : ''
  return `${count ?? 0} ${noun}${aliases}`
}
</script>

<template>
  <section class="flex min-h-[30rem] min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-[#D7E0EA] bg-white shadow-sm md:min-w-[20rem]" :aria-label="title">
    <header class="border-b border-slate-200 px-4 pb-4 pt-5">
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-[#171650]">{{ title }}</h2>
        <button
          v-if="canManage"
          type="button"
          class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold text-orange-700 outline-none transition hover:bg-orange-50 focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2 motion-reduce:transition-none disabled:cursor-not-allowed disabled:text-slate-400 disabled:hover:bg-transparent"
          :aria-label="addLabel"
          :aria-describedby="addDisabled && disabledTitle ? `category-${level}-disabled-reason` : undefined"
          :disabled="addDisabled"
          @click="$emit('add')"
        >
          <Plus :size="16" aria-hidden="true" />
          {{ addLabel }}
        </button>
      </div>
      <label class="relative mt-3 block">
        <span class="sr-only">{{ searchLabel }}</span>
        <Search :size="17" class="pointer-events-none absolute left-3 top-3 text-slate-400" aria-hidden="true" />
        <input
          v-model="query"
          type="search"
          :aria-label="searchLabel"
          :disabled="!parentSelected"
          class="h-11 w-full rounded-lg border border-slate-300 bg-slate-50 pl-10 pr-3 text-sm text-slate-800 outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10"
          placeholder="Cari nama…"
        />
      </label>
      <div v-if="canManage && addDisabled && parentSelected && disabledTitle" :id="`category-${level}-disabled-reason`" class="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2.5">
        <p class="text-xs font-semibold text-orange-900">{{ disabledTitle }}</p>
        <p class="mt-1 text-xs leading-5 text-orange-800">{{ disabledDescription }}</p>
      </div>
    </header>

    <div v-if="!parentSelected" class="flex flex-1 items-center justify-center px-6 text-center">
      <div>
        <p class="text-sm font-semibold text-slate-700">{{ parentPrompt }}</p>
        <p class="mt-2 text-xs leading-5 text-slate-500">Pilih dari panel sebelumnya untuk melanjutkan.</p>
      </div>
    </div>

    <div v-else class="flex-1 space-y-2 overflow-y-auto p-3">
      <article
        v-for="item in visibleItems"
        :key="item.id"
        class="rounded-lg border transition-colors motion-reduce:transition-none"
        :class="item.id === selectedId ? 'border-orange-300 bg-orange-50/70' : 'border-slate-200 bg-white hover:border-slate-300'"
      >
        <component
          :is="selectable ? 'button' : 'div'"
          :type="selectable ? 'button' : undefined"
          class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-3 text-left outline-none focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2"
          :aria-label="selectable ? selectLabel(item) : undefined"
          :aria-pressed="selectable ? item.id === selectedId : undefined"
          @click="selectable && $emit('select', item)"
        >
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-900">{{ item.name }}</span>
            <span class="mt-1 block text-xs text-slate-500">{{ dependencyLabel(item) }} · Urutan {{ item.sort_order }}</span>
          </span>
          <span class="rounded-full px-2 py-1 text-[11px] font-medium" :class="item.is_active ? 'bg-emerald-50 text-[#16865B]' : 'bg-slate-100 text-slate-600'">
            {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
          <ChevronRight v-if="selectable" :size="17" class="shrink-0 text-slate-400" aria-hidden="true" />
        </component>
        <div v-if="canManage" class="flex items-center gap-1 border-t border-slate-200/80 px-2 py-1.5">
          <button type="button" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg px-2 text-xs font-medium text-slate-600 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" :aria-label="`Ubah nama ${item.name}`" @click="$emit('edit', item)">
            <Pencil :size="15" aria-hidden="true" /> Ubah nama
          </button>
          <button type="button" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg px-2 text-xs font-medium text-slate-600 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" :aria-label="`${item.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${item.name}`" @click="$emit('toggle', item)">
            <Power :size="15" aria-hidden="true" /> {{ item.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
          </button>
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-red-50 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-[#171650]" :aria-label="`Hapus ${item.name}`" @click="$emit('delete', item)">
            <Trash2 :size="16" aria-hidden="true" />
          </button>
        </div>
      </article>

      <div v-if="!visibleItems.length" class="px-4 py-12 text-center">
        <p class="text-sm font-semibold text-slate-700">{{ query ? 'Tidak ada nama yang cocok' : emptyTitle }}</p>
        <p class="mt-2 text-xs leading-5 text-slate-500">{{ query ? 'Coba kata pencarian lain.' : emptyDescription }}</p>
      </div>
    </div>
  </section>
</template>
