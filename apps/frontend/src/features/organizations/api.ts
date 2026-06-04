import { api } from '@/lib/axios'
import type { Organization, OrganizationMemberRecord } from '@/types'
import { TeamRole } from '@/types'

interface OrganizationMemberResponse {
  id: string
  name: string
  email: string
  role: string
  joinedAt: string | null
}

interface PaginatedMembersResponse {
  data: OrganizationMemberResponse[]
}

export async function fetchCurrentMemberRole(
  organizationId: string,
  userId: string,
): Promise<TeamRole | null> {
  const members = await fetchOrganizationMembers(organizationId)

  const member = members.find(entry => entry.id === userId)

  if (member === undefined) {
    return null
  }

  return member.role
}

export async function fetchOrganization(organizationId: string): Promise<Organization> {
  const response = await api.get<Organization | { data: Organization }>(
    `/api/v1/organizations/${organizationId}`,
  )

  if ('data' in response.data) {
    return response.data.data
  }

  return response.data
}

export async function updateOrganization(
  organizationId: string,
  payload: { name: string },
): Promise<Organization> {
  const response = await api.patch<Organization | { data: Organization }>(
    `/api/v1/organizations/${organizationId}`,
    payload,
  )

  if ('data' in response.data) {
    return response.data.data
  }

  return response.data
}

export async function fetchOrganizationMembers(
  organizationId: string,
): Promise<OrganizationMemberRecord[]> {
  const response = await api.get<PaginatedMembersResponse>(
    `/api/v1/organizations/${organizationId}/members`,
    { params: { per_page: 100 } },
  )

  return response.data.data.map(member => ({
    id: member.id,
    name: member.name,
    email: member.email,
    role: member.role as TeamRole,
    joinedAt: member.joinedAt,
  }))
}

export async function inviteOrganizationMember(
  organizationId: string,
  payload: { email: string },
): Promise<string> {
  const response = await api.post<{ data: { invitationUrl: string } }>(
    `/api/v1/organizations/${organizationId}/invitations`,
    { email: payload.email },
  )

  return response.data.data.invitationUrl
}

export async function updateMemberRole(
  organizationId: string,
  userId: string,
  role: TeamRole,
): Promise<void> {
  await api.patch(`/api/v1/organizations/${organizationId}/members/${userId}`, { role })
}

export async function removeOrganizationMember(
  organizationId: string,
  userId: string,
): Promise<void> {
  await api.delete(`/api/v1/organizations/${organizationId}/members/${userId}`)
}
