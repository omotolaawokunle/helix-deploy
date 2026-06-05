import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  applyThemePreference,
  readStoredThemePreference,
  resetThemeApplicationState,
  resolveIsDark,
  resolveTheme,
  THEME_STORAGE_KEY,
} from '@/lib/theme'

describe('theme', () => {
  beforeEach(() => {
    localStorage.clear()
    resetThemeApplicationState()
    document.documentElement.classList.remove('dark', 'light')
    delete document.documentElement.dataset.theme
  })

  afterEach(() => {
    vi.restoreAllMocks()
    resetThemeApplicationState()
  })

  it('defaults to system preference when nothing is stored', () => {
    expect(readStoredThemePreference()).toBe('system')
  })

  it('reads stored preference from localStorage', () => {
    localStorage.setItem(THEME_STORAGE_KEY, 'dark')
    expect(readStoredThemePreference()).toBe('dark')
  })

  it('applies dark class when preference is dark', () => {
    expect(resolveTheme('dark')).toBe('dark')
    applyThemePreference('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(document.documentElement.classList.contains('light')).toBe(false)
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('applies light class when preference is light', () => {
    document.documentElement.classList.add('dark')
    expect(resolveTheme('light')).toBe('light')
    applyThemePreference('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(document.documentElement.classList.contains('light')).toBe(true)
    expect(document.documentElement.dataset.theme).toBe('light')
  })

  it('skips redundant DOM writes when resolved theme is unchanged', () => {
    applyThemePreference('dark')
    const classListToggle = vi.spyOn(document.documentElement.classList, 'toggle')

    applyThemePreference('dark')

    expect(classListToggle).not.toHaveBeenCalled()
    classListToggle.mockRestore()
  })

  it('re-applies when system preference changes while mode is system', () => {
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

    applyThemePreference('system')
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    vi.spyOn(window, 'matchMedia').mockReturnValue({
      matches: false,
      media: '(prefers-color-scheme: dark)',
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })

    resetThemeApplicationState()
    applyThemePreference('system')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(document.documentElement.classList.contains('light')).toBe(true)
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
