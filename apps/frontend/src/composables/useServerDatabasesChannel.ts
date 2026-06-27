import { onUnmounted, watch, type Ref } from 'vue'
import {
  privateServerDatabasesChannel,
  SERVER_DATABASE_BROADCAST_EVENTS,
  type ServerDatabaseBrowseReadyPayload,
  type SiteDatabaseBrowseReadyPayload,
} from '@/features/databases/types'
import { getEcho, initEcho } from '@/lib/echo'

export interface ServerDatabasesChannelCallbacks {
  onServerDatabaseReady?: (payload: ServerDatabaseBrowseReadyPayload) => void
  onSiteDatabaseReady?: (payload: SiteDatabaseBrowseReadyPayload) => void
}

export function useServerDatabasesChannel(
  serverId: Ref<string>,
  callbacks: ServerDatabasesChannelCallbacks,
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

    Object.values(SERVER_DATABASE_BROADCAST_EVENTS).forEach((event) => {
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

    const channelName = privateServerDatabasesChannel(nextServerId)
    const channel = echo.private(channelName)
    activeChannelName = channelName
    activeChannel = channel as typeof activeChannel

    if (callbacks.onServerDatabaseReady !== undefined) {
      channel.listen(`.${SERVER_DATABASE_BROADCAST_EVENTS.serverReady}`, (payload: unknown) => {
        callbacks.onServerDatabaseReady?.(payload as ServerDatabaseBrowseReadyPayload)
      })
    }

    if (callbacks.onSiteDatabaseReady !== undefined) {
      channel.listen(`.${SERVER_DATABASE_BROADCAST_EVENTS.siteReady}`, (payload: unknown) => {
        callbacks.onSiteDatabaseReady?.(payload as SiteDatabaseBrowseReadyPayload)
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
