import { onUnmounted } from 'vue'
import { getEcho } from '@/lib/echo'
import {
  SITE_BROADCAST_EVENTS,
  privateServerSitesChannel,
  type SiteCreatedPayload,
  type SiteDnsSslStatusChangedPayload,
  type SiteProvisioningFailedPayload,
  type SiteProvisioningStartedPayload,
} from '@/features/sites/types'

export interface SiteProvisioningChannelCallbacks {
  onProvisioningStarted?: (payload: SiteProvisioningStartedPayload) => void
  onCreated?: (payload: SiteCreatedPayload) => void
  onProvisioningFailed?: (payload: SiteProvisioningFailedPayload) => void
  onDnsSslStatusChanged?: (payload: SiteDnsSslStatusChangedPayload) => void
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

  if (callbacks.onDnsSslStatusChanged !== undefined) {
    channel.listen(SITE_BROADCAST_EVENTS.dnsSslStatusChanged, (payload: unknown) => {
      callbacks.onDnsSslStatusChanged?.(payload as SiteDnsSslStatusChangedPayload)
    })
  }

  const disconnect = (): void => {
    channel.stopListening(SITE_BROADCAST_EVENTS.provisioningStarted)
    channel.stopListening(SITE_BROADCAST_EVENTS.created)
    channel.stopListening(SITE_BROADCAST_EVENTS.provisioningFailed)
    channel.stopListening(SITE_BROADCAST_EVENTS.dnsSslStatusChanged)
    echo.leave(channelName)
  }

  onUnmounted(disconnect)

  return { channelName, disconnect }
}

export function patchSiteDnsSslFromBroadcast(
  site: { id: string; dnsStatus?: string | null; dnsError?: string | null; sslStatus?: string | null; sslError?: string | null },
  payload: SiteDnsSslStatusChangedPayload,
): void {
  if (site.id !== payload.siteId) {
    return
  }

  site.dnsStatus = payload.dnsStatus
  site.dnsError = payload.dnsError
  site.sslStatus = payload.sslStatus
  site.sslError = payload.sslError
}
