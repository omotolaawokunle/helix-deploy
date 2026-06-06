export interface CommandStreamCallbacks {
  onLogLine: (line: string, timestamp: string) => void
  onComplete: (payload: { status: string; exitCode: number | null; duration: number | null }) => void
}

function parseEventData<T>(raw: string): T | null {
  try {
    return JSON.parse(raw) as T
  } catch {
    console.warn('Failed to parse command stream event data')

    return null
  }
}

export function useCommandStream(
  commandId: string,
  callbacks: CommandStreamCallbacks,
): { teardown: () => void } {
  let eventSource: EventSource | null = null

  const streamUrl = `${import.meta.env.VITE_API_URL}/api/v1/commands/${commandId}/stream`

  const teardown = (): void => {
    if (eventSource !== null) {
      eventSource.close()
      eventSource = null
    }
  }

  const setup = (): void => {
    teardown()

    eventSource = new EventSource(streamUrl, { withCredentials: true })

    eventSource.addEventListener('log.line', (event: MessageEvent<string>) => {
      const data = parseEventData<{ line?: string; timestamp?: string }>(event.data)

      if (data?.line === undefined) {
        return
      }

      callbacks.onLogLine(data.line, data.timestamp ?? new Date().toISOString())
    })

    eventSource.addEventListener('command.completed', (event: MessageEvent<string>) => {
      const data = parseEventData<{
        status?: string
        exitCode?: number | null
        duration?: number | null
      }>(event.data)

      if (data?.status === undefined) {
        return
      }

      callbacks.onComplete({
        status: data.status,
        exitCode: data.exitCode ?? null,
        duration: data.duration ?? null,
      })
      teardown()
    })

    eventSource.onerror = (): void => {
      console.warn('Command stream connection interrupted; EventSource will retry.')
    }
  }

  setup()

  return { teardown }
}
