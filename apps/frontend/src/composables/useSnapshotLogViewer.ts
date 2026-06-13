import { onUnmounted, ref, type Ref } from 'vue'
import { toast } from 'vue-sonner'
import type { LogFetchResponse, LogFetchStatus } from '@/features/logs/types'

const POLL_INTERVAL_MS = 2000
const MAX_POLL_ATTEMPTS = 90

export interface SnapshotLogFetchParams<TType extends string> {
  type: TType
  lines: number
  refresh: boolean
}

export interface SnapshotLogsReadyPayload<TType extends string> {
  logType: TType
  linesRequested: number
  status: 'ready' | 'failed'
  lines: string[]
  message?: string | null
}

interface UseSnapshotLogViewerOptions<TType extends string> {
  logType: Ref<TType>
  lineCount: Ref<number>
  buildRequestKey: (type: TType, lines: number) => string
  fetchLogs: (params: SnapshotLogFetchParams<TType>) => Promise<LogFetchResponse>
  defaultErrorMessage: string
  pollTimeoutMessage?: string
}

export function useSnapshotLogViewer<TType extends string>(
  options: UseSnapshotLogViewerOptions<TType>,
): {
  logLines: Ref<string[]>
  isLoading: Ref<boolean>
  errorMessage: Ref<string | null>
  loadLogs: (refresh: boolean) => Promise<void>
  handleRefresh: () => void
  handleLogsReady: (payload: SnapshotLogsReadyPayload<TType>) => void
  stopPolling: () => void
} {
  const logLines = ref<string[]>([])
  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)
  const awaitingKey = ref<string | null>(null)

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let pollAttempts = 0
  let inFlight = false

  const pollTimeoutMessage = options.pollTimeoutMessage ?? 'Log fetch timed out. Try refreshing.'

  function stopPolling(): void {
    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }

    pollAttempts = 0
  }

  function failPollingTimeout(): void {
    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }

    pollAttempts = 0
    isLoading.value = false
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

      void loadLogs(false, true)
    }, POLL_INTERVAL_MS)
  }

  function applyResponse(
    status: LogFetchStatus,
    lines: string[],
    message?: string | null,
  ): void {
    if (status === 'loading') {
      return
    }

    stopPolling()
    isLoading.value = false
    awaitingKey.value = null
    inFlight = false
    logLines.value = lines

    if (status === 'failed') {
      errorMessage.value = message ?? options.defaultErrorMessage
      toast.error(errorMessage.value)

      return
    }

    errorMessage.value = null
  }

  async function loadLogs(refresh: boolean, silent = false): Promise<void> {
    if (inFlight) {
      return
    }

    const key = options.buildRequestKey(options.logType.value, options.lineCount.value)
    awaitingKey.value = key
    inFlight = true

    if (!silent) {
      isLoading.value = true
      errorMessage.value = null

      if (refresh) {
        logLines.value = []
      }
    }

    try {
      const response = await options.fetchLogs({
        type: options.logType.value,
        lines: options.lineCount.value,
        refresh,
      })

      if (response.status === 'loading') {
        if (!silent) {
          isLoading.value = true
        }

        startPolling(!silent)
        inFlight = false

        return
      }

      applyResponse(response.status, response.lines, response.message)
    } catch {
      stopPolling()
      isLoading.value = false
      awaitingKey.value = null
      inFlight = false
      errorMessage.value = options.defaultErrorMessage
      toast.error(errorMessage.value)
    }
  }

  function handleRefresh(): void {
    stopPolling()
    void loadLogs(true)
  }

  function handleLogsReady(payload: SnapshotLogsReadyPayload<TType>): void {
    const key = options.buildRequestKey(payload.logType, payload.linesRequested)

    if (awaitingKey.value !== key) {
      return
    }

    applyResponse(payload.status, payload.lines, payload.message)
  }

  onUnmounted(stopPolling)

  return {
    logLines,
    isLoading,
    errorMessage,
    loadLogs: (refresh: boolean) => loadLogs(refresh, false),
    handleRefresh,
    handleLogsReady,
    stopPolling,
  }
}
