import { TeamRole } from '@/types'

export interface TeamRecord {
  id: string
  name: string
  slug: string
  memberCount: number
  projectIds: string[]
  createdAt: string
  updatedAt: string
}

export interface TeamMemberRecord {
  id: string
  name: string
  email: string
  role: TeamRole
  joinedAt: string | null
}

export interface CreateTeamPayload {
  name: string
}

export interface UpdateTeamPayload {
  name: string
}

export interface AddTeamMemberPayload {
  userId: string
  role: TeamRole
}

export const TEAM_MEMBER_ROLE_OPTIONS: TeamRole[] = [
  TeamRole.Admin,
  TeamRole.Developer,
  TeamRole.Viewer,
]
