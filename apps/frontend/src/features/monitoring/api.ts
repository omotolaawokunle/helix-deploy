import { api } from '@/lib/axios'
import { fetchServers } from '@/features/servers/api'
import type { DeploymentListItem } from '@/features/deployments/types'
import type { CursorPaginatedResponse } from '@/features/deployments/types'
import { DeploymentStatus, ServerStatus, type Server } from '@/types'

export interface DashboardStats {
  activeServers: number
  deploymentsToday: number
  successfulToday: number
  failedToday: number
  serversWithIssues: number
}

export async function fetchRecentDeployments(
  organizationId: string,
  limit = 10,
): Promise<DeploymentListItem[]> {
  const response = await api.get<CursorPaginatedResponse<DeploymentListItem>>(
    `/api/v1/organizations/${organizationId}/deployments`,
    { params: { per_page: limit } },
  )

  return response.data.data
}

export function computeDashboardStats(
  servers: Server[],
  deployments: DeploymentListItem[],
): DashboardStats {
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const deploymentsToday = deployments.filter((deployment) => {
    const created = new Date(deployment.createdAt)

    return created >= today
  })

  const successfulToday = deploymentsToday.filter(
    deployment => deployment.status === DeploymentStatus.Succeeded,
  ).length

  const failedToday = deploymentsToday.filter(
    deployment => deployment.status === DeploymentStatus.Failed,
  ).length

  return {
    activeServers: servers.filter(server => server.status === ServerStatus.Active).length,
    deploymentsToday: deploymentsToday.length,
    successfulToday,
    failedToday,
    serversWithIssues: servers.filter(
      server => server.status === ServerStatus.Disconnected
        || server.status === ServerStatus.Maintenance,
    ).length,
  }
}

export async function loadDashboardData(organizationId: string): Promise<{
  servers: Server[]
  deployments: DeploymentListItem[]
  stats: DashboardStats
}> {
  const [servers, deployments] = await Promise.all([
    fetchServers(organizationId),
    fetchRecentDeployments(organizationId),
  ])

  return {
    servers,
    deployments,
    stats: computeDashboardStats(servers, deployments),
  }
}
