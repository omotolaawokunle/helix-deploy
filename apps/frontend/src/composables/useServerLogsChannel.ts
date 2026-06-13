import { onUnmounted, watch, type Ref } from 'vue'
import {
  SERVER_LOGS_BROADCAST_EVENTS,
  privateServerLogsChannel,
  type ServerLogsReadyPayload,
  type SiteLogsReadyPayload,
} from '@/features/logs/types'
import { getEcho, initEcho } from '@/lib/echo'

export interface ServerLogsChannelCallbacks {
  onServerLogsReady?: (payload: ServerLogsReadyPayload) => void
  onSiteLogsReady?: (payload: SiteLogsReadyPayload) => void
}

export function useServerLogsChannel(
  serverId: Ref<string>,
  callbacks: ServerLogsChannelCallbacks,
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

    Object.values(SERVER_LOGS_BROADCAST_EVENTS).forEach((event) => {
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

    const channelName = privateServerLogsChannel(nextServerId)
    const channel = echo.private(channelName)
    activeChannelName = channelName
    activeChannel = channel as typeof activeChannel

    if (callbacks.onServerLogsReady !== undefined) {
      channel.listen(`.${SERVER_LOGS_BROADCAST_EVENTS.serverReady}`, (payload: unknown) => {
        callbacks.onServerLogsReady?.(payload as ServerLogsReadyPayload)
      })
    }

    if (callbacks.onSiteLogsReady !== undefined) {
      channel.listen(`.${SERVER_LOGS_BROADCAST_EVENTS.siteReady}`, (payload: unknown) => {
        callbacks.onSiteLogsReady?.(payload as SiteLogsReadyPayload)
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
