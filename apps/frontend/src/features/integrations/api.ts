import { api } from '@/lib/axios'
import type {
  CloudflareConnection,
  CloudflareZone,
  ProjectDnsZone,
} from '@/features/integrations/types'
import type { Site } from '@/types'

interface ResourceResponse<T> {
  data: T
}

interface CollectionResponse<T> {
  data: T[]
}

export async function fetchCloudflareConnection(
  organizationId: string,
): Promise<CloudflareConnection> {
  const response = await api.get<ResourceResponse<CloudflareConnection>>(
    `/api/v1/organizations/${organizationId}/integrations/cloudflare`,
  )

  return response.data.data
}

export async function connectCloudflare(
  organizationId: string,
  token: string,
): Promise<CloudflareConnection> {
  const response = await api.post<ResourceResponse<CloudflareConnection>>(
    `/api/v1/organizations/${organizationId}/integrations/cloudflare/connect`,
    { token },
  )

  return response.data.data
}

export async function disconnectCloudflare(organizationId: string): Promise<void> {
  await api.delete(
    `/api/v1/organizations/${organizationId}/integrations/cloudflare/disconnect`,
  )
}

export async function fetchCloudflareZones(organizationId: string): Promise<CloudflareZone[]> {
  const response = await api.get<CollectionResponse<CloudflareZone>>(
    `/api/v1/organizations/${organizationId}/integrations/cloudflare/zones`,
  )

  return response.data.data
}

export async function fetchProjectDnsZones(projectId: string): Promise<ProjectDnsZone[]> {
  const response = await api.get<CollectionResponse<ProjectDnsZone>>(
    `/api/v1/projects/${projectId}/dns-zones`,
  )

  return response.data.data
}

export async function assignProjectDnsZone(
  projectId: string,
  payload: { zoneId: string; baseDomain: string },
): Promise<ProjectDnsZone> {
  const response = await api.post<ResourceResponse<ProjectDnsZone>>(
    `/api/v1/projects/${projectId}/dns-zones`,
    payload,
  )

  return response.data.data
}

export async function removeProjectDnsZone(
  projectId: string,
  projectDnsZoneId: string,
): Promise<void> {
  await api.delete(`/api/v1/projects/${projectId}/dns-zones/${projectDnsZoneId}`)
}

export async function retrySiteDns(siteId: string): Promise<Site> {
  const response = await api.post<ResourceResponse<Site>>(`/api/v1/sites/${siteId}/dns/retry`)

  return response.data.data
}

export async function retrySiteSsl(siteId: string): Promise<Site> {
  const response = await api.post<ResourceResponse<Site>>(`/api/v1/sites/${siteId}/ssl/retry`)

  return response.data.data
}

export function buildHostnameFromPrefix(prefix: string, baseDomain: string): string {
  const trimmed = prefix.trim()

  if (trimmed === '@' || trimmed === '') {
    return baseDomain
  }

  return `${trimmed}.${baseDomain}`
}
