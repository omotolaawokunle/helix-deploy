import { onMounted, onUnmounted, ref, type Ref } from 'vue'
import {
  initThemePreference,
  persistThemePreference,
  readStoredThemePreference,
  type ThemePreference,
} from '@/lib/theme'

interface UseThemeReturn {
  preference: Ref<ThemePreference>
  setPreference: (value: ThemePreference) => void
}

export function useTheme(): UseThemeReturn {
  const preference = ref<ThemePreference>(readStoredThemePreference())

  function setPreference(value: ThemePreference): void {
    preference.value = value
    persistThemePreference(value)
  }

  function handleSystemChange(): void {
    if (preference.value === 'system') {
      persistThemePreference('system')
    }
  }

  onMounted(() => {
    preference.value = initThemePreference()
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleSystemChange)
  })

  onUnmounted(() => {
    window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', handleSystemChange)
  })

  return {
    preference,
    setPreference,
  }
}
