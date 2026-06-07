import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import {
  clearHintSeen,
  hintStorageKey,
  readHintSeen,
  writeHintSeen,
} from '@/features/onboarding/lib/hintStorage'

describe('hintStorage', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('tracks hint dismissal in localStorage', () => {
    expect(readHintSeen('server-detail')).toBe(false)

    writeHintSeen('server-detail')

    expect(readHintSeen('server-detail')).toBe(true)
    expect(localStorage.getItem(hintStorageKey('server-detail'))).toBe('true')
  })

  it('clears a stored hint', () => {
    writeHintSeen('site-detail')
    clearHintSeen('site-detail')

    expect(readHintSeen('site-detail')).toBe(false)
  })
})
