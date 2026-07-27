<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  Activity,
  AlertTriangle,
  Building2,
  UsersRound,
  ScrollText,
  ChevronDown,
  Database,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  RefreshCcw,
  Search,
  ShieldCheck,
  X,
} from 'lucide-vue-next'
import FlashMessage from '@/components/feedback/FlashMessage.vue'
import logoKai from '../assets/logo-kai.png'

const page = usePage()
const isSidebarOpen = ref(false)
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

const menuItems = [
  { name: 'dashboard', label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard },
  { name: 'overview', label: 'Executive Overview', to: '/overview', icon: Activity },
  { name: 'master-asset', label: 'Master Aset', to: '/master-asset', icon: Database },
  { name: 'risk-matrix', label: 'Matriks Risiko', to: '/risk-matrix', icon: AlertTriangle },
  { name: 'inventory', label: 'Predictive Inventory', to: '/inventory', icon: Package },
  { name: 'reorder-stock', label: 'Reorder Stock', to: '/reorder-stock', icon: RefreshCcw },
]

const adminMenuItems = [
  { name: 'admin-units', label: 'Unit Kerja', to: '/admin/units', icon: Building2 },
  { name: 'admin-accounts', label: 'Akun Wilayah', to: '/admin/accounts', icon: UsersRound },
  { name: 'admin-audit', label: 'Audit Log', to: '/admin/audit-logs', icon: ScrollText },
]

const activeMenu = computed(() => [...menuItems, ...adminMenuItems]
  .find((item) => currentPath.value.startsWith(item.to)) ?? menuItems[0])

const closeSidebar = () => {
  isSidebarOpen.value = false
}
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
      class="fixed inset-y-0 left-0 z-50 flex w-[272px] flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
      :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      aria-label="Navigasi utama"
    >
      <div class="flex h-[76px] items-center justify-between border-b border-slate-100 px-5">
        <Link href="/dashboard" class="flex items-center gap-3" @click="closeSidebar">
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
          <li v-for="item in menuItems" :key="item.name">
            <Link
              :href="item.to"
              class="group flex min-h-11 items-center gap-3 rounded-lg border-l-[3px] px-3 text-sm font-medium transition"
              :class="currentPath === item.to
                ? 'border-orange-500 bg-orange-50 text-[#171650]'
                : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
              @click="closeSidebar"
            >
              <component :is="item.icon" :size="19" :stroke-width="1.8" :class="currentPath === item.to ? 'text-orange-600' : 'text-slate-400 group-hover:text-slate-600'" aria-hidden="true" />
              {{ item.label }}
            </Link>
          </li>
        </ul>

        <template v-if="isPusat">
          <p class="mb-2 mt-7 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Administrasi</p>
          <ul class="space-y-1">
            <li v-for="item in adminMenuItems" :key="item.name">
              <Link
                :href="item.to"
                class="group flex min-h-11 items-center gap-3 rounded-lg border-l-[3px] px-3 text-sm font-medium transition"
                :class="currentPath.startsWith(item.to)
                  ? 'border-orange-500 bg-orange-50 text-[#171650]'
                  : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                @click="closeSidebar"
              >
                <component :is="item.icon" :size="19" :stroke-width="1.8" :class="currentPath.startsWith(item.to) ? 'text-orange-600' : 'text-slate-400 group-hover:text-slate-600'" aria-hidden="true" />
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

    <div class="min-h-screen lg:pl-[272px]">
      <header class="sticky top-0 z-30 h-[76px] border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex h-full items-center justify-between gap-4 px-4 sm:px-6 xl:px-8">
          <div class="flex min-w-0 items-center gap-3">
            <button type="button" class="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 lg:hidden" aria-label="Buka navigasi" @click="isSidebarOpen = true">
              <Menu :size="20" aria-hidden="true" />
            </button>
            <div class="min-w-0">
              <p class="text-xs font-medium text-slate-400">KAI RAMS / Ruang kerja</p>
              <h1 class="truncate text-base font-semibold text-slate-950">{{ activeMenu.label }}</h1>
            </div>
          </div>

          <div class="flex items-center gap-2 sm:gap-4">
            <div class="hidden h-10 w-64 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 xl:flex">
              <Search :size="17" class="text-slate-400" aria-hidden="true" />
              <input type="search" placeholder="Cari data…" class="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none placeholder:text-slate-400" aria-label="Cari data" />
            </div>

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

      <main class="mx-auto max-w-[1600px] p-4 sm:p-6 xl:p-8">
        <FlashMessage :success="flash.success" :error="flash.error" />
        <slot />
      </main>
    </div>
  </div>
</template>
