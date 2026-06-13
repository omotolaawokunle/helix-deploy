import { api } from '@/lib/axios'
import type { LogFetchResponse } from '@/features/logs/types'
import type { EnvVarListItem, EnvVarPullPreview, EnvVarPullStrategy, GitProviderType, NginxConfig, Site } from '@/types'

interface ResourceResponse<T> {
  data: T
}

interface CollectionResponse<T> {
  data: T[]
}

export interface CreateSitePayload {
  domain?: string
  subdomainPrefix?: string
  projectId?: string
  projectDnsZoneId?: string
  autoCreateDns?: boolean
  enableSsl?: boolean
  includeWwwAlias?: boolean
  sslChallenge?: 'http-01' | 'dns-01'
  runtime: string
  deployBranch?: string
  repositoryUrl?: string
  phpVersion?: string
  appPort?: number
}

export async function createSite(serverId: string, payload: CreateSitePayload): Promise<Site> {
  const response = await api.post<ResourceResponse<Site>>(
    `/api/v1/servers/${serverId}/sites`,
    payload,
  )

  return response.data.data
}

export async function fetchSite(siteId: string): Promise<Site> {
  const response = await api.get<ResourceResponse<Site>>(`/api/v1/sites/${siteId}`)

  return response.data.data
}

export interface GitProviderConnection {
  provider: GitProviderType
  label: string | null
  configuredAt: string | null
  lastUsedAt: string | null
}

export interface GitRepositoryOption {
  id: string
  name: string
  fullName: string
  cloneUrl: string
  defaultBranch: string
  isPrivate: boolean
}

export interface GitBranchOption {
  name: string
  isDefault: boolean
}

export async function fetchGitProviders(organizationId: string): Promise<GitProviderConnection[]> {
  const response = await api.get<CollectionResponse<GitProviderConnection>>(
    `/api/v1/organizations/${organizationId}/git-providers`,
  )

  return response.data.data
}

export async function storeGitProviderToken(
  organizationId: string,
  payload: { provider: GitProviderType; token: string },
): Promise<void> {
  await api.post(`/api/v1/organizations/${organizationId}/git-providers`, payload)
}

export async function deleteGitProviderToken(
  organizationId: string,
  provider: GitProviderType,
): Promise<void> {
  await api.delete(`/api/v1/organizations/${organizationId}/git-providers/${provider}`)
}

export async function fetchGitRepositories(
  organizationId: string,
  provider: GitProviderType,
): Promise<GitRepositoryOption[]> {
  const response = await api.get<CollectionResponse<GitRepositoryOption>>(
    `/api/v1/organizations/${organizationId}/git-providers/${provider}/repositories`,
  )

  return response.data.data
}

export async function fetchGitBranches(
  organizationId: string,
  provider: GitProviderType,
  owner: string,
  repo: string,
): Promise<GitBranchOption[]> {
  const response = await api.get<CollectionResponse<GitBranchOption>>(
    `/api/v1/organizations/${organizationId}/git-providers/${provider}/repositories/${owner}/${repo}/branches`,
  )

  return response.data.data
}

export async function updateSite(
  siteId: string,
  payload: Partial<Pick<
    Site,
    | 'deployBranch'
    | 'preDeployScript'
    | 'postDeployScript'
    | 'preBuildScript'
    | 'buildStrategy'
    | 'buildRunnerId'
    | 'runMigrations'
    | 'dockerImage'
    | 'dockerRegistry'
    | 'dockerComposePath'
    | 'pipelineId'
    | 'repositoryUrl'
    | 'repositoryProvider'
  >>,
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

export async function fetchEnvVarsPullPreview(
  siteId: string,
  options?: { refresh?: boolean },
): Promise<EnvVarPullPreview> {
  const response = await api.get<ResourceResponse<EnvVarPullPreview>>(
    `/api/v1/sites/${siteId}/env-vars/pull`,
    {
      params: {
        refresh: options?.refresh === true ? 1 : undefined,
      },
    },
  )

  return response.data.data
}

export async function applyEnvVarsPull(
  siteId: string,
  payload: { strategy: EnvVarPullStrategy },
): Promise<void> {
  await api.post(`/api/v1/sites/${siteId}/env-vars/pull`, payload)
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

export async function fetchSiteLogs(
  siteId: string,
  options: {
    lines?: number
    refresh?: boolean
  },
): Promise<LogFetchResponse> {
  const response = await api.get<ResourceResponse<LogFetchResponse>>(`/api/v1/sites/${siteId}/logs`, {
    params: {
      lines: options.lines,
      refresh: options.refresh === true ? 1 : undefined,
    },
  })

  return response.data.data
}
