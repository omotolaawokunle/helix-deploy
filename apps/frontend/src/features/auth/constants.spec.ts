import { describe, expect, it } from 'vitest'
import { apiTokenAbilityLabel } from '@/features/auth/constants'

describe('api token constants', () => {
  it('labels token abilities for display', () => {
    expect(apiTokenAbilityLabel('read')).toBe('Read only')
    expect(apiTokenAbilityLabel('full')).toBe('Full access')
  })
})
