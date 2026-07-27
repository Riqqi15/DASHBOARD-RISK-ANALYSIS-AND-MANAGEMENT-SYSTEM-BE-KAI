<template>
  <div class="flex h-screen overflow-hidden bg-slate-50 font-sans text-slate-800">
    <!-- Sidebar -->
    <aside
      :class="[
        'absolute left-0 top-0 z-9999 flex h-screen w-72 flex-col overflow-y-hidden bg-white border-r border-slate-200 duration-300 ease-linear lg:static lg:translate-x-0 w-[260px] flex-shrink-0',
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <!-- Sidebar Header -->
      <div class="flex items-center justify-between gap-2 px-6 h-[72px]">
        <Link href="/dashboard" class="flex items-center space-x-2">
          <img :src="logoKai" alt="Logo KAI" class="h-8 object-contain" />
          <span class="text-[10px] px-2 py-0.5 bg-[#EA580C] text-white font-bold rounded-sm tracking-widest">RAMS</span>
        </Link>
        <button class="block lg:hidden text-slate-400 hover:text-slate-600 transition" @click="isSidebarOpen = false">
          <XIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Sidebar Menu -->
      <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
        <nav class="px-4 mt-2">
          <div class="mb-6">
            <h3 class="mb-4 ml-4 text-[11px] font-semibold text-slate-400 tracking-wider uppercase">Menu Utama</h3>
            <ul class="flex flex-col gap-1">
              <li v-for="item in menuItems" :key="item.name">
                <Link
                  :href="item.to"
                  class="group relative flex items-center justify-between rounded-lg px-4 py-2.5 font-medium text-sm text-slate-500 duration-300 ease-in-out hover:bg-slate-50 hover:text-slate-700 transition-colors"
                  :class="{ 'bg-blue-50/60 text-[#1E3A8A] hover:bg-blue-50 hover:text-[#1E3A8A]': currentPath === item.to }"
                >
                  <div class="flex items-center gap-3">
                    <component :is="item.icon" class="w-5 h-5 stroke-[1.5]" :class="{ 'text-[#1E3A8A] stroke-2': currentPath === item.to }" />
                    {{ item.label }}
                  </div>
                </Link>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </aside>

    <!-- Content Area -->
    <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
      <!-- Header -->
      <header class="sticky top-0 z-999 flex w-full bg-white border-b border-slate-200">
        <div class="flex flex-grow items-center justify-between px-4 h-[72px] md:px-6 2xl:px-10">
          
          <!-- Left: Mobile Toggle & Search Bar -->
          <div class="flex items-center gap-4 w-full max-w-md">
            <button
              class="block rounded-lg border border-slate-200 bg-white p-1.5 shadow-sm lg:hidden hover:bg-slate-50 transition"
              @click.stop="isSidebarOpen = !isSidebarOpen"
            >
              <MenuIcon class="w-5 h-5 text-slate-600" />
            </button>
            
            <!-- Search Bar Mockup -->
            <div class="hidden sm:flex items-center gap-2 px-3 py-2 w-full max-w-[320px] rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-400 focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all">
              <SearchIcon class="w-4 h-4 shrink-0" />
              <input type="text" placeholder="Search or type command..." class="bg-transparent border-none outline-none w-full placeholder-slate-400 text-slate-700" />
            </div>
          </div>

          <!-- Right: Actions & User -->
          <div class="flex items-center gap-3">
            <button class="p-2 text-slate-400 hover:text-slate-700 transition rounded-full hover:bg-slate-50">
              <MoonIcon class="w-5 h-5 stroke-[1.5]" />
            </button>
            
            <button class="relative p-2 text-slate-400 hover:text-slate-700 transition rounded-full hover:bg-slate-50">
              <BellIcon class="w-5 h-5 stroke-[1.5]" />
              <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
            </button>

            <div class="h-8 w-px bg-slate-200 mx-2"></div>

            <!-- User Area -->
            <div class="relative outline-none" tabindex="0" @blur="setTimeout(() => isDropdownOpen = false, 200)">
              <div class="flex items-center gap-3 cursor-pointer group" @click="isDropdownOpen = !isDropdownOpen">
                <div class="hidden text-right lg:block">
                  <span class="block text-sm font-semibold text-slate-700 group-hover:text-[#1E3A8A] transition">{{ currentUser.name }}</span>
                  <span class="block text-[11px] font-medium text-slate-500">{{ currentUser.role }}</span>
                </div>
                <!-- Simulated Avatar Image -->
                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold border border-blue-200 overflow-hidden">
                  <img :src="`https://ui-avatars.com/api/?name=${currentUser.name.replace(' ', '+')}&background=DBEAFE&color=1D4ED8`" alt="User" class="w-full h-full object-cover" />
                </div>
                <ChevronDownIcon class="w-4 h-4 text-slate-400 transition-transform" :class="{'rotate-180': isDropdownOpen}" />
              </div>

              <!-- Dropdown Panel -->
              <div v-show="isDropdownOpen" class="absolute right-0 mt-3 flex w-56 flex-col rounded-xl border border-slate-200 bg-white shadow-lg z-50">
                <div class="px-4 py-3 border-b border-slate-100">
                  <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Ganti Akun Dummy</span>
                  <ul class="flex flex-col gap-1">
                    <li v-for="user in mockUsers" :key="user.id">
                      <button 
                        @click="switchUser(user.id); isDropdownOpen = false" 
                        class="flex items-center gap-2 text-sm font-medium w-full text-left px-3 py-2 rounded-lg transition-colors"
                        :class="currentUser.id === user.id ? 'bg-blue-50 text-[#1E3A8A]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#1E3A8A]'"
                      >
                        {{ user.name }}
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <main>
        <div class="mx-auto max-w-screen-2xl p-4 md:p-6 2xl:p-10">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useAuth } from '@/application/composables/useAuth'
import logoKai from '../assets/logo-kai.png'
import { 
  LayoutDashboardIcon, 
  DatabaseIcon, 
  AlertTriangleIcon, 
  ActivityIcon, 
  PackageIcon, 
  RefreshCcwIcon,
  MenuIcon,
  XIcon,
  SearchIcon,
  BellIcon,
  MoonIcon,
  ChevronDownIcon
} from 'lucide-vue-next'

const { currentUser, mockUsers, switchUser } = useAuth()
const isSidebarOpen = ref(false)
const isDropdownOpen = ref(false)
const page = usePage()
const currentPath = computed(() => page.url.split('?')[0])

const menuItems = ref([
  { name: 'dashboard', label: 'Dashboard', to: '/dashboard', icon: LayoutDashboardIcon },
  { name: 'overview', label: 'Executive Overview', to: '/overview', icon: ActivityIcon },
  { name: 'master-asset', label: 'Master Aset', to: '/master-asset', icon: DatabaseIcon },
  { name: 'risk-matrix', label: 'Risk Matrix & LxC', to: '/risk-matrix', icon: AlertTriangleIcon },
  { name: 'inventory', label: 'Predictive Inventory', to: '/inventory', icon: PackageIcon },
  { name: 'reorder-stock', label: 'Reorder Stock', to: '/reorder-stock', icon: RefreshCcwIcon },
])
</script>
