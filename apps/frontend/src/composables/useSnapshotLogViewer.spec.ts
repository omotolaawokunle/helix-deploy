import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { useSnapshotLogViewer } from '@/composables/useSnapshotLogViewer'

describe('useSnapshotLogViewer', () => {
  it('keeps loading state stable during silent polling', async () => {
    vi.useFakeTimers()

    const logType = ref('nginx_access')
    const lineCount = ref(100)

    const fetchLogs = vi.fn(async () => ({
      status: 'loading' as const,
      lines: [],
    }))

    const { isLoading, loadLogs, stopPolling } = useSnapshotLogViewer({
      logType,
      lineCount,
      buildRequestKey: (type, lines) => `${type}:${lines}`,
      fetchLogs,
      defaultErrorMessage: 'Unable to load logs.',
    })

    await loadLogs(false)
    expect(isLoading.value).toBe(true)

    await vi.advanceTimersByTimeAsync(2000)

    expect(fetchLogs).toHaveBeenCalledTimes(2)
    expect(isLoading.value).toBe(true)

    stopPolling()
    vi.useRealTimers()
  })
})
