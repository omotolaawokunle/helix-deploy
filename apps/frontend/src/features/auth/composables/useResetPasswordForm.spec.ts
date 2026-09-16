import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useResetPasswordForm } from '@/features/auth/composables/useResetPasswordForm'

vi.mock('@/features/auth/api', () => ({
  resetPasswordRequest: vi.fn(),
}))

const routerPush = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: routerPush,
  }),
  useRoute: () => ({
    query: {
      email: 'user@example.test',
      token: 'reset-token',
    },
  }),
}))

import { resetPasswordRequest } from '@/features/auth/api'

describe('useResetPasswordForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routerPush.mockReset()
  })

  it('submits reset payload and redirects to login', async () => {
    vi.mocked(resetPasswordRequest).mockResolvedValue(undefined)

    const { submitResetPassword } = useResetPasswordForm()

    await submitResetPassword({
      password: 'new-password-456',
      passwordConfirmation: 'new-password-456',
    })

    expect(resetPasswordRequest).toHaveBeenCalledWith({
      email: 'user@example.test',
      token: 'reset-token',
      password: 'new-password-456',
      passwordConfirmation: 'new-password-456',
    })
    expect(routerPush).toHaveBeenCalledWith({
      path: '/login',
      query: { reset: '1' },
    })
  })

  it('sets an api error when reset fails without field errors', async () => {
    vi.mocked(resetPasswordRequest).mockRejectedValue(new Error('network'))

    const { submitResetPassword, apiError } = useResetPasswordForm()

    await submitResetPassword({
      password: 'new-password-456',
      passwordConfirmation: 'new-password-456',
    })

    expect(apiError.value).toBe(
      'Unable to reset password. Request a new link and try again.',
    )
    expect(routerPush).not.toHaveBeenCalled()
  })
})
