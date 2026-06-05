export type ThemePreference = 'light' | 'dark' | 'system'

export type ResolvedTheme = 'light' | 'dark'

export const THEME_STORAGE_KEY = 'helix-deploy-theme'

let appliedIsDark: boolean | null = null

export function resolveIsDark(preference: ThemePreference): boolean {
  if (preference === 'dark') {
    return true
  }

  if (preference === 'light') {
    return false
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function resolveTheme(preference: ThemePreference): ResolvedTheme {
  return resolveIsDark(preference) ? 'dark' : 'light'
}

export function readStoredThemePreference(): ThemePreference {
  const stored = localStorage.getItem(THEME_STORAGE_KEY)

  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    return stored
  }

  return 'system'
}

export function applyThemePreference(preference: ThemePreference): ResolvedTheme {
  const isDark = resolveIsDark(preference)
  const resolved: ResolvedTheme = isDark ? 'dark' : 'light'
  const root = document.documentElement

  if (
    appliedIsDark === isDark
    && root.classList.contains('dark') === isDark
    && root.classList.contains('light') === !isDark
    && root.dataset.theme === resolved
  ) {
    return resolved
  }

  root.classList.toggle('dark', isDark)
  root.classList.toggle('light', !isDark)
  root.dataset.theme = resolved
  appliedIsDark = isDark

  return resolved
}

export function writeStoredThemePreference(preference: ThemePreference): void {
  localStorage.setItem(THEME_STORAGE_KEY, preference)
}

export function persistThemePreference(preference: ThemePreference): ResolvedTheme {
  writeStoredThemePreference(preference)

  return applyThemePreference(preference)
}

export function initThemePreference(): ThemePreference {
  const preference = readStoredThemePreference()

  applyThemePreference(preference)

  return preference
}

export function resetThemeApplicationState(): void {
  appliedIsDark = null
}
