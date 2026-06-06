import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import EnvVarsTab from '@/features/sites/components/EnvVarsTab.vue'
import type { EnvVarListItem } from '@/types'

const fetchEnvVarsMock = vi.fn()
const revealEnvVarMock = vi.fn()

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/features/sites/api', () => ({
  fetchEnvVars: (...args: unknown[]) => fetchEnvVarsMock(...args),
  revealEnvVar: (...args: unknown[]) => revealEnvVarMock(...args),
  createEnvVar: vi.fn(),
  updateEnvVar: vi.fn(),
  deleteEnvVar: vi.fn(),
  syncEnvVars: vi.fn(),
}))

const envVar: EnvVarListItem = {
  id: 'cred-1',
  key: 'APP_KEY',
  maskedValue: '••••••••',
  createdAt: null,
  updatedAt: null,
}

describe('EnvVarsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    fetchEnvVarsMock.mockResolvedValue([envVar])
    revealEnvVarMock.mockResolvedValue({ id: 'cred-1', key: 'APP_KEY', value: 'secret-value' })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('reveals value on button click and re-masks after 30 seconds', async () => {
    const wrapper = mount(EnvVarsTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    expect(revealEnvVarMock).not.toHaveBeenCalled()

    const revealButton = document.body.querySelector('[data-testid="env-var-reveal-button"]') as HTMLButtonElement
    revealButton.click()
    await flushPromises()

    const input = document.body.querySelector('[data-testid="env-var-masked-input"]') as HTMLInputElement
    expect(input.value).toBe('secret-value')

    vi.advanceTimersByTime(30_000)
    await flushPromises()

    expect(input.value).toBe('••••••••')
    wrapper.unmount()
  })

  it('never includes raw values in list fetch response', async () => {
    fetchEnvVarsMock.mockResolvedValue([
      { ...envVar, maskedValue: '••••••••' },
    ])

    mount(EnvVarsTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const result = await fetchEnvVarsMock.mock.results[0]?.value as EnvVarListItem[]
    expect(result[0]?.maskedValue).toBe('••••••••')
    expect(JSON.stringify(result)).not.toMatch(/secret-value/)
  })
})
