import { defineStore } from 'pinia'
import { ref } from 'vue'

export type RealtimeConnectionStatus =
  | 'unconfigured'
  | 'connecting'
  | 'connected'
  | 'disconnected'
  | 'unavailable'

export const useRealtimeStore = defineStore('realtime', () => {
  const dashboardRefreshToken = ref(0)
  const buildRunnersRefreshToken = ref(0)
  const deletedServerId = ref<string | null>(null)
  const connectionStatus = ref<RealtimeConnectionStatus>('unconfigured')

  function requestDashboardRefresh(): void {
    dashboardRefreshToken.value += 1
  }

  function requestBuildRunnersRefresh(): void {
    buildRunnersRefreshToken.value += 1
  }

  function signalServerDeleted(serverId: string): void {
    deletedServerId.value = serverId
  }

  function consumeServerDeleted(serverId: string): boolean {
    if (deletedServerId.value !== serverId) {
      return false
    }

    deletedServerId.value = null

    return true
  }

  function setConnectionStatus(status: RealtimeConnectionStatus): void {
    connectionStatus.value = status
  }

  function markRealtimeUnconfigured(): void {
    connectionStatus.value = 'unconfigured'
  }

  return {
    dashboardRefreshToken,
    buildRunnersRefreshToken,
    deletedServerId,
    connectionStatus,
    requestDashboardRefresh,
    requestBuildRunnersRefresh,
    signalServerDeleted,
    consumeServerDeleted,
    setConnectionStatus,
    markRealtimeUnconfigured,
  }
})
