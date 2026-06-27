import { onUnmounted, ref, type Ref } from 'vue'
import { toast } from 'vue-sonner'
import type { DatabaseBrowseResponse, DatabaseBrowseStatus } from '@/features/databases/types'

const POLL_INTERVAL_MS = 2000
const MAX_POLL_ATTEMPTS = 90

export interface DatabaseBrowseReadyPayload {
  kind: string
  status: 'ready' | 'failed'
  message?: string | null
}

interface UseDatabaseBrowserOptions {
  buildRequestKey: () => string
  fetchBrowse: (refresh: boolean) => Promise<DatabaseBrowseResponse>
  matchesReadyPayload: (payload: DatabaseBrowseReadyPayload) => boolean
  defaultErrorMessage: string
  pollTimeoutMessage?: string
}

export function useDatabaseBrowser(
  options: UseDatabaseBrowserOptions,
): {
  data: Ref<DatabaseBrowseResponse | null>
  isLoading: Ref<boolean>
  isFetching: Ref<boolean>
  errorMessage: Ref<string | null>
  showReadyFlash: Ref<boolean>
  load: (refresh?: boolean, silent?: boolean) => Promise<void>
  handleRefresh: () => void
  handleBrowseReady: (payload: DatabaseBrowseReadyPayload) => void
  stopPolling: () => void
} {
  const data = ref<DatabaseBrowseResponse | null>(null)
  const isLoading = ref(false)
  const isFetching = ref(false)
  const errorMessage = ref<string | null>(null)
  const showReadyFlash = ref(false)
  const awaitingKey = ref<string | null>(null)

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let pollAttempts = 0
  let inFlight = false
  let readyFlashTimer: ReturnType<typeof setTimeout> | null = null

  const pollTimeoutMessage = options.pollTimeoutMessage ?? 'Database browse timed out. Try refreshing.'

  function triggerReadyFlash(): void {
    if (readyFlashTimer !== null) {
      clearTimeout(readyFlashTimer)
    }

    showReadyFlash.value = true
    readyFlashTimer = setTimeout(() => {
      showReadyFlash.value = false
      readyFlashTimer = null
    }, 520)
  }

  function stopPolling(): void {
    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }

    pollAttempts = 0
    inFlight = false
    isFetching.value = false
  }

  function failPollingTimeout(): void {
    stopPolling()
    isLoading.value = false
    isFetching.value = false
    awaitingKey.value = null
    inFlight = false
    errorMessage.value = pollTimeoutMessage
    toast.error(errorMessage.value)
  }

  function startPolling(resetAttempts = true): void {
    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }

    if (resetAttempts) {
      pollAttempts = 0
    }

    pollTimer = setInterval(() => {
      pollAttempts += 1

      if (pollAttempts >= MAX_POLL_ATTEMPTS) {
        failPollingTimeout()

        return
      }

      void load(false, true)
    }, POLL_INTERVAL_MS)
  }

  function applyResponse(response: DatabaseBrowseResponse, silent: boolean): void {
    const status: DatabaseBrowseStatus = response.status

    if (status === 'loading') {
      return
    }

    stopPolling()
    isLoading.value = false
    isFetching.value = false
    awaitingKey.value = null
    inFlight = false
    data.value = response

    if (status === 'failed') {
      errorMessage.value = response.message ?? options.defaultErrorMessage
      toast.error(errorMessage.value)

      return
    }

    errorMessage.value = null

    if (! silent) {
      triggerReadyFlash()
    }
  }

  async function load(refresh = false, silent = false): Promise<void> {
    const requestKey = options.buildRequestKey()

    if (inFlight && ! silent) {
      return
    }

    if (! silent) {
      isLoading.value = true
      errorMessage.value = null
    } else {
      isFetching.value = true
    }

    inFlight = true
    awaitingKey.value = requestKey

    try {
      const response = await options.fetchBrowse(refresh)

      if (awaitingKey.value !== null && awaitingKey.value !== requestKey) {
        return
      }

      applyResponse(response, silent)

      if (response.status === 'loading') {
        startPolling(! silent)
        inFlight = false
      }
    } catch {
      if (awaitingKey.value !== null && awaitingKey.value !== requestKey) {
        return
      }

      stopPolling()
      isLoading.value = false
      isFetching.value = false
      awaitingKey.value = null
      inFlight = false
      errorMessage.value = options.defaultErrorMessage
      toast.error(errorMessage.value)
    }
  }

  function handleRefresh(): void {
    stopPolling()
    void load(true)
  }

  function handleBrowseReady(payload: DatabaseBrowseReadyPayload): void {
    if (! options.matchesReadyPayload(payload)) {
      return
    }

    if (payload.status === 'failed') {
      stopPolling()
      isLoading.value = false
      isFetching.value = false
      errorMessage.value = payload.message ?? options.defaultErrorMessage

      return
    }

    void load(false, true)
  }

  onUnmounted(() => {
    stopPolling()

    if (readyFlashTimer !== null) {
      clearTimeout(readyFlashTimer)
    }
  })

  return {
    data,
    isLoading,
    isFetching,
    errorMessage,
    showReadyFlash,
    load,
    handleRefresh,
    handleBrowseReady,
    stopPolling,
  }
}
