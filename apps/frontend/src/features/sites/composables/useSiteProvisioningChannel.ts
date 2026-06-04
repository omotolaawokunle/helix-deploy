import { onUnmounted } from 'vue'
import { getEcho } from '@/lib/echo'
import {
  SITE_BROADCAST_EVENTS,
  privateServerSitesChannel,
  type SiteCreatedPayload,
  type SiteProvisioningFailedPayload,
  type SiteProvisioningStartedPayload,
} from '@/features/sites/types'

export interface SiteProvisioningChannelCallbacks {
  onProvisioningStarted?: (payload: SiteProvisioningStartedPayload) => void
  onCreated?: (payload: SiteCreatedPayload) => void
  onProvisioningFailed?: (payload: SiteProvisioningFailedPayload) => void
}

export function useSiteProvisioningChannel(
  serverId: string,
  callbacks: SiteProvisioningChannelCallbacks,
): { channelName: string; disconnect: () => void } {
  const channelName = privateServerSitesChannel(serverId)
  const echo = getEcho()

  if (echo === undefined) {
    return {
      channelName,
      disconnect: (): void => undefined,
    }
  }

  const channel = echo.private(channelName)

  if (callbacks.onProvisioningStarted !== undefined) {
    channel.listen(SITE_BROADCAST_EVENTS.provisioningStarted, (payload: unknown) => {
      callbacks.onProvisioningStarted?.(payload as SiteProvisioningStartedPayload)
    })
  }

  if (callbacks.onCreated !== undefined) {
    channel.listen(SITE_BROADCAST_EVENTS.created, (payload: unknown) => {
      callbacks.onCreated?.(payload as SiteCreatedPayload)
    })
  }

  if (callbacks.onProvisioningFailed !== undefined) {
    channel.listen(SITE_BROADCAST_EVENTS.provisioningFailed, (payload: unknown) => {
      callbacks.onProvisioningFailed?.(payload as SiteProvisioningFailedPayload)
    })
  }

  const disconnect = (): void => {
    channel.stopListening(SITE_BROADCAST_EVENTS.provisioningStarted)
    channel.stopListening(SITE_BROADCAST_EVENTS.created)
    channel.stopListening(SITE_BROADCAST_EVENTS.provisioningFailed)
    echo.leave(channelName)
  }

  onUnmounted(disconnect)

  return { channelName, disconnect }
}
