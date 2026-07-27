import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({
    props: {
      auth: {
        user: {
          id: 1,
          name: 'Admin Pusat',
          username: 'admin.pusat',
          email: 'admin.pusat@example.test',
          role: 'pusat',
          unit_kerja: null,
        },
      },
    },
  }),
}))

import { normalizeUnitCode, useAuth } from '@/application/composables/useAuth'

describe('normalizeUnitCode', () => {
  it.each([
    ['DAOP-1', 'DAOP1'],
    ['DAOP-9', 'DAOP9'],
    ['DIVRE-I', 'DIVRE1'],
    ['DIVRE-IV', 'DIVRE4'],
  ])('maps authoritative code %s to prototype code %s', (source, expected) => {
    expect(normalizeUnitCode(source)).toBe(expected)
  })
})

describe('useAuth', () => {
  it('maps the authenticated username into the domain user', () => {
    const { currentUser } = useAuth()

    expect(currentUser.value.username).toBe('admin.pusat')
  })
})
