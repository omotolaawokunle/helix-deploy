import { api } from '@/lib/axios'
import type { Server } from '@/types'
import type {
  EnvironmentOption,
  ProjectOption,
  ProvisionServerPayload,
  ProvisionServerResponse,
  RegisterServerPayload,
  ServerRegistrationResponse,
} from '@/features/servers/types'

interface PaginatedResponse<T> {
  data: T[]
}

export async function fetchServers(organizationId: string): Promise<Server[]> {
  const response = await api.get<PaginatedResponse<Server>>(
    `/api/v1/organizations/${organizationId}/servers`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchServer(serverId: string): Promise<Server> {
  const response = await api.get<Server>(`/api/v1/servers/${serverId}`)

  return response.data
}

export async function registerServer(
  organizationId: string,
  payload: RegisterServerPayload,
): Promise<ServerRegistrationResponse> {
  const response = await api.post<ServerRegistrationResponse>(
    `/api/v1/organizations/${organizationId}/servers`,
    payload,
  )

  return response.data
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
