import { api } from '@/lib/axios'
import type { DaemonRecord } from '@/types'

interface CollectionResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export interface DaemonLogsResponse {
  status: 'loading' | 'ready' | 'failed'
  lines: string[]
  message?: string | null
}

export async function fetchDaemons(serverId: string): Promise<DaemonRecord[]> {
  const response = await api.get<CollectionResponse<DaemonRecord>>(
    `/api/v1/servers/${serverId}/daemons`,
  )

  return response.data.data
}

export async function createDaemon(
  serverId: string,
  payload: {
    name: string
    command: string
    directory: string
    user: string
    processes: number
  },
): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/daemons`, payload)
}

export async function startDaemon(serverId: string, daemonId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/daemons/${daemonId}/start`)
}

export async function stopDaemon(serverId: string, daemonId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/daemons/${daemonId}/stop`)
}

export async function restartDaemon(serverId: string, daemonId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/daemons/${daemonId}/restart`)
}

export async function deleteDaemon(serverId: string, daemonId: string): Promise<void> {
  await api.delete(`/api/v1/servers/${serverId}/daemons/${daemonId}`)
}

export async function fetchDaemonLogs(
  serverId: string,
  daemonId: string,
): Promise<DaemonLogsResponse> {
  const response = await api.get<ResourceResponse<DaemonLogsResponse>>(
    `/api/v1/servers/${serverId}/daemons/${daemonId}/logs`,
  )

  return response.data.data
}
