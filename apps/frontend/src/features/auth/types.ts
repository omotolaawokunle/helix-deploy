import type { Organization, TeamRole, User } from '@/types'

export interface AuthUser extends User {
  currentOrganization?: Organization | null
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface CreateOrganizationPayload {
  name: string
}

export { TeamRole }
