import { api } from '@/lib/axios'
import type {
  CursorPaginatedResponse,
  DeploymentDetail,
  DeploymentListItem,
  RollbackDeploymentPayload,
  TriggerDeploymentPayload,
} from '@/features/deployments/types'

interface ResourceResponse<T> {
  data: T
}

interface AcceptedDeploymentResponse {
  data: DeploymentDetail
  channel?: string
}

export async function fetchDeployment(deploymentId: string): Promise<DeploymentDetail> {
  const response = await api.get<ResourceResponse<DeploymentDetail>>(
    `/api/v1/deployments/${deploymentId}`,
  )

  return response.data.data
}

export async function fetchSiteDeployments(
  siteId: string,
  params?: { cursor?: string; per_page?: number },
): Promise<CursorPaginatedResponse<DeploymentListItem>> {
  const response = await api.get<CursorPaginatedResponse<DeploymentListItem>>(
    `/api/v1/sites/${siteId}/deployments`,
    { params },
  )

  return response.data
}

export async function triggerDeployment(
  siteId: string,
  payload: TriggerDeploymentPayload,
): Promise<AcceptedDeploymentResponse> {
  const response = await api.post<AcceptedDeploymentResponse>(
    `/api/v1/sites/${siteId}/deployments`,
    payload,
  )

  return response.data
}

export async function rollbackDeployment(
  deploymentId: string,
  payload: RollbackDeploymentPayload,
): Promise<AcceptedDeploymentResponse> {
  const response = await api.post<AcceptedDeploymentResponse>(
    `/api/v1/deployments/${deploymentId}/rollback`,
    payload,
  )

  return response.data
}

export async function cancelDeployment(deploymentId: string): Promise<DeploymentDetail> {
  const response = await api.post<ResourceResponse<DeploymentDetail>>(
    `/api/v1/deployments/${deploymentId}/cancel`,
  )

  return response.data.data
}

export async function approvePipelineRun(pipelineRunId: string): Promise<void> {
  await api.post(`/api/v1/pipeline-runs/${pipelineRunId}/approve`)
}

export async function rejectPipelineRun(
  pipelineRunId: string,
  payload?: { reason?: string | null },
): Promise<void> {
  await api.post(`/api/v1/pipeline-runs/${pipelineRunId}/reject`, payload ?? {})
}
