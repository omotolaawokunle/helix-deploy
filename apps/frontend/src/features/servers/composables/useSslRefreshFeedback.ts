import { onUnmounted, ref, watch, type ComputedRef, type Ref } from 'vue'
import { useRotatingStatusMessage } from '@/composables/useRotatingStatusMessage'

const SSL_REFRESH_HINTS = [
  'Reading certificate expiry from certbot…',
  'Checking Let\'s Encrypt certificate paths…',
  'Updating expiry dates in Helix…',
] as const

export function useSslRefreshFeedback(isBackgroundRefreshing: Ref<boolean>): {
  refreshHint: ComputedRef<string>
  showRefreshComplete: Ref<boolean>
} {
  const showRefreshComplete = ref(false)
  let refreshCompleteTimer: ReturnType<typeof setTimeout> | null = null

  const refreshHint = useRotatingStatusMessage(SSL_REFRESH_HINTS, isBackgroundRefreshing)

  watch(isBackgroundRefreshing, (refreshing, wasRefreshing) => {
    if (wasRefreshing === true && refreshing === false) {
      showRefreshComplete.value = true

      if (refreshCompleteTimer !== null) {
        clearTimeout(refreshCompleteTimer)
      }

      refreshCompleteTimer = setTimeout(() => {
        showRefreshComplete.value = false
        refreshCompleteTimer = null
      }, 650)
    }
  })

  onUnmounted(() => {
    if (refreshCompleteTimer !== null) {
      clearTimeout(refreshCompleteTimer)
    }
  })

  return {
    refreshHint,
    showRefreshComplete,
  }
}

export function sslRowEntranceDelay(index: number): string {
  return `${Math.min(index, 8) * 45}ms`
}
