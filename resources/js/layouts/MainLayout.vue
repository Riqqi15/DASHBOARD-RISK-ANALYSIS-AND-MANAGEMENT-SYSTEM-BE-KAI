<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  AlertTriangle,
  Building2,
  ChevronDown,
  Database,
  FileSpreadsheet,
  LayoutDashboard,
  LogOut,
  Menu,
  Network,
  Package,
  PanelLeftClose,
  PanelLeftOpen,
  ShieldCheck,
  UploadCloud,
  X,
} from 'lucide-vue-next'
import FlashMessage from '@/components/feedback/FlashMessage.vue'
import logoKai from '../assets/logo-kai.png'

const page = usePage()
const isSidebarOpen = ref(false)
const isSidebarCollapsed = ref(false)
const isUserMenuOpen = ref(false)

const currentPath = computed(() => page.url.split('?')[0])
const user = computed(() => page.props.auth?.user ?? {})
const flash = computed(() => page.props.flash ?? {})
const isPusat = computed(() => user.value.role === 'pusat')
const userInitials = computed(() => (user.value.name || 'Pengguna')
  .split(' ')
  .slice(0, 2)
  .map((part) => part[0])
  .join('')
  .toUpperCase())
const roleLabel = computed(() => isPusat.value ? 'Kantor Pusat' : (user.value.unit_kerja?.code || 'Unit Kerja'))
const activeRamsUnit = computed(() => page.props.active_rams_unit ?? null)

const menuItems = [
  { name: 'dashboard', label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard },
  { name: 'master-asset', label: 'Master Aset', to: '/master-asset', icon: Database },
  { name: 'risk-matrix', label: 'Matriks Risiko', to: '/risk-matrix', icon: AlertTriangle },
  { name: 'risk-register', label: 'Risk Register', to: '/risk-register', icon: ShieldCheck },
  { name: 'inventory', label: 'Inventori Suku Cadang', to: '/inventory', icon: Package },
  { name: 'reports', label: 'Laporan RAMS', to: '/reports', icon: FileSpreadsheet },
  { name: 'trouble-report-import', label: 'Import Data RAMS', to: '/trouble-report/import', icon: UploadCloud },
]

const primaryMenuItems = menuItems.filter((item) => item.name === 'dashboard')
const moduleMenuItems = menuItems.filter((item) => !['dashboard', 'trouble-report-import'].includes(item.name))
const importMenuItems = menuItems.filter((item) => item.name === 'trouble-report-import')
const scopedHref = (item) => {
  const unit = activeRamsUnit.value
  if (!unit || item.name === 'trouble-report-import') return item.to

  const query = ['master-asset', 'inventory'].includes(item.name)
    ? `unit_kerja_id=${encodeURIComponent(unit.id)}`
    : `area=${encodeURIComponent(unit.code)}`

  return `${item.to}?${query}`
}
const isRamsModuleActive = computed(() => moduleMenuItems.some((item) => currentPath.value.startsWith(item.to)))
const isRamsMenuOpen = ref(isRamsModuleActive.value)

watch(isRamsModuleActive, (isActive) => {
  if (isActive) isRamsMenuOpen.value = true
})

const adminMenuItems = [
  { name: 'admin-asset-categories', label: 'Kategori Aset', to: '/admin/asset-categories', icon: Network },
  { name: 'admin-units', label: 'Unit & Akun', to: '/admin/units', icon: Building2 },
]

const visibleAdminMenuItems = computed(() => isPusat.value
  ? adminMenuItems
  : adminMenuItems.filter((item) => item.name === 'admin-asset-categories'))

const activeMenu = computed(() => {
  if (currentPath.value.startsWith('/admin/accounts')) {
    return adminMenuItems.find((item) => item.name === 'admin-units')
  }

  return [...menuItems, ...adminMenuItems]
    .find((item) => currentPath.value.startsWith(item.to)) ?? menuItems[0]
})

const closeSidebar = () => {
  isSidebarOpen.value = false
}

const toggleNavigation = () => {
  if (window.innerWidth < 1024) {
    isSidebarOpen.value = !isSidebarOpen.value
    return
  }

  isSidebarCollapsed.value = !isSidebarCollapsed.value
  window.localStorage.setItem('rams.sidebar-collapsed', String(isSidebarCollapsed.value))
}

const navigationToggleLabel = computed(() => {
  if (isSidebarCollapsed.value) return 'Tampilkan navigasi'
  if (isSidebarOpen.value) return 'Tutup navigasi'
  return 'Sembunyikan navigasi'
})

onMounted(() => {
  isSidebarCollapsed.value = window.localStorage.getItem('rams.sidebar-collapsed') === 'true'
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800">
    <div
      v-if="isSidebarOpen"
      class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[1px] lg:hidden"
      aria-hidden="true"
      @click="closeSidebar"
    />

    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-[272px] flex-col border-r border-slate-200 bg-white transition-transform duration-200"
      :class="[
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
        isSidebarCollapsed ? 'lg:-translate-x-full' : 'lg:translate-x-0',
      ]"
      aria-label="Navigasi utama"
    >
      <div class="flex h-[76px] items-center justify-between border-b border-slate-100 px-5">
        <Link :href="scopedHref(primaryMenuItems[0])" class="flex items-center gap-3" @click="closeSidebar">
          <img :src="logoKai" alt="Kereta Api Indonesia" class="h-8 w-auto object-contain" />
          <span class="h-7 w-px bg-slate-200" aria-hidden="true" />
          <span class="rounded bg-[#171650] px-2 py-1 text-[10px] font-semibold tracking-[0.16em] text-white">RAMS</span>
        </Link>
        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Tutup navigasi" @click="closeSidebar">
          <X :size="20" aria-hidden="true" />
        </button>
      </div>

      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex items-center gap-2 text-xs font-medium text-emerald-700">
          <span class="h-2 w-2 rounded-full bg-emerald-500 ring-4 ring-emerald-50" aria-hidden="true" />
          Sistem operasional
        </div>
        <p class="mt-2 text-xs leading-5 text-slate-500">Data kerja tersinkron dengan lingkungan {{ roleLabel }}.</p>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-5">
        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ruang kerja</p>
        <ul class="space-y-1">
          <li v-for="item in primaryMenuItems" :key="item.name">
            <Link
              :href="scopedHref(item)"
              class="group flex min-h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
              :class="currentPath.startsWith(item.to)
                ? 'bg-[#171650]/[0.06] font-semibold text-[#171650]'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
              @click="closeSidebar"
            >
              <component
                :is="item.icon"
                :size="19"
                :stroke-width="1.8"
                :class="currentPath.startsWith(item.to) ? 'text-[#171650]' : 'text-slate-400 group-hover:text-slate-600'"
                aria-hidden="true"
              />
              {{ item.label }}
            </Link>
          </li>

          <li>
            <button
              type="button"
              class="flex min-h-12 w-full items-center justify-between gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
              :class="isRamsModuleActive
                ? 'bg-[#171650]/[0.04] text-[#171650]'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
              :aria-expanded="isRamsMenuOpen"
              aria-controls="rams-module-menu"
              @click="isRamsMenuOpen = !isRamsMenuOpen"
            >
              <span class="flex items-center gap-3">
                <Database :size="19" :stroke-width="1.8" :class="isRamsModuleActive ? 'text-[#171650]' : 'text-slate-400'" aria-hidden="true" />
                Modul RAMS
              </span>
              <ChevronDown
                :size="16"
                class="text-slate-400 transition-transform duration-200"
                :class="isRamsMenuOpen ? 'rotate-180' : ''"
                aria-hidden="true"
              />
            </button>

            <ul id="rams-module-menu" v-show="isRamsMenuOpen" class="mt-1 space-y-1 pl-4">
              <li v-for="item in moduleMenuItems" :key="item.name">
                <Link
                  :href="scopedHref(item)"
                  class="group flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
                  :class="currentPath.startsWith(item.to)
                    ? 'bg-[#171650]/[0.06] font-semibold text-[#171650]'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                  @click="closeSidebar"
                >
                  <component
                    :is="item.icon"
                    :size="17"
                    :stroke-width="1.8"
                    :class="currentPath.startsWith(item.to) ? 'text-[#171650]' : 'text-slate-400 group-hover:text-slate-600'"
                    aria-hidden="true"
                  />
                  {{ item.label }}
                </Link>
              </li>
            </ul>
          </li>

          <li v-for="item in importMenuItems" :key="item.name">
            <Link
              :href="item.to"
              class="group flex min-h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
              :class="currentPath.startsWith(item.to)
                ? 'bg-[#171650]/[0.06] font-semibold text-[#171650]'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
              @click="closeSidebar"
            >
              <component
                :is="item.icon"
                :size="19"
                :stroke-width="1.8"
                :class="currentPath.startsWith(item.to) ? 'text-[#171650]' : 'text-slate-400 group-hover:text-slate-600'"
                aria-hidden="true"
              />
              {{ item.label }}
            </Link>
          </li>
        </ul>

        <template v-if="visibleAdminMenuItems.length">
          <p class="mb-2 mt-7 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Administrasi</p>
          <ul class="space-y-1">
            <li v-for="item in visibleAdminMenuItems" :key="item.name">
              <Link
                :href="item.to"
                class="group flex min-h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
                :class="currentPath.startsWith(item.to)
                  ? 'bg-[#171650]/[0.06] font-semibold text-[#171650]'
                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                @click="closeSidebar"
              >
                <component
                  :is="item.icon"
                  :size="19"
                  :stroke-width="1.8"
                  :class="currentPath.startsWith(item.to) ? 'text-[#171650]' : 'text-slate-400 group-hover:text-slate-600'"
                  aria-hidden="true"
                />
                {{ item.label }}
              </Link>
            </li>
          </ul>
        </template>
      </nav>

      <div class="border-t border-slate-100 p-4">
        <div class="rounded-xl bg-[#171650] p-4 text-white">
          <div class="flex items-center gap-2 text-xs font-semibold text-blue-100">
            <ShieldCheck :size="17" aria-hidden="true" />
            Akses {{ isPusat ? 'Pusat' : 'Wilayah' }}
          </div>
          <p class="mt-2 text-[11px] leading-5 text-blue-200/80">Hak akses diterapkan berdasarkan akun dan unit kerja Anda.</p>
        </div>
      </div>
    </aside>

    <div class="min-h-screen transition-[padding] duration-200" :class="isSidebarCollapsed ? 'lg:pl-0' : 'lg:pl-[272px]'">
      <header class="sticky top-0 z-30 h-[76px] border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex h-full items-center justify-between gap-4 px-4 sm:px-6 xl:px-8">
          <div class="flex min-w-0 items-center gap-3">
            <button
              type="button"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-700 transition hover:border-orange-300 hover:bg-orange-50 hover:text-[#171650] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orange-100"
              :aria-label="navigationToggleLabel"
              :title="navigationToggleLabel"
              @click="toggleNavigation"
            >
              <Menu class="lg:hidden" :size="21" aria-hidden="true" />
              <PanelLeftClose v-if="!isSidebarCollapsed" class="hidden lg:block" :size="21" aria-hidden="true" />
              <PanelLeftOpen v-else class="hidden lg:block" :size="21" aria-hidden="true" />
            </button>
            <div class="min-w-0">
              <p class="text-xs font-medium text-slate-400">KAI RAMS / Ruang kerja</p>
              <h1 class="truncate text-base font-semibold text-slate-950">{{ activeMenu.label }}</h1>
            </div>
          </div>

          <div class="flex items-center gap-2 sm:gap-4">
            <div class="relative">
              <button
                type="button"
                class="flex items-center gap-3 rounded-lg p-1.5 text-left hover:bg-slate-50"
                :aria-expanded="isUserMenuOpen"
                aria-haspopup="menu"
                @click="isUserMenuOpen = !isUserMenuOpen"
              >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#171650] text-xs font-semibold text-white">{{ userInitials }}</span>
                <span class="hidden max-w-40 sm:block">
                  <span class="block truncate text-sm font-semibold text-slate-800">{{ user.name }}</span>
                  <span class="block truncate text-[11px] text-slate-500">{{ roleLabel }}</span>
                </span>
                <ChevronDown :size="16" class="hidden text-slate-400 transition sm:block" :class="isUserMenuOpen ? 'rotate-180' : ''" aria-hidden="true" />
              </button>

              <div v-if="isUserMenuOpen" class="absolute right-0 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10" role="menu">
                <div class="border-b border-slate-100 px-4 py-3">
                  <p class="truncate text-sm font-semibold text-slate-900">{{ user.name }}</p>
                  <p class="mt-1 truncate text-xs text-slate-500">@{{ user.username }}</p>
                </div>
                <Link href="/logout" method="post" as="button" class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50" role="menuitem">
                  <LogOut :size="17" aria-hidden="true" />
                  Keluar dari sistem
                </Link>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="w-full p-4 sm:p-6 xl:p-8">
        <FlashMessage :success="flash.success" :error="flash.error" />
        <slot />
      </main>
    </div>
  </div>
</template>
