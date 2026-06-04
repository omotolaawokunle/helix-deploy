import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  applyThemePreference,
  readStoredThemePreference,
  resolveIsDark,
  THEME_STORAGE_KEY,
} from '@/lib/theme'

describe('theme', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.classList.remove('dark')
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('defaults to system preference when nothing is stored', () => {
    expect(readStoredThemePreference()).toBe('system')
  })

  it('reads stored preference from localStorage', () => {
    localStorage.setItem(THEME_STORAGE_KEY, 'dark')
    expect(readStoredThemePreference()).toBe('dark')
  })

  it('applies dark class when preference is dark', () => {
    applyThemePreference('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('removes dark class when preference is light', () => {
    document.documentElement.classList.add('dark')
    applyThemePreference('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('follows system preference when set to system', () => {
    vi.spyOn(window, 'matchMedia').mockReturnValue({
      matches: true,
      media: '(prefers-color-scheme: dark)',
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })

    expect(resolveIsDark('system')).toBe(true)
  })
})
