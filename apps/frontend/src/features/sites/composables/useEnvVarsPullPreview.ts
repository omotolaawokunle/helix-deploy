import { onUnmounted, ref, type Ref } from 'vue'
import { fetchEnvVarsPullPreview } from '@/features/sites/api'
import type { EnvVarPullPreviewReadyPayload } from '@/features/sites/types'
import type { EnvVarPullPreview } from '@/types'

const POLL_INTERVAL_MS = 2000

interface UseEnvVarsPullPreviewOptions {
  siteId: Ref<string>
  defaultErrorMessage?: string
}

export function useEnvVarsPullPreview(
  options: UseEnvVarsPullPreviewOptions,
): {
  pullPreview: Ref<EnvVarPullPreview | null>
  isLoading: Ref<boolean>
  errorMessage: Ref<string | null>
  loadPreview: (refresh?: boolean, silent?: boolean) => Promise<void>
  handlePreviewReady: (payload: EnvVarPullPreviewReadyPayload) => void
  startPolling: () => void
  stopPolling: () => void
  reset: () => void
} {
  const pullPreview = ref<EnvVarPullPreview | null>(null)
  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let inFlight = false
  let pollingEnabled = false

  function stopPolling(): void {
    pollingEnabled = false

    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  function startPolling(): void {
    stopPolling()
    pollingEnabled = true

    pollTimer = setInterval(() => {
      void loadPreview(false, true)
    }, POLL_INTERVAL_MS)
  }

  function applyPreview(preview: EnvVarPullPreview): void {
    if (preview.status === 'loading') {
      isLoading.value = true
      errorMessage.value = null

      return
    }

    stopPolling()
    isLoading.value = false
    pullPreview.value = preview

    if (preview.status === 'failed') {
      errorMessage.value = preview.message ?? options.defaultErrorMessage ?? 'Unable to load pull preview.'

      return
    }

    errorMessage.value = null
  }

  async function loadPreview(refresh = false, silent = false): Promise<void> {
    if (inFlight) {
      return
    }

    inFlight = true

    if (! silent) {
      isLoading.value = true
      errorMessage.value = null
    }

    try {
      const preview = await fetchEnvVarsPullPreview(options.siteId.value, { refresh })

      if (preview.status === 'loading') {
        if (! silent) {
          isLoading.value = true
        }

        if (pollingEnabled) {
          startPolling()
        }

        inFlight = false

        return
      }

      applyPreview(preview)
    } catch {
      stopPolling()
      isLoading.value = false
      errorMessage.value = options.defaultErrorMessage ?? 'Unable to load pull preview.'
    } finally {
      inFlight = false
    }
  }

  function handlePreviewReady(payload: EnvVarPullPreviewReadyPayload): void {
    if (payload.siteId !== options.siteId.value) {
      return
    }

    if (payload.status === 'loading') {
      return
    }

    if (payload.status === 'failed') {
      applyPreview({
        status: 'failed',
        serverFileExists: false,
        new: [],
        changed: [],
        unchanged: [],
        helixOnly: [],
        skipped: [],
        message: payload.message ?? options.defaultErrorMessage ?? 'Unable to load pull preview.',
      })

      return
    }

    const diff = payload.diff

    applyPreview({
      status: 'ready',
      serverFileExists: diff?.serverFileExists ?? false,
      new: diff?.new ?? [],
      changed: diff?.changed ?? [],
      unchanged: diff?.unchanged ?? [],
      helixOnly: diff?.helixOnly ?? [],
      skipped: diff?.skipped ?? [],
      message: payload.message ?? null,
    })
  }

  function reset(): void {
    stopPolling()
    pullPreview.value = null
    isLoading.value = false
    errorMessage.value = null
  }

  onUnmounted(stopPolling)

  return {
    pullPreview,
    isLoading,
    errorMessage,
    loadPreview,
    handlePreviewReady,
    startPolling,
    stopPolling,
    reset,
  }
}
