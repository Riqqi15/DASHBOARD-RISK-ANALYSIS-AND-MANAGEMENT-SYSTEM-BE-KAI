import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ROLES, UserModel } from '@/domain/models/user.model'

const activeArea = ref(null)

const divreNumbers = { I: '1', II: '2', III: '3', IV: '4' }

export function normalizeUnitCode(code) {
  if (!code) return null

  const daop = code.match(/^DAOP-(\d+)$/)
  if (daop) return `DAOP${daop[1]}`

  const divre = code.match(/^DIVRE-(I|II|III|IV)$/)
  if (divre) return `DIVRE${divreNumbers[divre[1]]}`

  return code.replaceAll('-', '')
}

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
      unit_kerja_id: normalizeUnitCode(user.unit_kerja?.code),
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
