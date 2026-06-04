import { computed, watch, type Ref } from 'vue'
import { useIntervalFn } from '@vueuse/core'
import { ServerStatus, type Server } from '@/types'

export function useServerPolling(servers: Ref<Server[]>, refetch: () => Promise<void>): void {
  const shouldPoll = computed(() =>
    servers.value.some(server => server.status === ServerStatus.Connecting),
  )

  const { pause, resume } = useIntervalFn(
    () => {
      void refetch()
    },
    5000,
    { immediate: false },
  )

  watch(
    shouldPoll,
    (polling) => {
      if (polling) {
        resume()
        return
      }

      pause()
    },
    { immediate: true },
  )
}
