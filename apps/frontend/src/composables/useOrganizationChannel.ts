import { onUnmounted, watch, type Ref } from 'vue'
import { DEPLOYMENT_BROADCAST_EVENTS } from '@/features/deployments/types'
import {
  ORGANIZATION_BROADCAST_EVENTS,
  privateOrganizationChannel,
  type BuildRunnerSlotsPayload,
  type BuildRunnerStatusPayload,
  type DaemonChangedPayload,
  type ServerConnectedPayload,
  type ServerConnectionFailedPayload,
  type ServerDeletedPayload,
  type ServerHealthChangedPayload,
  type ServerInventoryDiscoveredPayload,
  type ServerMetricsUpdatedPayload,
  type ServerServiceStatusUpdatedPayload,
} from '@/features/realtime/types'
import { getEcho, initEcho } from '@/lib/echo'

export interface OrganizationChannelCallbacks {
  onServerConnected?: (payload: ServerConnectedPayload) => void
  onServerConnectionFailed?: (payload: ServerConnectionFailedPayload) => void
  onServerHealthChanged?: (payload: ServerHealthChangedPayload) => void
  onServerMetricsUpdated?: (payload: ServerMetricsUpdatedPayload) => void
  onServerDeleted?: (payload: ServerDeletedPayload) => void
  onServerInventoryDiscovered?: (payload: ServerInventoryDiscoveredPayload) => void
  onDeploymentActivity?: () => void
  onBuildRunnerStatus?: (payload: BuildRunnerStatusPayload) => void
  onBuildRunnerSlotsUpdated?: (payload: BuildRunnerSlotsPayload) => void
  onDaemonChanged?: (payload: DaemonChangedPayload) => void
  onServerServiceStatusUpdated?: (payload: ServerServiceStatusUpdatedPayload) => void
}

export function useOrganizationChannel(
  organizationId: Ref<string | null | undefined>,
  callbacks: OrganizationChannelCallbacks,
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

    Object.values(ORGANIZATION_BROADCAST_EVENTS).forEach((event) => {
      activeChannel?.stopListening(`.${event}`)
    })
    Object.values(DEPLOYMENT_BROADCAST_EVENTS).forEach((event) => {
      activeChannel?.stopListening(`.${event}`)
    })

    echo.leave(activeChannelName)
    activeChannelName = null
    activeChannel = null
  }

  const connect = (orgId: string): void => {
    disconnect()

    initEcho()
    const echo = getEcho()

    if (echo === undefined) {
      return
    }

    const channelName = privateOrganizationChannel(orgId)
    const channel = echo.private(channelName)
    activeChannelName = channelName
    activeChannel = channel as typeof activeChannel

    if (callbacks.onServerConnected !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.serverConnected}`, (payload: unknown) => {
        callbacks.onServerConnected?.(payload as ServerConnectedPayload)
      })
    }

    if (callbacks.onServerConnectionFailed !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.serverConnectionFailed}`, (payload: unknown) => {
        callbacks.onServerConnectionFailed?.(payload as ServerConnectionFailedPayload)
      })
    }

    if (callbacks.onServerHealthChanged !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.serverHealthChanged}`, (payload: unknown) => {
        callbacks.onServerHealthChanged?.(payload as ServerHealthChangedPayload)
      })
    }

    if (callbacks.onServerMetricsUpdated !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.serverMetricsUpdated}`, (payload: unknown) => {
        callbacks.onServerMetricsUpdated?.(payload as ServerMetricsUpdatedPayload)
      })
    }

    if (callbacks.onServerDeleted !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.serverDeleted}`, (payload: unknown) => {
        callbacks.onServerDeleted?.(payload as ServerDeletedPayload)
      })
    }

    if (callbacks.onServerInventoryDiscovered !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.serverInventoryDiscovered}`, (payload: unknown) => {
        callbacks.onServerInventoryDiscovered?.(payload as ServerInventoryDiscoveredPayload)
      })
    }

    if (callbacks.onDeploymentActivity !== undefined) {
      const notify = (): void => {
        callbacks.onDeploymentActivity?.()
      }

      channel.listen(`.${DEPLOYMENT_BROADCAST_EVENTS.started}`, notify)
      channel.listen(`.${DEPLOYMENT_BROADCAST_EVENTS.completed}`, notify)
      channel.listen(`.${DEPLOYMENT_BROADCAST_EVENTS.failed}`, notify)
      channel.listen(`.${DEPLOYMENT_BROADCAST_EVENTS.rolledBack}`, notify)
    }

    if (callbacks.onBuildRunnerStatus !== undefined) {
      const handle = (payload: unknown): void => {
        callbacks.onBuildRunnerStatus?.(payload as BuildRunnerStatusPayload)
      }

      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.buildRunnerOnline}`, handle)
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.buildRunnerOffline}`, handle)
    }

    if (callbacks.onBuildRunnerSlotsUpdated !== undefined) {
      channel.listen(
        `.${ORGANIZATION_BROADCAST_EVENTS.buildRunnerSlotsUpdated}`,
        (payload: unknown) => {
          callbacks.onBuildRunnerSlotsUpdated?.(payload as BuildRunnerSlotsPayload)
        },
      )
    }

    if (callbacks.onDaemonChanged !== undefined) {
      channel.listen(`.${ORGANIZATION_BROADCAST_EVENTS.daemonChanged}`, (payload: unknown) => {
        callbacks.onDaemonChanged?.(payload as DaemonChangedPayload)
      })
    }

    if (callbacks.onServerServiceStatusUpdated !== undefined) {
      channel.listen(
        `.${ORGANIZATION_BROADCAST_EVENTS.serverServiceStatusUpdated}`,
        (payload: unknown) => {
          callbacks.onServerServiceStatusUpdated?.(payload as ServerServiceStatusUpdatedPayload)
        },
      )
    }
  }

  watch(
    organizationId,
    (orgId) => {
      if (orgId === null || orgId === undefined || orgId === '') {
        disconnect()

        return
      }

      connect(orgId)
    },
    { immediate: true },
  )

  onUnmounted(disconnect)

  return { disconnect }
}
