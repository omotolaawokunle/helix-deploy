import { onUnmounted, watch, type Ref } from 'vue'
import {
  DAEMON_BROADCAST_EVENTS,
  privateServerDaemonsChannel,
  type DaemonChangedPayload,
  type DaemonLogsReadyPayload,
} from '@/features/daemons/types'
import { getEcho, initEcho } from '@/lib/echo'

export interface DaemonChannelCallbacks {
  onDaemonChanged?: (payload: DaemonChangedPayload) => void
  onLogsReady?: (payload: DaemonLogsReadyPayload) => void
}

export function useDaemonChannel(
  serverId: Ref<string>,
  callbacks: DaemonChannelCallbacks,
): { disconnect: () => void } {
  let activeChannelName: string | null = null
  let activeChannel: ReturnType<NonNullable<ReturnType<typeof getEcho>>['private']> | null = null

  const disconnect = (): void => {
    const echo = getEcho()

    if (echo === undefined || activeChannelName === null || activeChannel === null) {
      activeChannelName = null
      activeChannel = null

      return
    }

    Object.values(DAEMON_BROADCAST_EVENTS).forEach((event) => {
      activeChannel?.stopListening(`.${event}`)
    })

    echo.leave(activeChannelName)
    activeChannelName = null
    activeChannel = null
  }

  const connect = (nextServerId: string): void => {
    disconnect()

    initEcho()
    const echo = getEcho()

    if (echo === undefined) {
      return
    }

    const channelName = privateServerDaemonsChannel(nextServerId)
    const channel = echo.private(channelName)
    activeChannelName = channelName
    activeChannel = channel as typeof activeChannel

    if (callbacks.onDaemonChanged !== undefined) {
      channel.listen(`.${DAEMON_BROADCAST_EVENTS.changed}`, (payload: unknown) => {
        callbacks.onDaemonChanged?.(payload as DaemonChangedPayload)
      })
    }

    if (callbacks.onLogsReady !== undefined) {
      channel.listen(`.${DAEMON_BROADCAST_EVENTS.logsReady}`, (payload: unknown) => {
        callbacks.onLogsReady?.(payload as DaemonLogsReadyPayload)
      })
    }
  }

  watch(
    serverId,
    (nextServerId) => {
      if (nextServerId === '') {
        disconnect()

        return
      }

      connect(nextServerId)
    },
    { immediate: true },
  )

  onUnmounted(disconnect)

  return { disconnect }
}
