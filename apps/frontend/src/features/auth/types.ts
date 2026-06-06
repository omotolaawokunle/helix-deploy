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

export interface UpdateProfilePayload {
  name: string
  email: string
  timezone: string
}

export interface ChangePasswordPayload {
  currentPassword: string
  password: string
  passwordConfirmation: string
}

export interface CreateOrganizationPayload {
  name: string
}

export type ApiTokenAbility = 'full' | 'read'

export interface ApiTokenRecord {
  id: string
  name: string
  ability: ApiTokenAbility
  lastUsedAt: string | null
  createdAt: string
}

export interface CreateApiTokenPayload {
  name: string
  ability: ApiTokenAbility
}

export interface CreateApiTokenResponse {
  token: ApiTokenRecord
  plainTextToken: string
}

export { TeamRole }
