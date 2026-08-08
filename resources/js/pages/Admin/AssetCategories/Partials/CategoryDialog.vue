<script setup>
import { X } from 'lucide-vue-next'
import AccessibleDialog from './AccessibleDialog.vue'

const props = defineProps({
  title: { type: String, required: true },
  levelLabel: { type: String, required: true },
  description: { type: String, required: true },
  form: { type: Object, required: true },
})

defineEmits(['close', 'submit'])

</script>

<template>
  <AccessibleDialog labelledby="category-dialog-title" describedby="category-dialog-description" :processing="form.processing" panel-class="w-full max-w-lg" @close="$emit('close')">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
          <div>
            <h2 id="category-dialog-title" class="text-lg font-semibold text-slate-950">{{ title }}</h2>
            <p id="category-dialog-description" class="mt-1 text-sm leading-6 text-slate-600">{{ description }}</p>
          </div>
          <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 outline-none hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#171650]" aria-label="Tutup dialog" :disabled="form.processing" @click="$emit('close')">
            <X :size="19" aria-hidden="true" />
          </button>
        </div>

        <form class="space-y-5 p-6" @submit.prevent="$emit('submit')">
          <div v-if="form.errors.asset_group_id || form.errors.asset_system_id" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-700">
            {{ form.errors.asset_group_id || form.errors.asset_system_id }}
            Pilih {{ form.errors.asset_system_id ? 'system' : 'kategori' }} aktif lalu coba lagi.
          </div>
          <div>
            <label for="category-name" class="mb-2 block text-sm font-medium text-slate-800">Nama {{ levelLabel }}</label>
            <input
              id="category-name"
              v-model="form.name"
              data-dialog-initial-focus
              autofocus
              required
              maxlength="255"
              :disabled="form.processing"
              :aria-invalid="Boolean(form.errors.name || form.errors.normalized_name)"
              :aria-describedby="form.errors.name || form.errors.normalized_name ? 'category-name-error' : undefined"
              class="h-11 w-full rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10 disabled:bg-slate-100"
            />
            <p v-if="form.errors.name || form.errors.normalized_name" id="category-name-error" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.name || form.errors.normalized_name }}</p>
          </div>

          <div>
            <label for="category-sort-order" class="mb-2 block text-sm font-medium text-slate-800">Urutan tampilan</label>
            <input
              id="category-sort-order"
              v-model.number="form.sort_order"
              type="number"
              min="0"
              max="65535"
              required
              :disabled="form.processing"
              :aria-invalid="Boolean(form.errors.sort_order)"
              :aria-describedby="form.errors.sort_order ? 'category-sort-error' : 'category-sort-help'"
              class="h-11 w-full rounded-lg border border-slate-300 px-3.5 text-sm outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10 disabled:bg-slate-100"
            />
            <p id="category-sort-help" class="mt-2 text-xs leading-5 text-slate-500">Angka kecil ditampilkan lebih dahulu.</p>
            <p v-if="form.errors.sort_order" id="category-sort-error" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.sort_order }}</p>
          </div>

          <div>
            <div class="mb-2 flex items-center justify-between gap-3">
              <label for="category-dashboard-color" class="text-sm font-medium text-slate-800">Warna aset di dashboard</label>
              <span class="text-xs text-slate-500">Opsional</span>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
              <input
                id="category-dashboard-color"
                type="color"
                :value="form.dashboard_color || '#171650'"
                :disabled="form.processing"
                class="h-11 w-14 cursor-pointer rounded-md border border-slate-300 bg-white p-1 disabled:cursor-not-allowed"
                aria-label="Pilih warna dashboard"
                @input="form.dashboard_color = $event.target.value.toUpperCase()"
              />
              <input
                v-model="form.dashboard_color"
                type="text"
                maxlength="7"
                placeholder="#FF0000"
                :disabled="form.processing"
                :aria-invalid="Boolean(form.errors.dashboard_color)"
                class="h-11 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 font-mono text-sm uppercase outline-none focus:border-[#171650] focus:ring-4 focus:ring-[#171650]/10 disabled:bg-slate-100"
              />
              <button type="button" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-100" :disabled="form.processing || !form.dashboard_color" @click="form.dashboard_color = null">Reset</button>
            </div>
            <p class="mt-2 text-xs leading-5 text-slate-500">Warna manual tidak akan ditimpa saat workbook diimpor ulang. Reset untuk memakai warna Excel pada import berikutnya.</p>
            <p v-if="form.errors.dashboard_color" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.dashboard_color }}</p>
          </div>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <button type="button" class="h-11 rounded-lg border border-slate-300 px-5 text-sm font-medium text-slate-700 outline-none hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#171650] disabled:opacity-60" :disabled="form.processing" @click="$emit('close')">Batal</button>
            <button type="submit" class="h-11 rounded-lg bg-[#F15A24] px-5 text-sm font-semibold text-white outline-none hover:bg-orange-700 focus-visible:ring-2 focus-visible:ring-[#171650] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
              {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
            </button>
          </div>
        </form>
  </AccessibleDialog>
</template>
