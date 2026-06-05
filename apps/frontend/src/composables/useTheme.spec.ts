import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, nextTick, type Component } from 'vue'
import { resetThemeApplicationState, THEME_STORAGE_KEY } from '@/lib/theme'
import type { useTheme as UseThemeFn } from '@/composables/useTheme'

function installMatchMediaMock(prefersDark = false): void {
  vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
    matches: query === '(prefers-color-scheme: dark)' ? prefersDark : false,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }))
}

function createThemeProbe(useTheme: typeof UseThemeFn): Component {
  return defineComponent({
    setup() {
      const { preference, resolvedTheme, setPreference } = useTheme()

      return { preference, resolvedTheme, setPreference }
    },
    template: '<div />',
  })
}

describe('useTheme', () => {
  beforeEach(() => {
    vi.resetModules()
    localStorage.clear()
    resetThemeApplicationState()
    document.documentElement.classList.remove('dark', 'light')
    delete document.documentElement.dataset.theme
    installMatchMediaMock(false)
  })

  afterEach(() => {
    vi.restoreAllMocks()
    localStorage.clear()
    resetThemeApplicationState()
    document.documentElement.classList.remove('dark', 'light')
    delete document.documentElement.dataset.theme
  })

  it('persists preference and toggles the document theme', async () => {
    const { useTheme } = await import('@/composables/useTheme')
    const wrapper = mount(createThemeProbe(useTheme))

    wrapper.vm.setPreference('dark')
    await nextTick()

    expect(wrapper.vm.preference).toBe('dark')
    expect(wrapper.vm.resolvedTheme).toBe('dark')
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(document.documentElement.dataset.theme).toBe('dark')

    wrapper.vm.setPreference('light')
    await nextTick()

    expect(wrapper.vm.preference).toBe('light')
    expect(wrapper.vm.resolvedTheme).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(document.documentElement.classList.contains('light')).toBe(true)
    expect(document.documentElement.dataset.theme).toBe('light')

    wrapper.unmount()
  })

  it('reuses a single shared theme state across components', async () => {
    const { useTheme } = await import('@/composables/useTheme')
    const ThemeProbe = createThemeProbe(useTheme)
    const first = mount(ThemeProbe)
    const second = mount(ThemeProbe)

    first.vm.setPreference('dark')
    await nextTick()

    expect(second.vm.preference).toBe('dark')
    expect(second.vm.resolvedTheme).toBe('dark')

    first.unmount()
    second.unmount()
  })
})
