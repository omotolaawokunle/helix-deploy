import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { BuildRunnerLivePatch } from '@/features/build-runners/lib/patchBuildRunnerInList'
import type { ServerMetricsLivePatch } from '@/features/monitoring/lib/patchServerMetricsInList'
import type { InstalledServiceRecord } from '@/features/servers/types'

export interface ServerServiceStatusLiveUpdate {
  serverId: string
  services: InstalledServiceRecord[]
}

export type RealtimeConnectionStatus =
  | 'unconfigured'
  | 'connecting'
  | 'connected'
  | 'disconnected'
  | 'unavailable'

export const useRealtimeStore = defineStore('realtime', () => {
  const dashboardRefreshToken = ref(0)
  const buildRunnerPatchSeq = ref(0)
  const buildRunnerPatch = ref<BuildRunnerLivePatch | null>(null)
  const serverMetricsPatchSeq = ref(0)
  const serverMetricsPatch = ref<ServerMetricsLivePatch | null>(null)
  const deletedServerId = ref<string | null>(null)
  const serverInventoryRefreshId = ref<string | null>(null)
  const serverServiceStatusUpdateSeq = ref(0)
  const serverServiceStatusUpdate = ref<ServerServiceStatusLiveUpdate | null>(null)
  const connectionStatus = ref<RealtimeConnectionStatus>('unconfigured')

  function requestDashboardRefresh(): void {
    dashboardRefreshToken.value += 1
  }

  function emitBuildRunnerPatch(patch: BuildRunnerLivePatch): void {
    buildRunnerPatch.value = patch
    buildRunnerPatchSeq.value += 1
  }

  function emitServerMetricsPatch(patch: ServerMetricsLivePatch): void {
    serverMetricsPatch.value = patch
    serverMetricsPatchSeq.value += 1
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

  function signalServerInventoryRefresh(serverId: string): void {
    serverInventoryRefreshId.value = serverId
  }

  function consumeServerInventoryRefresh(serverId: string): boolean {
    if (serverInventoryRefreshId.value !== serverId) {
      return false
    }

    serverInventoryRefreshId.value = null

    return true
  }

  function emitServerServiceStatusUpdate(payload: ServerServiceStatusLiveUpdate): void {
    serverServiceStatusUpdate.value = payload
    serverServiceStatusUpdateSeq.value += 1
  }

  function setConnectionStatus(status: RealtimeConnectionStatus): void {
    connectionStatus.value = status
  }

  function markRealtimeUnconfigured(): void {
    connectionStatus.value = 'unconfigured'
  }

  return {
    dashboardRefreshToken,
    buildRunnerPatchSeq,
    buildRunnerPatch,
    serverMetricsPatchSeq,
    serverMetricsPatch,
    deletedServerId,
    serverInventoryRefreshId,
    serverServiceStatusUpdateSeq,
    serverServiceStatusUpdate,
    connectionStatus,
    requestDashboardRefresh,
    emitBuildRunnerPatch,
    emitServerMetricsPatch,
    signalServerDeleted,
    consumeServerDeleted,
    signalServerInventoryRefresh,
    consumeServerInventoryRefresh,
    emitServerServiceStatusUpdate,
    setConnectionStatus,
    markRealtimeUnconfigured,
  }
})
