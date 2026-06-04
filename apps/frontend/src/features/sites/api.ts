import { api } from '@/lib/axios'
import type { EnvVarListItem, NginxConfig, Site } from '@/types'

interface ResourceResponse<T> {
  data: T
}

interface CollectionResponse<T> {
  data: T[]
}

export async function fetchSite(siteId: string): Promise<Site> {
  const response = await api.get<ResourceResponse<Site>>(`/api/v1/sites/${siteId}`)

  return response.data.data
}

export async function updateSite(
  siteId: string,
  payload: Partial<Pick<Site, 'deployBranch' | 'deployScript' | 'runMigrations' | 'dockerImage' | 'dockerRegistry' | 'dockerComposePath'>>,
): Promise<Site> {
  const response = await api.patch<ResourceResponse<Site>>(`/api/v1/sites/${siteId}`, payload)

  return response.data.data
}

export async function deleteSite(siteId: string): Promise<void> {
  await api.delete(`/api/v1/sites/${siteId}`)
}

export async function fetchServerSites(serverId: string): Promise<Site[]> {
  const response = await api.get<CollectionResponse<Site>>(`/api/v1/servers/${serverId}/sites`, {
    params: { per_page: 100 },
  })

  return response.data.data
}

export async function fetchOrgSites(organizationId: string): Promise<Site[]> {
  const response = await api.get<CollectionResponse<Site>>(
    `/api/v1/organizations/${organizationId}/sites`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchEnvVars(siteId: string): Promise<EnvVarListItem[]> {
  const response = await api.get<CollectionResponse<EnvVarListItem>>(
    `/api/v1/sites/${siteId}/env-vars`,
  )

  return response.data.data
}

export async function createEnvVar(
  siteId: string,
  payload: { key: string; value: string },
): Promise<EnvVarListItem> {
  const response = await api.post<ResourceResponse<EnvVarListItem>>(
    `/api/v1/sites/${siteId}/env-vars`,
    payload,
  )

  return response.data.data
}

export async function updateEnvVar(
  siteId: string,
  credentialId: string,
  payload: { value: string },
): Promise<EnvVarListItem> {
  const response = await api.patch<ResourceResponse<EnvVarListItem>>(
    `/api/v1/sites/${siteId}/env-vars/${credentialId}`,
    payload,
  )

  return response.data.data
}

export async function deleteEnvVar(siteId: string, credentialId: string): Promise<void> {
  await api.delete(`/api/v1/sites/${siteId}/env-vars/${credentialId}`)
}

export async function revealEnvVar(
  siteId: string,
  credentialId: string,
): Promise<{ id: string; key: string; value: string }> {
  const response = await api.get<ResourceResponse<{ id: string; key: string; value: string }>>(
    `/api/v1/sites/${siteId}/env-vars/${credentialId}/reveal`,
  )

  return response.data.data
}

export async function syncEnvVars(siteId: string): Promise<void> {
  await api.post(`/api/v1/sites/${siteId}/env-vars/sync`)
}

export async function fetchNginxConfig(siteId: string): Promise<NginxConfig> {
  const response = await api.get<ResourceResponse<NginxConfig>>(
    `/api/v1/sites/${siteId}/nginx-config`,
  )

  return response.data.data
}

export async function saveNginxConfig(siteId: string, config: string): Promise<NginxConfig> {
  const response = await api.put<ResourceResponse<NginxConfig>>(
    `/api/v1/sites/${siteId}/nginx-config`,
    { config },
  )

  return response.data.data
}
