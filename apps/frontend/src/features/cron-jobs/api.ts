import { api } from '@/lib/axios'
import type { CronJobRecord } from '@/types'

interface CollectionResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export async function fetchCronJobs(serverId: string): Promise<CronJobRecord[]> {
  const response = await api.get<CollectionResponse<CronJobRecord>>(
    `/api/v1/servers/${serverId}/cron-jobs`,
  )

  return response.data.data
}

export async function describeCronExpression(expression: string): Promise<string> {
  const response = await api.get<ResourceResponse<{ description: string }>>(
    '/api/v1/cron-jobs/describe',
    { params: { expression } },
  )

  return response.data.data.description
}

export async function createCronJob(
  serverId: string,
  payload: { expression: string; command: string; user: string; active?: boolean },
): Promise<CronJobRecord> {
  const response = await api.post<ResourceResponse<CronJobRecord>>(
    `/api/v1/servers/${serverId}/cron-jobs`,
    payload,
  )

  return response.data.data
}

export async function updateCronJob(
  serverId: string,
  cronJobId: string,
  payload: Partial<{ expression: string; command: string; user: string; active: boolean }>,
): Promise<CronJobRecord> {
  const response = await api.patch<ResourceResponse<CronJobRecord>>(
    `/api/v1/servers/${serverId}/cron-jobs/${cronJobId}`,
    payload,
  )

  return response.data.data
}

export async function deleteCronJob(serverId: string, cronJobId: string): Promise<void> {
  await api.delete(`/api/v1/servers/${serverId}/cron-jobs/${cronJobId}`)
}

export async function toggleCronJob(serverId: string, cronJobId: string): Promise<CronJobRecord> {
  const response = await api.post<ResourceResponse<CronJobRecord>>(
    `/api/v1/servers/${serverId}/cron-jobs/${cronJobId}/toggle`,
  )

  return response.data.data
}
