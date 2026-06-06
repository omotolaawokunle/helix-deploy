import { api } from '@/lib/axios'
import type { TeamRole } from '@/types'
import type {
  AddTeamMemberPayload,
  CreateTeamPayload,
  TeamMemberRecord,
  TeamRecord,
  UpdateTeamPayload,
} from '@/features/teams/types'

interface PaginatedResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export async function fetchTeams(organizationId: string): Promise<TeamRecord[]> {
  const response = await api.get<PaginatedResponse<TeamRecord>>(
    `/api/v1/organizations/${organizationId}/teams`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function fetchTeam(teamId: string): Promise<TeamRecord> {
  const response = await api.get<ResourceResponse<TeamRecord>>(`/api/v1/teams/${teamId}`)

  return response.data.data
}

export async function createTeam(
  organizationId: string,
  payload: CreateTeamPayload,
): Promise<TeamRecord> {
  const response = await api.post<ResourceResponse<TeamRecord>>(
    `/api/v1/organizations/${organizationId}/teams`,
    payload,
  )

  return response.data.data
}

export async function updateTeam(teamId: string, payload: UpdateTeamPayload): Promise<TeamRecord> {
  const response = await api.patch<ResourceResponse<TeamRecord>>(
    `/api/v1/teams/${teamId}`,
    payload,
  )

  return response.data.data
}

export async function deleteTeam(teamId: string): Promise<void> {
  await api.delete(`/api/v1/teams/${teamId}`)
}

export async function syncTeamProjects(
  teamId: string,
  projectIds: string[],
): Promise<TeamRecord> {
  const response = await api.put<ResourceResponse<TeamRecord>>(
    `/api/v1/teams/${teamId}/projects`,
    { projectIds },
  )

  return response.data.data
}

export async function fetchTeamMembers(teamId: string): Promise<TeamMemberRecord[]> {
  const response = await api.get<PaginatedResponse<TeamMemberRecord>>(
    `/api/v1/teams/${teamId}/members`,
    { params: { per_page: 100 } },
  )

  return response.data.data.map(member => ({
    ...member,
    role: member.role as TeamRole,
  }))
}

export async function addTeamMember(
  teamId: string,
  payload: AddTeamMemberPayload,
): Promise<void> {
  await api.post(`/api/v1/teams/${teamId}/members`, payload)
}

export async function updateTeamMemberRole(
  teamId: string,
  userId: string,
  role: TeamRole,
): Promise<void> {
  await api.patch(`/api/v1/teams/${teamId}/members/${userId}`, { role })
}

export async function removeTeamMember(teamId: string, userId: string): Promise<void> {
  await api.delete(`/api/v1/teams/${teamId}/members/${userId}`)
}
