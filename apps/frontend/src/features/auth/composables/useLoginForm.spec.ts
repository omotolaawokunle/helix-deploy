import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { useLoginForm } from '@/features/auth/composables/useLoginForm'

vi.mock('@/features/auth/api', () => ({
  loginRequest: vi.fn(),
  fetchOrganizations: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/organizations/api', () => ({
  fetchCurrentMemberRole: vi.fn().mockResolvedValue('owner'),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

import { loginRequest } from '@/features/auth/api'

describe('useLoginForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('submits correct payload on form submit', async () => {
    vi.mocked(loginRequest).mockResolvedValue({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: new Date().toISOString(),
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })

    const { submitLogin, authStore } = useLoginForm()

    await submitLogin({
      email: 'test@example.com',
      password: 'secret-password',
    })

    expect(loginRequest).toHaveBeenCalledWith({
      email: 'test@example.com',
      password: 'secret-password',
    })
    expect(authStore.isAuthenticated).toBe(true)
  })

  it('shows 422 errors on correct fields', async () => {
    const validationError = new AxiosError('Validation failed')
    validationError.response = {
      status: 422,
      data: {
        errors: {
          email: ['The email has already been taken.'],
          password: ['The password is incorrect.'],
        },
      },
      statusText: 'Unprocessable Entity',
      headers: {},
      config: {} as InternalAxiosRequestConfig,
    } as AxiosResponse

    vi.mocked(loginRequest).mockRejectedValue(validationError)

    const { submitLogin, form } = useLoginForm()

    await submitLogin({
      email: 'test@example.com',
      password: 'secret-password',
    })

    expect(form.errors.value.email).toBe('The email has already been taken.')
    expect(form.errors.value.password).toBe('The password is incorrect.')
  })

  it('shows loading state during request', async () => {
    let resolveLogin: ((value: Awaited<ReturnType<typeof loginRequest>>) => void) | undefined

    vi.mocked(loginRequest).mockImplementation(
      () =>
        new Promise(resolve => {
          resolveLogin = resolve
        }),
    )

    const { submitLogin, authStore } = useLoginForm()

    const pending = submitLogin({
      email: 'test@example.com',
      password: 'secret-password',
    })

    expect(authStore.isLoading).toBe(true)

    resolveLogin?.({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: new Date().toISOString(),
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })

    await pending

    expect(authStore.isLoading).toBe(false)
  })
})
