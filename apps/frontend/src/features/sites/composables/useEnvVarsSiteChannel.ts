import { onUnmounted, watch, type Ref } from 'vue'
import {
  SITE_BROADCAST_EVENTS,
  privateServerSitesChannel,
  type EnvVarPullPreviewReadyPayload,
  type EnvVarsPulledPayload,
} from '@/features/sites/types'
import { getEcho, initEcho } from '@/lib/echo'

export interface EnvVarsSiteChannelCallbacks {
  onPullPreviewReady?: (payload: EnvVarPullPreviewReadyPayload) => void
  onPulled?: (payload: EnvVarsPulledPayload) => void
}

export function useEnvVarsSiteChannel(
  serverId: Ref<string | undefined>,
  callbacks: EnvVarsSiteChannelCallbacks,
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

    activeChannel.stopListening(`.${SITE_BROADCAST_EVENTS.envVarsPullPreviewReady}`)
    activeChannel.stopListening(`.${SITE_BROADCAST_EVENTS.envVarsPulled}`)
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

    const channelName = privateServerSitesChannel(nextServerId)
    const channel = echo.private(channelName)
    activeChannelName = channelName
    activeChannel = channel as typeof activeChannel

    if (callbacks.onPullPreviewReady !== undefined) {
      channel.listen(`.${SITE_BROADCAST_EVENTS.envVarsPullPreviewReady}`, (payload: unknown) => {
        callbacks.onPullPreviewReady?.(payload as EnvVarPullPreviewReadyPayload)
      })
    }

    if (callbacks.onPulled !== undefined) {
      channel.listen(`.${SITE_BROADCAST_EVENTS.envVarsPulled}`, (payload: unknown) => {
        callbacks.onPulled?.(payload as EnvVarsPulledPayload)
      })
    }
  }

  watch(
    serverId,
    (nextServerId) => {
      if (nextServerId === undefined || nextServerId === '') {
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
