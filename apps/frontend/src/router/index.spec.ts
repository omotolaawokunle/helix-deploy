import { beforeEach, describe, expect, it } from 'vitest'
import router from '@/router'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { pinia } from '@/stores'

describe('router auth guards', () => {
  beforeEach(async () => {
    const authStore = useAuthStore(pinia)
    authStore.clearAuth()
    await router.push('/')
    await router.isReady()
  })

  it('redirects unauthenticated user to /login', async () => {
    const authStore = useAuthStore(pinia)
    authStore.clearAuth()

    await router.push('/dashboard')

    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('redirects authenticated user on /login to /dashboard', async () => {
    const authStore = useAuthStore(pinia)
    authStore.setAuthUser({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: new Date().toISOString(),
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })

    await router.push('/register')
    await router.push('/login')

    expect(router.currentRoute.value.path).toBe('/dashboard')
  })
})
