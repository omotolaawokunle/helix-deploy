import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useRegisterForm } from '@/features/auth/composables/useRegisterForm'

vi.mock('@/features/auth/api', () => ({
  registerRequest: vi.fn(),
}))

import { registerRequest } from '@/features/auth/api'

describe('useRegisterForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('shows verification notice after submit without redirecting', async () => {
    vi.mocked(registerRequest).mockResolvedValue({
      id: 'user-1',
      name: 'New User',
      email: 'new@example.com',
      emailVerifiedAt: null,
      currentOrganizationId: 'org-1',
      createdAt: new Date().toISOString(),
    })

    const { submitRegister, showVerificationNotice, registeredEmail } = useRegisterForm()

    await submitRegister({
      name: 'New User',
      email: 'new@example.com',
      password: 'password123',
      password_confirmation: 'password123',
    })

    expect(showVerificationNotice.value).toBe(true)
    expect(registeredEmail.value).toBe('new@example.com')
  })
})
