import { watch } from 'vue'
import { useIntervalFn, usePreferredReducedMotion } from '@vueuse/core'

export function useReducedMotionPolling(
  callback: () => void | Promise<void>,
  intervalMs: number,
): void {
  const prefersReducedMotion = usePreferredReducedMotion()

  const { pause, resume } = useIntervalFn(
    () => {
      void callback()
    },
    intervalMs,
    { immediate: false },
  )

  watch(
    prefersReducedMotion,
    (reduced) => {
      if (reduced === 'reduce') {
        pause()
        return
      }

      resume()
    },
    { immediate: true },
  )
}
