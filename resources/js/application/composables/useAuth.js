import { ref, computed } from 'vue'
import { UserModel, ROLES } from '@/domain/models/user.model'

// Definisi Akun Dummy Lengkap
export const mockUsers = [
  new UserModel({ id: 1, username: 'admin_pusat', name: 'Admin Pusat', role: ROLES.PUSAT, unit_kerja_id: null }),
  new UserModel({ id: 2, username: 'admin_daop1', name: 'Petugas Daop 1', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP1' }),
  new UserModel({ id: 3, username: 'admin_daop2', name: 'Petugas Daop 2', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP2' }),
  new UserModel({ id: 4, username: 'admin_daop3', name: 'Petugas Daop 3', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP3' }),
  new UserModel({ id: 5, username: 'admin_daop4', name: 'Petugas Daop 4', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP4' }),
  new UserModel({ id: 6, username: 'admin_daop5', name: 'Petugas Daop 5', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP5' }),
  new UserModel({ id: 7, username: 'admin_daop6', name: 'Petugas Daop 6', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP6' }),
  new UserModel({ id: 8, username: 'admin_daop7', name: 'Petugas Daop 7', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP7' }),
  new UserModel({ id: 9, username: 'admin_daop8', name: 'Petugas Daop 8', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP8' }),
  new UserModel({ id: 10, username: 'admin_daop9', name: 'Petugas Daop 9', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DAOP9' }),
  new UserModel({ id: 11, username: 'admin_divre1', name: 'Petugas Divre 1', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DIVRE1' }),
  new UserModel({ id: 12, username: 'admin_divre2', name: 'Petugas Divre 2', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DIVRE2' }),
  new UserModel({ id: 13, username: 'admin_divre3', name: 'Petugas Divre 3', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DIVRE3' }),
  new UserModel({ id: 14, username: 'admin_divre4', name: 'Petugas Divre 4', role: ROLES.DAOP_DIVRE, unit_kerja_id: 'DIVRE4' })
]

// Global State
const currentUser = ref(mockUsers[0]) // Default login sebagai Pusat
const activeArea = ref(null) // null berarti Nasional (Pusat), atau string 'DAOP1', 'DIVRE1' dsb.

export function useAuth() {
  const switchUser = (userId) => {
    const user = mockUsers.find(u => u.id === userId)
    if (user) {
      currentUser.value = user
      // Reset active area when switching user
      activeArea.value = user.role === ROLES.PUSAT ? null : user.unit_kerja_id
    }
  }

  const setArea = (areaId) => {
    if (currentUser.value.role === ROLES.PUSAT) {
      activeArea.value = areaId
    }
  }

  // Get current effective area (if Pusat is viewing a specific Daop, return that Daop. Otherwise return their actual unit_kerja_id)
  const currentArea = computed(() => {
    if (currentUser.value.role === ROLES.PUSAT) {
      return activeArea.value // can be null (Nasional) or specific Daop/Divre
    }
    return currentUser.value.unit_kerja_id
  })

  return {
    currentUser,
    mockUsers,
    switchUser,
    activeArea,
    setArea,
    currentArea
  }
}
