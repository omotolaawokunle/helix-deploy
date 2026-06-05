import { computed, watch } from 'vue'
import {
  useDocumentVisibility,
  useIntervalFn,
  usePreferredReducedMotion,
} from '@vueuse/core'

export function useReducedMotionPolling(
  callback: () => void | Promise<void>,
  intervalMs: number,
): void {
  const prefersReducedMotion = usePreferredReducedMotion()
  const documentVisibility = useDocumentVisibility()

  const shouldPoll = computed(
    () => prefersReducedMotion.value !== 'reduce' && documentVisibility.value === 'visible',
  )

  const { pause, resume } = useIntervalFn(
    () => {
      void callback()
    },
    intervalMs,
    { immediate: false },
  )

  watch(
    shouldPoll,
    (active) => {
      if (active) {
        resume()
        return
      }

      pause()
    },
    { immediate: true },
  )
}
