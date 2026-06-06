import { computed, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'
import { useOrganizationChannel } from '@/composables/useOrganizationChannel'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import { disconnectEcho, initEcho, subscribeEchoConnectionState } from '@/lib/echo'
import { useRealtimeStore } from '@/stores/useRealtimeStore'
import { ServerStatus } from '@/types'

export function useOrganizationRealtime(): void {
  const authStore = useAuthStore()
  const serversStore = useServersStore()
  const realtimeStore = useRealtimeStore()
  const route = useRoute()

  const organizationId = computed(() => authStore.currentOrg?.id ?? null)

  const echo = initEcho()

  if (echo === undefined) {
    realtimeStore.markRealtimeUnconfigured()
  } else {
    const unsubscribeConnectionState = subscribeEchoConnectionState((state) => {
      realtimeStore.setConnectionStatus(state)
    })

    onUnmounted(() => {
      unsubscribeConnectionState()
    })
  }

  useOrganizationChannel(organizationId, {
    onServerConnected: (payload) => {
      const previous = serversStore.servers.find(server => server.id === payload.serverId)
      const wasConnecting = previous?.status === ServerStatus.Connecting

      serversStore.applyServerUpdate({
        serverId: payload.serverId,
        status: payload.status,
        os: payload.os,
        phpVersion: payload.phpVersion,
        nodeVersion: payload.nodeVersion,
      })

      const onServerDetail = route.name === 'server-detail' && route.params.id === payload.serverId

      if (wasConnecting && onServerDetail) {
        const hostname = previous?.hostname ?? 'Server'
        toast.success(`${hostname} is online.`, {
          description: 'SSH verified. Scanning installed services and existing sites.',
        })
      }
    },
    onServerInventoryDiscovered: (payload) => {
      serversStore.applyServerUpdate({
        serverId: payload.serverId,
        installedServices: payload.installedServices,
      })
      realtimeStore.signalServerInventoryRefresh(payload.serverId)

      const onServerDetail = route.name === 'server-detail' && route.params.id === payload.serverId

      if (!onServerDetail) {
        return
      }

      const siteSummary = payload.sitesCreated + payload.sitesUpdated

      if (siteSummary > 0) {
        toast.success('Server inventory updated.', {
          description: `${siteSummary} site${siteSummary === 1 ? '' : 's'} synced from the server.`,
        })
      } else if (payload.discoveredSiteCount === 0) {
        toast.message('Server inventory updated.', {
          description: 'Installed services detected. No nginx sites found to import.',
        })
      }
    },
    onServerConnectionFailed: (payload) => {
      serversStore.applyServerUpdate({
        serverId: payload.serverId,
        status: payload.status,
      })

      const onServerDetail = route.name === 'server-detail' && route.params.id === payload.serverId

      if (onServerDetail) {
        toast.error('Connection verification failed.', {
          description: payload.reason ?? 'Check SSH credentials and network access, then test again.',
        })
      }
    },
    onServerHealthChanged: (payload) => {
      serversStore.applyServerUpdate({
        serverId: payload.serverId,
        status: payload.currentStatus,
      })
    },
    onServerDeleted: (payload) => {
      serversStore.removeServer(payload.serverId)
      realtimeStore.signalServerDeleted(payload.serverId)
    },
    onDeploymentActivity: () => {
      realtimeStore.requestDashboardRefresh()
    },
    onBuildRunnerStatus: (payload) => {
      realtimeStore.emitBuildRunnerPatch({
        runnerId: payload.runnerId,
        status: payload.status,
        activeBuilds: payload.activeBuilds,
        maxConcurrentBuilds: payload.maxConcurrentBuilds,
        availableSlots: payload.availableSlots,
      })
    },
    onBuildRunnerSlotsUpdated: (payload) => {
      realtimeStore.emitBuildRunnerPatch(payload)
    },
  })

  onUnmounted(() => {
    if (!authStore.isAuthenticated) {
      disconnectEcho()
    }
  })
}
