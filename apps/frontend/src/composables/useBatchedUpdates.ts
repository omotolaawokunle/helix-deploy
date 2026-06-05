export function useBatchedUpdates<T>(
  flush: (items: readonly T[]) => void,
): { push: (item: T) => void; flushNow: () => void; clear: () => void } {
  let pending: T[] = []
  let rafId: number | null = null

  const flushNow = (): void => {
    if (rafId !== null) {
      cancelAnimationFrame(rafId)
      rafId = null
    }

    if (pending.length === 0) {
      return
    }

    const batch = pending
    pending = []
    flush(batch)
  }

  const scheduleFlush = (): void => {
    if (import.meta.env.MODE === 'test') {
      flushNow()

      return
    }

    if (rafId !== null) {
      return
    }

    rafId = requestAnimationFrame(() => {
      rafId = null
      flushNow()
    })
  }

  const push = (item: T): void => {
    pending.push(item)
    scheduleFlush()
  }

  const clear = (): void => {
    pending = []

    if (rafId !== null) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
  }

  return { push, flushNow, clear }
}
