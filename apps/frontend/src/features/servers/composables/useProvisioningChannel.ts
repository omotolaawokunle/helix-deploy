import { onUnmounted } from 'vue'
import { getEcho } from '@/lib/echo'
import {
  PROVISIONING_BROADCAST_EVENTS,
  privateServerProvisioningChannel,
  type ProvisioningCompletedPayload,
  type ProvisioningLogLinePayload,
} from '@/features/servers/types'

export interface ProvisioningChannelCallbacks {
  onLogLine?: (payload: ProvisioningLogLinePayload) => void
  onCompleted?: (payload: ProvisioningCompletedPayload) => void
}

export function useProvisioningChannel(
  serverId: string,
  callbacks: ProvisioningChannelCallbacks,
): { channelName: string; disconnect: () => void } {
  const channelName = privateServerProvisioningChannel(serverId)
  const echo = getEcho()

  if (echo === undefined) {
    return {
      channelName,
      disconnect: (): void => undefined,
    }
  }

  const channel = echo.private(channelName)

  if (callbacks.onLogLine !== undefined) {
    channel.listen(PROVISIONING_BROADCAST_EVENTS.logLine, (payload: unknown) => {
      callbacks.onLogLine?.(payload as ProvisioningLogLinePayload)
    })
  }

  if (callbacks.onCompleted !== undefined) {
    channel.listen(PROVISIONING_BROADCAST_EVENTS.completed, (payload: unknown) => {
      callbacks.onCompleted?.(payload as ProvisioningCompletedPayload)
    })
  }

  const disconnect = (): void => {
    channel.stopListening(PROVISIONING_BROADCAST_EVENTS.logLine)
    channel.stopListening(PROVISIONING_BROADCAST_EVENTS.completed)
    echo.leave(channelName)
  }

  onUnmounted(disconnect)

  return { channelName, disconnect }
}
