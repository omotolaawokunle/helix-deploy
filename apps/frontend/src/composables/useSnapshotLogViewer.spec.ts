import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { toast } from 'vue-sonner'
import { useSnapshotLogViewer } from '@/composables/useSnapshotLogViewer'

vi.mock('vue-sonner', () => ({
  toast: {
    error: vi.fn(),
  },
}))

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

  it('stops polling and surfaces a timeout after the maximum attempts', async () => {
    vi.useFakeTimers()

    const logType = ref('application')
    const lineCount = ref(100)

    const fetchLogs = vi.fn(async () => ({
      status: 'loading' as const,
      lines: [],
    }))

    const { isLoading, errorMessage, loadLogs } = useSnapshotLogViewer({
      logType,
      lineCount,
      buildRequestKey: (type, lines) => `${type}:${lines}`,
      fetchLogs,
      defaultErrorMessage: 'Unable to load logs.',
    })

    await loadLogs(false)
    expect(isLoading.value).toBe(true)

    await vi.advanceTimersByTimeAsync(2000 * 90)

    expect(isLoading.value).toBe(false)
    expect(errorMessage.value).toContain('timed out')
    expect(toast.error).toHaveBeenCalled()

    vi.useRealTimers()
  })
})
