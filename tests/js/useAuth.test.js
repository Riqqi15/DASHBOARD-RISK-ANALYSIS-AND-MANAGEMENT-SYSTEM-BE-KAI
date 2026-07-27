import { describe, expect, it } from 'vitest'
import { normalizeUnitCode } from '@/application/composables/useAuth'

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
