import { describe, expect, it, vi } from 'vitest'
import { loginRequest, forgotPasswordRequest, resetPasswordRequest } from '@/features/auth/api'

vi.mock('@/lib/axios', () => ({
  api: {
    get: vi.fn().mockResolvedValue({}),
    post: vi.fn(),
  },
}))

import { api } from '@/lib/axios'

describe('auth api', () => {
  it('unwraps login resource payload', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: {
        data: {
          id: 'user-1',
          name: 'Test User',
          email: 'test@example.com',
          emailVerifiedAt: null,
          currentOrganizationId: 'org-1',
          createdAt: new Date().toISOString(),
        },
      },
    })

    const user = await loginRequest({
      email: 'test@example.com',
      password: 'secret',
    })

    expect(user.id).toBe('user-1')
    expect(user.emailVerifiedAt).toBeNull()
  })

  it('posts forgot password after fetching csrf cookie', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: {} })

    await forgotPasswordRequest({ email: 'user@example.test' })

    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
    expect(api.post).toHaveBeenCalledWith('/api/v1/auth/forgot-password', {
      email: 'user@example.test',
    })
  })

  it('posts reset password after fetching csrf cookie', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: {} })

    await resetPasswordRequest({
      email: 'user@example.test',
      token: 'token',
      password: 'new-password-456',
      passwordConfirmation: 'new-password-456',
    })

    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
    expect(api.post).toHaveBeenCalledWith('/api/v1/auth/reset-password', {
      email: 'user@example.test',
      token: 'token',
      password: 'new-password-456',
      passwordConfirmation: 'new-password-456',
    })
  })
})
