import { api } from '@/lib/axios'
import type {
  CreatePipelinePayload,
  PipelineRecord,
  UpdatePipelinePayload,
} from '@/features/pipelines/types'

interface PaginatedResponse<T> {
  data: T[]
}

export async function fetchPipelines(organizationId: string): Promise<PipelineRecord[]> {
  const response = await api.get<PaginatedResponse<PipelineRecord>>(
    `/api/v1/organizations/${organizationId}/pipelines`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchPipeline(pipelineId: string): Promise<PipelineRecord> {
  const response = await api.get<PipelineRecord>(`/api/v1/pipelines/${pipelineId}`)

  return response.data
}

export async function createPipeline(
  organizationId: string,
  payload: CreatePipelinePayload,
): Promise<PipelineRecord> {
  const response = await api.post<PipelineRecord>(
    `/api/v1/organizations/${organizationId}/pipelines`,
    payload,
  )

  return response.data
}

export async function updatePipeline(
  pipelineId: string,
  payload: UpdatePipelinePayload,
): Promise<PipelineRecord> {
  const response = await api.patch<PipelineRecord>(`/api/v1/pipelines/${pipelineId}`, payload)

  return response.data
}

export async function deletePipeline(pipelineId: string): Promise<void> {
  await api.delete(`/api/v1/pipelines/${pipelineId}`)
}
