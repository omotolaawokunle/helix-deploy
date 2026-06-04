import { api } from '@/lib/axios'
import { TeamRole } from '@/types'

interface OrganizationMemberResponse {
  id: string
  name: string
  email: string
  role: string
  joinedAt: string | null
}

export async function fetchCurrentMemberRole(
  organizationId: string,
  userId: string,
): Promise<TeamRole | null> {
  const response = await api.get<{ data: OrganizationMemberResponse[] }>(
    `/api/v1/organizations/${organizationId}/members`,
    { params: { per_page: 100 } },
  )

  const member = response.data.data.find(entry => entry.id === userId)

  if (member === undefined) {
    return null
  }

  return member.role as TeamRole
}
