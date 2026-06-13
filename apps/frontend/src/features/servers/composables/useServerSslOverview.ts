import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import { fetchServerSslCertificates } from '@/features/servers/api'
import type { ServerSslOverview } from '@/features/servers/types'

const POLL_INTERVAL_MS = 5000
const MAX_POLL_ATTEMPTS = 12

export function useServerSslOverview(serverId: Ref<string>): {
  overview: Ref<ServerSslOverview | null>
  isLoading: Ref<boolean>
  isRefreshing: Ref<boolean>
  loadError: Ref<string | null>
  lastCheckedAt: Ref<string | null>
  hasSites: Ref<boolean>
  hasUnadoptedSites: Ref<boolean>
  isBackgroundUpdatePending: Ref<boolean>
  loadOverview: () => Promise<void>
  refreshOverview: () => Promise<void>
  beginBackgroundRefresh: () => void
} {
  const overview = ref<ServerSslOverview | null>(null)
  const isLoading = ref(true)
  const isRefreshing = ref(false)
  const loadError = ref<string | null>(null)
  const isBackgroundUpdatePending = ref(false)

  let pollTimer: ReturnType<typeof setInterval> | null = null
  let pollAttempts = 0

  const hasSites = computed(
    (): boolean => (overview.value?.certificates.length ?? 0) > 0,
  )

  const hasUnadoptedSites = computed((): boolean => {
    if (overview.value === null) {
      return false
    }

    return overview.value.certificates.some(
      cert => cert.sslStatus !== 'active' || cert.sslExpiresAt === null,
    )
  })

  const lastCheckedAt = computed((): string | null => {
    const timestamps = overview.value?.certificates
      .map(cert => cert.sslCheckedAt)
      .filter((value): value is string => value !== null)
      .sort()

    return timestamps?.at(-1) ?? null
  })

  function stopPolling(): void {
    if (pollTimer !== null) {
      clearInterval(pollTimer)
      pollTimer = null
    }

    pollAttempts = 0
  }

  function shouldContinuePolling(): boolean {
    if (!isBackgroundUpdatePending.value && overview.value?.syncQueued !== true) {
      return false
    }

    return pollAttempts < MAX_POLL_ATTEMPTS
  }

  function startPollingIfNeeded(): void {
    stopPolling()

    if (!shouldContinuePolling()) {
      isBackgroundUpdatePending.value = false

      return
    }

    pollTimer = setInterval(() => {
      pollAttempts++

      if (!shouldContinuePolling()) {
        stopPolling()
        isBackgroundUpdatePending.value = false

        return
      }

      void loadOverview({ silent: true })
    }, POLL_INTERVAL_MS)
  }

  async function loadOverview(options: { silent?: boolean } = {}): Promise<void> {
    const silent = options.silent === true

    if (!silent) {
      loadError.value = null
    }

    if (silent) {
      isRefreshing.value = true
    }

    try {
      overview.value = await fetchServerSslCertificates(serverId.value)

      if (!overview.value.syncQueued && !isBackgroundUpdatePending.value) {
        stopPolling()
      } else if (overview.value.syncQueued || isBackgroundUpdatePending.value) {
        startPollingIfNeeded()
      }
    } catch {
      if (!silent) {
        loadError.value = 'Unable to load SSL certificates.'
      }
    } finally {
      isLoading.value = false
      isRefreshing.value = false
    }
  }

  async function refreshOverview(): Promise<void> {
    isRefreshing.value = true
    await loadOverview({ silent: true })
  }

  function beginBackgroundRefresh(): void {
    isBackgroundUpdatePending.value = true
    pollAttempts = 0
    startPollingIfNeeded()
  }

  watch(serverId, () => {
    isLoading.value = true
    stopPolling()
    isBackgroundUpdatePending.value = false
    void loadOverview()
  })

  onBeforeUnmount(() => {
    stopPolling()
  })

  return {
    overview,
    isLoading,
    isRefreshing,
    loadError,
    lastCheckedAt,
    hasSites,
    hasUnadoptedSites,
    isBackgroundUpdatePending,
    loadOverview,
    refreshOverview,
    beginBackgroundRefresh,
  }
}
