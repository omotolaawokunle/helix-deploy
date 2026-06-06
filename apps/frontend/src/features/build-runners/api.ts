import { api } from '@/lib/axios'
import type {
  BuildRunner,
  BuildRunnerRegistrationResponse,
  RegisterBuildRunnerPayload,
  UpdateBuildRunnerPayload,
} from '@/features/build-runners/types'

interface PaginatedResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export interface FetchBuildRunnersOptions {
  search?: string
  status?: string
}

export async function fetchBuildRunners(
  organizationId: string,
  options: FetchBuildRunnersOptions = {},
): Promise<BuildRunner[]> {
  const params: Record<string, string | number> = { per_page: 100 }

  if (options.search !== undefined && options.search.trim() !== '') {
    params.search = options.search.trim()
  }

  if (options.status !== undefined && options.status !== '') {
    params['filter[status]'] = options.status
  }

  const response = await api.get<PaginatedResponse<BuildRunner>>(
    `/api/v1/organizations/${organizationId}/build-runners`,
    { params },
  )

  return response.data.data
}

export async function registerBuildRunner(
  organizationId: string,
  payload: RegisterBuildRunnerPayload,
): Promise<BuildRunnerRegistrationResponse> {
  const response = await api.post<ResourceResponse<BuildRunnerRegistrationResponse>>(
    `/api/v1/organizations/${organizationId}/build-runners`,
    payload,
  )

  return response.data.data
}

export async function updateBuildRunner(
  runnerId: string,
  payload: UpdateBuildRunnerPayload,
): Promise<BuildRunner> {
  const response = await api.patch<ResourceResponse<BuildRunner>>(
    `/api/v1/build-runners/${runnerId}`,
    payload,
  )

  return response.data.data
}

export async function deleteBuildRunner(runnerId: string): Promise<void> {
  await api.delete(`/api/v1/build-runners/${runnerId}`)
}

export async function testBuildRunnerConnection(runnerId: string): Promise<void> {
  await api.post(`/api/v1/build-runners/${runnerId}/test-connection`)
}
