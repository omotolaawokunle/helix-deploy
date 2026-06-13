import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import EnvVarsTab from '@/features/sites/components/EnvVarsTab.vue'
import type { EnvVarListItem } from '@/types'

const fetchEnvVarsMock = vi.fn()
const revealEnvVarMock = vi.fn()
const fetchEnvVarsPullPreviewMock = vi.fn()

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/features/sites/composables/useEnvVarsSiteChannel', () => ({
  useEnvVarsSiteChannel: vi.fn(),
}))

vi.mock('@/features/sites/api', () => ({
  fetchEnvVars: (...args: unknown[]) => fetchEnvVarsMock(...args),
  revealEnvVar: (...args: unknown[]) => revealEnvVarMock(...args),
  fetchEnvVarsPullPreview: (...args: unknown[]) => fetchEnvVarsPullPreviewMock(...args),
  createEnvVar: vi.fn(),
  updateEnvVar: vi.fn(),
  deleteEnvVar: vi.fn(),
  syncEnvVars: vi.fn(),
  applyEnvVarsPull: vi.fn(),
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
    fetchEnvVarsPullPreviewMock.mockResolvedValue({
      status: 'ready',
      serverFileExists: true,
      new: ['DB_HOST'],
      changed: [],
      unchanged: [],
      helixOnly: [],
      skipped: [],
    })
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
    expect(document.body.querySelector('[data-testid="env-var-hide-button"]')).not.toBeNull()

    vi.advanceTimersByTime(30_000)
    await flushPromises()

    expect(input.value).toBe('••••••••')
    wrapper.unmount()
  })

  it('hides revealed value immediately when hide is clicked', async () => {
    const wrapper = mount(EnvVarsTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const revealButton = document.body.querySelector('[data-testid="env-var-reveal-button"]') as HTMLButtonElement
    revealButton.click()
    await flushPromises()

    const hideButton = document.body.querySelector('[data-testid="env-var-hide-button"]') as HTMLButtonElement
    hideButton.click()
    await flushPromises()

    const input = document.body.querySelector('[data-testid="env-var-masked-input"]') as HTMLInputElement
    expect(input.value).toBe('••••••••')
    expect(document.body.querySelector('[data-testid="env-var-reveal-button"]')).not.toBeNull()

    vi.advanceTimersByTime(30_000)
    await flushPromises()

    expect(revealEnvVarMock).toHaveBeenCalledTimes(1)
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

  it('shows empty-state banner when server has env vars but Helix has none', async () => {
    fetchEnvVarsMock.mockResolvedValue([])

    const wrapper = mount(EnvVarsTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    expect(fetchEnvVarsPullPreviewMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="env-var-empty-banner"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('shows pull preview keys without secret values', async () => {
    fetchEnvVarsPullPreviewMock.mockResolvedValue({
      status: 'ready',
      serverFileExists: true,
      new: ['DB_HOST'],
      changed: ['APP_KEY'],
      unchanged: [],
      helixOnly: ['LOCAL_ONLY'],
      skipped: [],
    })

    const wrapper = mount(EnvVarsTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const pullButton = document.body.querySelector('[data-testid="env-var-pull-button"]') as HTMLButtonElement
    pullButton.click()
    await flushPromises()
    await vi.advanceTimersByTimeAsync(250)

    expect(document.body.textContent).toContain('DB_HOST')
    expect(document.body.textContent).toContain('APP_KEY')
    expect(document.body.textContent).toContain('LOCAL_ONLY')
    expect(JSON.stringify(fetchEnvVarsPullPreviewMock.mock.results)).not.toMatch(/secret-value/)
    wrapper.unmount()
  })

  it('closes pull sheet before mirror confirmation dialog opens', async () => {
    fetchEnvVarsPullPreviewMock.mockResolvedValue({
      status: 'ready',
      serverFileExists: true,
      new: [],
      changed: [],
      unchanged: [],
      helixOnly: ['LOCAL_ONLY'],
      skipped: [],
    })

    const wrapper = mount(EnvVarsTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const pullButton = document.body.querySelector('[data-testid="env-var-pull-button"]') as HTMLButtonElement
    pullButton.click()
    await flushPromises()
    await vi.advanceTimersByTimeAsync(250)

    const mirrorRadio = document.body.querySelector('input[type="radio"][value="mirror_server"]') as HTMLInputElement
    mirrorRadio.click()
    await flushPromises()

    const applyButton = document.body.querySelector('[data-testid="env-var-pull-apply"]') as HTMLButtonElement
    applyButton.click()
    await flushPromises()

    expect(document.body.textContent).toContain('Mirror server environment variables')

    const openPullApplyButtons = Array.from(
      document.body.querySelectorAll('[data-testid="env-var-pull-apply"]'),
    ).filter((element) => element.closest('[data-state="open"]') !== null)

    expect(openPullApplyButtons).toHaveLength(0)
    wrapper.unmount()
  })
})
