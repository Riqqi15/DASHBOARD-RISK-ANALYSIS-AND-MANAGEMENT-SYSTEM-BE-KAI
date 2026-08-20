<script setup>
import { computed, ref, watch } from 'vue'
import { ChevronRight, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  title: { type: String, required: true },
  position: { type: Number, required: true },
  items: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
  canManage: { type: Boolean, default: false },
  canDeleteLevel: { type: Boolean, default: false },
  parentSelected: { type: Boolean, default: true },
  parentName: { type: String, default: '' },
  scopeKey: { type: [String, Number], default: null },
})

defineEmits(['select', 'add', 'edit', 'delete', 'delete-level'])

const query = ref('')
watch(() => props.scopeKey, () => { query.value = '' })
const visibleItems = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('id')
  return needle ? props.items.filter((item) => item.name.toLocaleLowerCase('id').includes(needle)) : props.items
})
</script>

<template>
  <section class="flex h-[30rem] w-[20rem] shrink-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white" :aria-label="title">
    <header class="border-b border-slate-200 px-4 py-4">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-xs font-medium text-slate-500">Level {{ position }}</p>
          <h2 class="mt-0.5 truncate text-sm font-semibold text-[#171650]">{{ title }}</h2>
        </div>
        <div v-if="canManage" class="flex shrink-0 items-center gap-1">
          <button
            v-if="canDeleteLevel"
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-red-50 hover:text-red-700 focus-visible:ring-2 focus-visible:ring-[#171650]"
            :aria-label="`Hapus level ${title}`"
            :title="`Hapus level ${title}`"
            @click="$emit('delete-level')"
          >
            <Trash2 :size="17" aria-hidden="true" />
          </button>
          <button
            type="button"
            class="inline-flex h-11 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold text-orange-700 outline-none hover:bg-orange-50 focus-visible:ring-2 focus-visible:ring-[#171650] disabled:cursor-not-allowed disabled:text-slate-400"
            :aria-label="`Tambah ${title}`"
            :disabled="!parentSelected"
            @click="$emit('add')"
          >
            <Plus :size="16" aria-hidden="true" /> Tambah
          </button>
        </div>
      </div>

      <label class="relative mt-3 block">
        <span class="sr-only">Cari {{ title }}</span>
        <Search :size="17" class="pointer-events-none absolute left-3 top-3 text-slate-400" aria-hidden="true" />
        <input
          v-model="query"
          type="search"
          :disabled="!parentSelected"
          :aria-label="`Cari ${title}`"
          class="h-11 w-full rounded-lg border border-slate-300 bg-slate-50 pl-10 pr-3 text-sm text-slate-800 outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10 disabled:cursor-not-allowed disabled:bg-slate-100"
          placeholder="Cari nama…"
        />
      </label>
    </header>

    <div v-if="!parentSelected" class="flex flex-1 items-center justify-center px-6 text-center">
      <div>
        <p class="text-sm font-semibold text-slate-700">Pilih Level {{ position - 1 }}</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">Pilih kategori pada kolom sebelumnya untuk membuka {{ title }}.</p>
      </div>
    </div>

    <div v-else class="flex-1 space-y-2 overflow-y-auto p-3">
      <article
        v-for="item in visibleItems"
        :key="item.id"
        class="overflow-hidden rounded-lg border"
        :class="item.id === selectedId ? 'border-orange-300 bg-orange-50' : 'border-slate-200 bg-white hover:border-slate-300'"
      >
        <button
          type="button"
          class="flex min-h-14 w-full items-center gap-3 px-3 py-2.5 text-left outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#171650]"
          :aria-label="`Pilih ${title} ${item.name}`"
          :aria-pressed="item.id === selectedId"
          @click="$emit('select', item)"
        >
          <span v-if="item.dashboard_color" class="h-8 w-1 shrink-0 rounded-full" :style="{ backgroundColor: item.dashboard_color }" aria-hidden="true" />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-900">{{ item.name }}</span>
            <span class="mt-1 block text-xs text-slate-500">{{ item.subtree_assets_count ?? 0 }} aset wilayah · {{ item.subtree_units_count ?? 0 }} unit</span>
          </span>
          <span v-if="!item.is_active" class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">Nonaktif</span>
          <ChevronRight :size="17" class="shrink-0 text-slate-400" aria-hidden="true" />
        </button>
        <div v-if="canManage" class="flex border-t border-slate-200/80 px-2 py-1">
          <button type="button" class="flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg text-xs font-medium text-slate-600 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" :aria-label="`Ubah ${item.name}`" @click="$emit('edit', item)">
            <Pencil :size="15" aria-hidden="true" /> Ubah
          </button>
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-red-50 hover:text-red-700 focus-visible:ring-2 focus-visible:ring-[#171650]" :aria-label="`Hapus kategori ${item.name}`" @click="$emit('delete', item)">
            <Trash2 :size="16" aria-hidden="true" />
          </button>
        </div>
      </article>

      <div v-if="!visibleItems.length" class="px-4 py-12 text-center">
        <p class="text-sm font-semibold text-slate-700">{{ query ? 'Nama tidak ditemukan' : `Belum ada ${title}` }}</p>
        <p class="mt-2 text-xs leading-5 text-slate-500">{{ query ? 'Coba kata pencarian lain.' : canManage ? `Tambahkan ${title} pertama pada jalur ini.` : 'Belum ada kategori pada jalur ini.' }}</p>
      </div>
    </div>
  </section>
</template>
