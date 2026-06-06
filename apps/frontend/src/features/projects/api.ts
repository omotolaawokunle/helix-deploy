import { api } from '@/lib/axios'
import type {
  CreateEnvironmentPayload,
  CreateProjectPayload,
  EnvironmentRecord,
  ProjectRecord,
  UpdateEnvironmentPayload,
  UpdateProjectPayload,
} from '@/features/projects/types'

interface PaginatedResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export async function fetchProjects(organizationId: string): Promise<ProjectRecord[]> {
  const response = await api.get<PaginatedResponse<ProjectRecord>>(
    `/api/v1/organizations/${organizationId}/projects`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchProject(projectId: string): Promise<ProjectRecord> {
  const response = await api.get<ResourceResponse<ProjectRecord>>(`/api/v1/projects/${projectId}`)

  return response.data.data
}

export async function createProject(
  organizationId: string,
  payload: CreateProjectPayload,
): Promise<ProjectRecord> {
  const response = await api.post<ResourceResponse<ProjectRecord>>(
    `/api/v1/organizations/${organizationId}/projects`,
    payload,
  )

  return response.data.data
}

export async function updateProject(
  projectId: string,
  payload: UpdateProjectPayload,
): Promise<ProjectRecord> {
  const response = await api.patch<ResourceResponse<ProjectRecord>>(
    `/api/v1/projects/${projectId}`,
    payload,
  )

  return response.data.data
}

export async function deleteProject(projectId: string): Promise<void> {
  await api.delete(`/api/v1/projects/${projectId}`)
}

export async function fetchProjectEnvironments(projectId: string): Promise<EnvironmentRecord[]> {
  const response = await api.get<PaginatedResponse<EnvironmentRecord>>(
    `/api/v1/projects/${projectId}/environments`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function createEnvironment(
  projectId: string,
  payload: CreateEnvironmentPayload,
): Promise<EnvironmentRecord> {
  const response = await api.post<ResourceResponse<EnvironmentRecord>>(
    `/api/v1/projects/${projectId}/environments`,
    payload,
  )

  return response.data.data
}

export async function updateEnvironment(
  projectId: string,
  environmentId: string,
  payload: UpdateEnvironmentPayload,
): Promise<EnvironmentRecord> {
  const response = await api.patch<ResourceResponse<EnvironmentRecord>>(
    `/api/v1/projects/${projectId}/environments/${environmentId}`,
    payload,
  )

  return response.data.data
}

export async function deleteEnvironment(projectId: string, environmentId: string): Promise<void> {
  await api.delete(`/api/v1/projects/${projectId}/environments/${environmentId}`)
}
