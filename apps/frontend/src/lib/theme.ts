export type ThemePreference = 'light' | 'dark' | 'system'

export const THEME_STORAGE_KEY = 'helix-deploy-theme'

export function resolveIsDark(preference: ThemePreference): boolean {
  if (preference === 'dark') {
    return true
  }

  if (preference === 'light') {
    return false
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function readStoredThemePreference(): ThemePreference {
  const stored = localStorage.getItem(THEME_STORAGE_KEY)

  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    return stored
  }

  return 'system'
}

export function applyThemePreference(preference: ThemePreference): void {
  const root = document.documentElement

  if (resolveIsDark(preference)) {
    root.classList.add('dark')
  } else {
    root.classList.remove('dark')
  }
}

export function persistThemePreference(preference: ThemePreference): void {
  localStorage.setItem(THEME_STORAGE_KEY, preference)
  applyThemePreference(preference)
}

export function initThemePreference(): ThemePreference {
  const preference = readStoredThemePreference()
  applyThemePreference(preference)

  return preference
}
