import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError } from 'axios'
import router from '@/router'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { pinia } from '@/stores'

vi.mock('@/features/auth/api', () => ({
  fetchAuthUser: vi.fn(),
  fetchOrganizations: vi.fn().mockResolvedValue([]),
}))

import { fetchAuthUser } from '@/features/auth/api'

describe('router auth guards', () => {
  beforeEach(async () => {
    const authStore = useAuthStore(pinia)
    authStore.clearAuth()

    const unauthenticatedError = new AxiosError('Unauthorized')
    unauthenticatedError.response = {
      status: 401,
      data: {},
      statusText: 'Unauthorized',
      headers: {},
      config: {} as never,
    }

    vi.mocked(fetchAuthUser).mockRejectedValue(unauthenticatedError)
  })

  it('redirects unauthenticated user to /login', async () => {
    const authStore = useAuthStore(pinia)
    authStore.clearAuth()
    authStore.markAuthInitialized()

    await router.push('/dashboard')

    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('redirects verified authenticated user on /login to /dashboard', async () => {
    const authStore = useAuthStore(pinia)
    authStore.setAuthUser({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: new Date().toISOString(),
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })
    authStore.markAuthInitialized()

    await router.push('/register')
    await router.push('/login')

    expect(router.currentRoute.value.path).toBe('/dashboard')
  })

  it('redirects unverified authenticated user on /login to /verify-email', async () => {
    const authStore = useAuthStore(pinia)
    authStore.setAuthUser({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: null,
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })
    authStore.markAuthInitialized()

    await router.push('/login')

    expect(router.currentRoute.value.path).toBe('/verify-email')
  })

  it('redirects unverified authenticated user to /verify-email from protected routes', async () => {
    const authStore = useAuthStore(pinia)
    authStore.setAuthUser({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: null,
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })
    authStore.markAuthInitialized()

    await router.push('/dashboard')

    expect(router.currentRoute.value.path).toBe('/verify-email')
  })

  it('renders not found page for unknown routes', async () => {
    await router.push('/does-not-exist')

    expect(router.currentRoute.value.name).toBe('not-found')
  })
})
