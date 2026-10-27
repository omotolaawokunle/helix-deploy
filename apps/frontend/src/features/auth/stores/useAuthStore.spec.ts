import { AxiosError } from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ensureCsrfCookie, fetchAuthUser } from '@/features/auth/api'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

vi.mock('@/features/auth/api', () => ({
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
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
    vi.mocked(ensureCsrfCookie).mockReset()
    vi.mocked(ensureCsrfCookie).mockResolvedValue(undefined)
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

  it('refreshes the csrf cookie before loading the user', async () => {
    const order: string[] = []

    vi.mocked(ensureCsrfCookie).mockImplementation(async () => {
      order.push('csrf')
    })
    vi.mocked(fetchAuthUser).mockImplementation(async () => {
      order.push('user')

      throw unauthenticatedError()
    })

    await useAuthStore().init()

    expect(order).toEqual(['csrf', 'user'])
  })

  it('still loads the user when the csrf cookie refresh fails', async () => {
    vi.mocked(ensureCsrfCookie).mockRejectedValue(new Error('network'))
    vi.mocked(fetchAuthUser).mockRejectedValue(unauthenticatedError())

    const authStore = useAuthStore()

    await authStore.init()

    expect(fetchAuthUser).toHaveBeenCalledTimes(1)
    expect(authStore.isAuthenticated).toBe(false)
  })
})
