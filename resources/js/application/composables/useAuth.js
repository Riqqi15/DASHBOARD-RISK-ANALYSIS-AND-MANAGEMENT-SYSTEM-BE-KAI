import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ROLES, UserModel } from '@/domain/models/user.model'

const activeArea = ref(null)

export function useAuth() {
  const page = usePage()

  const currentUser = computed(() => {
    const user = page.props.auth?.user

    if (!user) {
      return new UserModel({ id: null, username: '', name: 'Pengguna', role: ROLES.DAOP_DIVRE })
    }

    return new UserModel({
      id: user.id,
      username: user.email,
      name: user.name,
      role: user.role === 'pusat' ? ROLES.PUSAT : ROLES.DAOP_DIVRE,
      unit_kerja_id: user.unit_kerja?.code ?? null,
    })
  })

  const setArea = (areaId) => {
    if (currentUser.value.isPusat()) {
      activeArea.value = areaId
    }
  }

  const currentArea = computed(() => currentUser.value.isPusat()
    ? activeArea.value
    : currentUser.value.unit_kerja_id)

  return {
    currentUser,
    activeArea,
    setArea,
    currentArea,
  }
}
