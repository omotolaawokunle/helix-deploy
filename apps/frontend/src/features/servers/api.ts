import { api } from '@/lib/axios'
import type { LogFetchResponse, ServerLogType } from '@/features/logs/types'
import type { Server, ServerGroup } from '@/types'
import type {
  EnvironmentOption,
  InstalledServiceRecord,
  ProjectOption,
  ProvisionServerPayload,
  ProvisionServerResponse,
  RegisterServerPayload,
  ServerRegistrationResponse,
  ServerSslOverview,
} from '@/features/servers/types'

interface PaginatedResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export interface FetchServersOptions {
  tags?: string[]
  serverGroupId?: string
}

export type CloudProviderType = 'hetzner' | 'digitalocean' | 'aws'

export interface CloudProviderConnection {
  provider: CloudProviderType
  label: string
  configured: boolean
}

export interface CloudInstance {
  id: string
  name: string
  ipAddress: string | null
  region: string | null
  serverType: string | null
  status: string
  os: string | null
}

interface CollectionResponse<T> {
  data: T[]
}

export async function fetchCloudProviders(organizationId: string): Promise<CloudProviderConnection[]> {
  const response = await api.get<CollectionResponse<CloudProviderConnection>>(
    `/api/v1/organizations/${organizationId}/cloud-providers`,
  )

  return response.data.data
}

export async function storeCloudProviderCredential(
  organizationId: string,
  payload:
    | { provider: 'hetzner' | 'digitalocean'; token: string }
    | { provider: 'aws'; accessKeyId: string; secretAccessKey: string; region: string },
): Promise<void> {
  await api.post(`/api/v1/organizations/${organizationId}/cloud-providers`, payload)
}

export async function deleteCloudProviderCredential(
  organizationId: string,
  provider: CloudProviderType,
): Promise<void> {
  await api.delete(`/api/v1/organizations/${organizationId}/cloud-providers/${provider}`)
}

export async function fetchCloudInstances(
  organizationId: string,
  provider: CloudProviderType,
): Promise<CloudInstance[]> {
  const response = await api.get<CollectionResponse<CloudInstance>>(
    `/api/v1/organizations/${organizationId}/cloud-providers/${provider}/instances`,
  )

  return response.data.data
}

export async function fetchServers(
  organizationId: string,
  options: FetchServersOptions = {},
): Promise<Server[]> {
  const params: Record<string, string | number | string[]> = { per_page: 100 }

  if (options.tags !== undefined && options.tags.length > 0) {
    params['filter[tags]'] = options.tags.join(',')
  }

  if (options.serverGroupId !== undefined) {
    params['filter[server_group_id]'] = options.serverGroupId
  }

  const response = await api.get<PaginatedResponse<Server>>(
    `/api/v1/organizations/${organizationId}/servers`,
    { params },
  )

  return response.data.data
}

export async function deleteServer(serverId: string): Promise<void> {
  await api.delete(`/api/v1/servers/${serverId}`)
}

export async function fetchServer(serverId: string): Promise<Server> {
  const response = await api.get<ResourceResponse<Server>>(`/api/v1/servers/${serverId}`)

  return response.data.data
}

export async function registerServer(
  organizationId: string,
  payload: RegisterServerPayload,
): Promise<ServerRegistrationResponse> {
  const response = await api.post<ResourceResponse<ServerRegistrationResponse>>(
    `/api/v1/organizations/${organizationId}/servers`,
    payload,
  )

  return response.data.data
}

export async function testServerConnection(serverId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/test-connection`)
}

export async function provisionServer(
  serverId: string,
  payload: ProvisionServerPayload,
): Promise<ProvisionServerResponse> {
  const response = await api.post<ProvisionServerResponse>(
    `/api/v1/servers/${serverId}/provision`,
    payload,
  )

  return response.data
}

export async function fetchProjects(organizationId: string): Promise<ProjectOption[]> {
  const response = await api.get<PaginatedResponse<ProjectOption>>(
    `/api/v1/organizations/${organizationId}/projects`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchProjectEnvironments(projectId: string): Promise<EnvironmentOption[]> {
  const response = await api.get<PaginatedResponse<EnvironmentOption>>(
    `/api/v1/projects/${projectId}/environments`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export interface CreateServerGroupPayload {
  name: string
  description?: string | null
}

export interface UpdateServerGroupPayload {
  name?: string
  description?: string | null
}

export async function fetchServerGroups(organizationId: string): Promise<ServerGroup[]> {
  const response = await api.get<PaginatedResponse<ServerGroup>>(
    `/api/v1/organizations/${organizationId}/server-groups`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchServerGroup(serverGroupId: string): Promise<ServerGroup> {
  const response = await api.get<ServerGroup>(`/api/v1/server-groups/${serverGroupId}`)

  return response.data
}

export async function createServerGroup(
  organizationId: string,
  payload: CreateServerGroupPayload,
): Promise<ServerGroup> {
  const response = await api.post<ServerGroup>(
    `/api/v1/organizations/${organizationId}/server-groups`,
    payload,
  )

  return response.data
}

export async function updateServerGroup(
  serverGroupId: string,
  payload: UpdateServerGroupPayload,
): Promise<ServerGroup> {
  const response = await api.patch<ServerGroup>(
    `/api/v1/server-groups/${serverGroupId}`,
    payload,
  )

  return response.data
}

export async function deleteServerGroup(serverGroupId: string): Promise<void> {
  await api.delete(`/api/v1/server-groups/${serverGroupId}`)
}

export async function syncServerGroupServers(
  serverGroupId: string,
  serverIds: string[],
): Promise<ServerGroup> {
  const response = await api.put<ServerGroup>(
    `/api/v1/server-groups/${serverGroupId}/servers`,
    { serverIds },
  )

  return response.data
}

export async function fetchServerServices(serverId: string): Promise<InstalledServiceRecord[]> {
  const response = await api.get<CollectionResponse<InstalledServiceRecord>>(
    `/api/v1/servers/${serverId}/services`,
  )

  return response.data.data
}

export async function syncServerServiceStatuses(serverId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/services/sync-status`)
}

export async function startServerService(serverId: string, serviceKey: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/services/${serviceKey}/start`)
}

export async function stopServerService(serverId: string, serviceKey: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/services/${serviceKey}/stop`)
}

export async function restartServerService(serverId: string, serviceKey: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/services/${serviceKey}/restart`)
}

export async function fetchServerSslCertificates(serverId: string): Promise<ServerSslOverview> {
  const response = await api.get<ResourceResponse<ServerSslOverview>>(
    `/api/v1/servers/${serverId}/ssl-certificates`,
  )

  return response.data.data
}

export async function syncServerSslCertificates(serverId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/ssl-certificates/sync`)
}

export async function adoptServerSslCertificates(serverId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/ssl-certificates/adopt`)
}

export async function renewServerSslCertificates(serverId: string): Promise<void> {
  await api.post(`/api/v1/servers/${serverId}/ssl-certificates/renew`)
}

export async function fetchServerLogs(
  serverId: string,
  options: {
    type: ServerLogType
    lines?: number
    refresh?: boolean
  },
): Promise<LogFetchResponse> {
  const response = await api.get<ResourceResponse<LogFetchResponse>>(
    `/api/v1/servers/${serverId}/logs`,
    {
      params: {
        type: options.type,
        lines: options.lines,
        refresh: options.refresh === true ? 1 : undefined,
      },
    },
  )

  return response.data.data
}
