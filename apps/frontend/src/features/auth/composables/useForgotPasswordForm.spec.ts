import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useForgotPasswordForm } from '@/features/auth/composables/useForgotPasswordForm'

vi.mock('@/features/auth/api', () => ({
  forgotPasswordRequest: vi.fn(),
}))

import { forgotPasswordRequest } from '@/features/auth/api'

describe('useForgotPasswordForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('marks the form submitted after a successful request', async () => {
    vi.mocked(forgotPasswordRequest).mockResolvedValue(undefined)

    const { submitForgotPassword, submitted } = useForgotPasswordForm()

    await submitForgotPassword({ email: 'user@example.test' })

    expect(forgotPasswordRequest).toHaveBeenCalledWith({
      email: 'user@example.test',
    })
    expect(submitted.value).toBe(true)
  })

  it('sets an api error when the request fails without field errors', async () => {
    vi.mocked(forgotPasswordRequest).mockRejectedValue(new Error('network'))

    const { submitForgotPassword, apiError, submitted } = useForgotPasswordForm()

    await submitForgotPassword({ email: 'user@example.test' })

    expect(submitted.value).toBe(false)
    expect(apiError.value).toBe('Unable to send reset link. Try again.')
  })
})
