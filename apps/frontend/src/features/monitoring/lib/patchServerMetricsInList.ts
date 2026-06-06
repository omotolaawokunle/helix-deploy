import type { Server, ServerHealthStatus } from '@/types'

export interface ServerMetricsLivePatch {
  serverId: string
  cpuPercent?: number | null
  memoryUsedPercent?: number | null
  diskUsedPercent?: number | null
  lastCheckedAt?: string | null
}

function mergeHealthStatus(
  current: ServerHealthStatus | null,
  patch: ServerMetricsLivePatch,
): ServerHealthStatus {
  return {
    ...current,
    cpuPercent: patch.cpuPercent ?? current?.cpuPercent,
    memoryUsedPercent: patch.memoryUsedPercent ?? current?.memoryUsedPercent,
    diskUsedPercent: patch.diskUsedPercent ?? current?.diskUsedPercent,
    lastCheckedAt: patch.lastCheckedAt ?? current?.lastCheckedAt,
  }
}

export function patchServerMetricsInList(
  servers: readonly Server[],
  patch: ServerMetricsLivePatch,
): Server[] | 'missing' {
  const index = servers.findIndex(server => server.id === patch.serverId)

  if (index === -1) {
    return 'missing'
  }

  const current = servers[index]
  const nextServers = [...servers]

  nextServers[index] = {
    ...current,
    healthStatus: mergeHealthStatus(current.healthStatus, patch),
  }

  return nextServers
}
