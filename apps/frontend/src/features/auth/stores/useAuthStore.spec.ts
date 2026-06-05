import { AxiosError } from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchAuthUser } from '@/features/auth/api'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

vi.mock('@/features/auth/api', () => ({
  fetchAuthUser: vi.fn(),
  fetchOrganizations: vi.fn(),
}))

function unauthenticatedError(): AxiosError {
  const error = new AxiosError('Unauthorized')
  error.response = {
    status: 401,
    data: {},
    statusText: 'Unauthorized',
    headers: {},
    config: {} as never,
  }

  return error
}

describe('useAuthStore init', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(fetchAuthUser).mockReset()
  })

  it('marks initialized after a failed bootstrap request without resetting on clearAuth', async () => {
    vi.mocked(fetchAuthUser).mockRejectedValue(unauthenticatedError())

    const authStore = useAuthStore()

    await authStore.init()

    expect(fetchAuthUser).toHaveBeenCalledTimes(1)
    expect(authStore.isInitialized).toBe(true)
    expect(authStore.isAuthenticated).toBe(false)

    authStore.clearAuth()

    expect(authStore.isInitialized).toBe(true)
    expect(authStore.isAuthenticated).toBe(false)

    await authStore.init()

    expect(fetchAuthUser).toHaveBeenCalledTimes(1)
  })

  it('deduplicates concurrent init calls', async () => {
    vi.mocked(fetchAuthUser).mockRejectedValue(unauthenticatedError())

    const authStore = useAuthStore()

    await Promise.all([authStore.init(), authStore.init(), authStore.init()])

    expect(fetchAuthUser).toHaveBeenCalledTimes(1)
    expect(authStore.isInitialized).toBe(true)
  })
})
