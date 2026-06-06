import { api } from '@/lib/axios'
import type { Organization } from '@/types'
import type { AuthUser, CreateOrganizationPayload, CreateApiTokenPayload, CreateApiTokenResponse, ApiTokenRecord, ChangePasswordPayload, LoginPayload, RegisterPayload, UpdateProfilePayload } from './types'

interface ApiResource<T> {
  data: T
}

function unwrapResource<T>(payload: ApiResource<T>): T {
  return payload.data
}

export async function fetchAuthUser(): Promise<AuthUser> {
  const response = await api.get<ApiResource<AuthUser>>('/api/v1/auth/user')

  return unwrapResource(response.data)
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<AuthUser> {
  const response = await api.patch<ApiResource<AuthUser>>('/api/v1/auth/user', payload)

  return unwrapResource(response.data)
}

export async function changePassword(payload: ChangePasswordPayload): Promise<void> {
  await api.post('/api/v1/auth/password', payload)
}

export async function loginRequest(payload: LoginPayload): Promise<AuthUser> {
  await api.get('/sanctum/csrf-cookie')
  const response = await api.post<ApiResource<AuthUser>>('/api/v1/auth/login', payload)

  return unwrapResource(response.data)
}

export async function registerRequest(payload: RegisterPayload): Promise<AuthUser> {
  await api.get('/sanctum/csrf-cookie')
  const response = await api.post<ApiResource<AuthUser>>('/api/v1/auth/register', payload)

  return unwrapResource(response.data)
}

export async function logoutRequest(): Promise<void> {
  await api.post('/api/v1/auth/logout')
}

export async function resendVerificationEmail(): Promise<void> {
  await api.post('/api/v1/auth/email/resend')
}

export async function fetchOrganizations(): Promise<Organization[]> {
  const response = await api.get<{ data: Organization[] }>('/api/v1/organizations')

  return response.data.data
}

export async function createOrganization(payload: CreateOrganizationPayload): Promise<Organization> {
  const response = await api.post<ApiResource<Organization>>('/api/v1/organizations', payload)

  return unwrapResource(response.data)
}

export async function switchOrganization(orgId: string): Promise<void> {
  await api.post(`/api/v1/organizations/${orgId}/switch`)
}

export async function fetchApiTokens(): Promise<ApiTokenRecord[]> {
  const response = await api.get<{ data: ApiTokenRecord[] }>('/api/v1/auth/tokens')

  return response.data.data
}

export async function createApiToken(payload: CreateApiTokenPayload): Promise<CreateApiTokenResponse> {
  const response = await api.post<{
    data: ApiTokenRecord
    plainTextToken: string
  }>('/api/v1/auth/tokens', payload)

  return {
    token: response.data.data,
    plainTextToken: response.data.plainTextToken,
  }
}

export async function revokeApiToken(tokenId: string): Promise<void> {
  await api.delete(`/api/v1/auth/tokens/${tokenId}`)
}
