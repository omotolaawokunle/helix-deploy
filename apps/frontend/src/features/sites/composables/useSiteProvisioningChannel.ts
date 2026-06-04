import { onUnmounted } from 'vue'
import {
  SITE_BROADCAST_EVENTS,
  privateServerSitesChannel,
  type SiteBroadcastEventName,
  type SiteCreatedPayload,
  type SiteProvisioningFailedPayload,
  type SiteProvisioningStartedPayload,
} from '@/features/sites/types'

export interface SiteProvisioningChannelCallbacks {
  onProvisioningStarted?: (payload: SiteProvisioningStartedPayload) => void
  onCreated?: (payload: SiteCreatedPayload) => void
  onProvisioningFailed?: (payload: SiteProvisioningFailedPayload) => void
}

type EchoChannel = {
  listen: (event: SiteBroadcastEventName, callback: (payload: unknown) => void) => EchoChannel
  stopListening: (event: SiteBroadcastEventName) => void
}

type EchoInstance = {
  private: (channel: string) => EchoChannel
  leave: (channel: string) => void
}

declare global {
  interface Window {
    Echo?: EchoInstance
  }
}

/**
 * Subscribe to site provisioning broadcasts on the server sites channel.
 * Requires Laravel Echo + Reverb to be configured on window.Echo.
 */
export function useSiteProvisioningChannel(
  serverId: string,
  callbacks: SiteProvisioningChannelCallbacks,
): { channelName: string; disconnect: () => void } {
  const channelName = privateServerSitesChannel(serverId)
  const echo = window.Echo

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
