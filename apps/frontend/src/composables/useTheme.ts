import { createSharedComposable, useMediaQuery } from '@vueuse/core'
import { computed, ref, watch, type ComputedRef, type Ref } from 'vue'
import {
  applyThemePreference,
  readStoredThemePreference,
  writeStoredThemePreference,
  type ResolvedTheme,
  type ThemePreference,
} from '@/lib/theme'

interface UseThemeReturn {
  preference: Ref<ThemePreference>
  resolvedTheme: ComputedRef<ResolvedTheme>
  isDark: ComputedRef<boolean>
  setPreference: (value: ThemePreference) => void
}

export const useTheme = createSharedComposable((): UseThemeReturn => {
  const preference = ref<ThemePreference>(readStoredThemePreference())
  const systemIsDark = useMediaQuery('(prefers-color-scheme: dark)')

  const isDark = computed((): boolean => {
    if (preference.value === 'dark') {
      return true
    }

    if (preference.value === 'light') {
      return false
    }

    return systemIsDark.value
  })

  const resolvedTheme = computed((): ResolvedTheme => (isDark.value ? 'dark' : 'light'))

  watch(
    [preference, systemIsDark],
    ([nextPreference], [previousPreference]) => {
      if (nextPreference !== previousPreference) {
        writeStoredThemePreference(nextPreference)
      }

      applyThemePreference(nextPreference)
    },
    { immediate: true },
  )

  function setPreference(value: ThemePreference): void {
    preference.value = value
  }

  return {
    preference,
    resolvedTheme,
    isDark,
    setPreference,
  }
})
