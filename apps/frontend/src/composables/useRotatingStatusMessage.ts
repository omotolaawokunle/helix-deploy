import { computed, onUnmounted, ref, watch, type ComputedRef, type Ref } from 'vue'

export function useRotatingStatusMessage(
  messages: readonly string[],
  active: Ref<boolean> | ComputedRef<boolean>,
  intervalMs = 3200,
): ComputedRef<string> {
  const index = ref(0)
  let timer: ReturnType<typeof setInterval> | null = null

  const message = computed((): string => {
    if (messages.length === 0) {
      return ''
    }

    return messages[index.value % messages.length] ?? messages[0] ?? ''
  })

  function stop(): void {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  function start(): void {
    stop()

    if (messages.length <= 1) {
      return
    }

    timer = setInterval(() => {
      index.value = (index.value + 1) % messages.length
    }, intervalMs)
  }

  watch(
    active,
    (isActive) => {
      index.value = 0

      if (isActive) {
        start()

        return
      }

      stop()
    },
    { immediate: true },
  )

  onUnmounted(stop)

  return message
}
