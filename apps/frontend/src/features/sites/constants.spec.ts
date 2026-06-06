import { describe, expect, it } from 'vitest'
import {
  isSelectableBuildStrategy,
  SELECTABLE_SITE_BUILD_STRATEGIES,
} from '@/features/sites/constants'

describe('site build strategy constants', () => {
  it('excludes external from selectable v1 strategies', () => {
    expect(SELECTABLE_SITE_BUILD_STRATEGIES).toEqual(['on_server', 'runner'])
    expect(SELECTABLE_SITE_BUILD_STRATEGIES).not.toContain('external')
  })

  it('identifies external as non-selectable', () => {
    expect(isSelectableBuildStrategy('external')).toBe(false)
    expect(isSelectableBuildStrategy('runner')).toBe(true)
  })
})
