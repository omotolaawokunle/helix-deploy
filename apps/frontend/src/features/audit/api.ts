import { api } from '@/lib/axios'
import type { AuditLogEntry } from '@/types'
import type { CursorPaginatedResponse } from '@/features/deployments/types'

export interface AuditLogFilters {
  operation?: string
  actor_id?: string
  resource_type?: string
  date_from?: string
  date_to?: string
  cursor?: string
  per_page?: number
}

export async function fetchServerAuditLogs(
  serverId: string,
  filters: AuditLogFilters = {},
): Promise<CursorPaginatedResponse<AuditLogEntry>> {
  const response = await api.get<CursorPaginatedResponse<AuditLogEntry>>(
    `/api/v1/servers/${serverId}/audit-logs`,
    { params: filters },
  )

  return response.data
}

export async function fetchOrganizationAuditLogs(
  organizationId: string,
  filters: AuditLogFilters = {},
): Promise<CursorPaginatedResponse<AuditLogEntry>> {
  const response = await api.get<CursorPaginatedResponse<AuditLogEntry>>(
    `/api/v1/organizations/${organizationId}/audit-logs`,
    { params: filters },
  )

  return response.data
}

export async function exportAuditLogs(
  organizationId: string,
  filters: AuditLogFilters = {},
): Promise<{ status: string; exportId?: string; message?: string }> {
  const response = await api.get(
    `/api/v1/organizations/${organizationId}/audit-logs/export`,
    { params: filters },
  )

  return response.data as { status: string; exportId?: string; message?: string }
}
