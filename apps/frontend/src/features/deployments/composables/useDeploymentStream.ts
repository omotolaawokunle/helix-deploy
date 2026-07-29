import { onUnmounted } from 'vue'
import type { DeploymentCompletedPayload } from '@/features/deployments/types'

export interface DeploymentStreamCallbacks {
  onLogLine: (stepId: string, line: string, timestamp: string) => void
  onStepUpdate: (stepId: string, status: string, duration: number | null) => void
  onStepStarted: (stepId: string, name: string, order: number, status: string, phase: string) => void
  onComplete: (payload: DeploymentCompletedPayload) => void
  onApprovalRequired: (payload: Record<string, unknown>) => void
}

export interface UseDeploymentStreamOptions {
  /** When false, caller must invoke connect(). Defaults to true. */
  immediate?: boolean
}

export interface ConnectDeploymentStreamOptions {
  /**
   * When false, connection errors close the stream instead of letting EventSource
   * auto-reconnect. Use for already-terminal deployments whose catch-up stream ends
   * immediately (success / failed / cancelled).
   */
  reconnect?: boolean
}

function parseEventData<T>(raw: string): T | null {
  try {
    return JSON.parse(raw) as T
  } catch {
    console.warn('Failed to parse deployment stream event data')

    return null
  }
}

export function useDeploymentStream(
  deploymentId: string,
  callbacks: DeploymentStreamCallbacks,
  options: UseDeploymentStreamOptions = {},
): { connect: (connectOptions?: ConnectDeploymentStreamOptions) => void; teardown: () => void } {
  const immediate = options.immediate !== false

  let eventSource: EventSource | null = null
  let reachedTerminal = false
  let allowReconnect = true

  const streamUrl = `${import.meta.env.VITE_API_URL}/api/v1/deployments/${deploymentId}/stream`

  const teardown = (): void => {
    if (eventSource !== null) {
      eventSource.close()
      eventSource = null
    }
  }

  const connect = (connectOptions: ConnectDeploymentStreamOptions = {}): void => {
    teardown()
    reachedTerminal = false
    allowReconnect = connectOptions.reconnect !== false

    eventSource = new EventSource(streamUrl, { withCredentials: true })

    eventSource.addEventListener('log.line', (event: MessageEvent<string>) => {
      const data = parseEventData<{ stepId?: string; line?: string; timestamp?: string }>(event.data)

      if (data?.stepId === undefined || data.line === undefined) {
        return
      }

      callbacks.onLogLine(
        data.stepId,
        data.line,
        data.timestamp ?? new Date().toISOString(),
      )
    })

    eventSource.addEventListener('step.started', (event: MessageEvent<string>) => {
      const data = parseEventData<{
        stepId?: string
        name?: string
        order?: number
        status?: string
        phase?: string
      }>(event.data)

      if (data?.stepId === undefined || data.name === undefined) {
        return
      }

      callbacks.onStepStarted(
        data.stepId,
        data.name,
        data.order ?? 0,
        data.status ?? 'running',
        data.phase ?? 'deploy',
      )
    })

    eventSource.addEventListener('step.completed', (event: MessageEvent<string>) => {
      const data = parseEventData<{
        stepId?: string
        status?: string
        duration?: number | null
      }>(event.data)

      if (data?.stepId === undefined || data.status === undefined) {
        return
      }

      callbacks.onStepUpdate(data.stepId, data.status, data.duration ?? null)
    })

    const handleTerminalEvent = (event: MessageEvent<string>): void => {
      const data = parseEventData<DeploymentCompletedPayload>(event.data)

      if (data === null || reachedTerminal) {
        return
      }

      reachedTerminal = true
      callbacks.onComplete(data)
      teardown()
    }

    // Failed deployments use the same deployment.completed catch-up event as success.
    eventSource.addEventListener('deployment.completed', handleTerminalEvent)
    eventSource.addEventListener('deployment.rolled_back', handleTerminalEvent)
    eventSource.addEventListener('deployment.cancelled', handleTerminalEvent)

    eventSource.addEventListener('deployment.approval_required', (event: MessageEvent<string>) => {
      const data = parseEventData<Record<string, unknown>>(event.data)

      if (data !== null) {
        callbacks.onApprovalRequired(data)
      }
    })

    eventSource.onerror = (): void => {
      // Terminal catch-up streams close right after the final event. Close explicitly so
      // EventSource does not auto-reconnect and re-play catch-up (looks like a reload loop).
      if (reachedTerminal || !allowReconnect) {
        teardown()

        return
      }

      console.warn('Deployment stream connection interrupted; EventSource will retry.')
    }
  }

  if (immediate) {
    connect()
  }

  onUnmounted(teardown)

  return { connect, teardown }
}
