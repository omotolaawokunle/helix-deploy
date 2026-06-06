import { describe, expect, it, vi } from 'vitest'
import { loginRequest } from '@/features/auth/api'

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
})
