import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DaemonsTab from '@/features/daemons/components/DaemonsTab.vue'

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/composables/useDaemonChannel', () => ({
  useDaemonChannel: vi.fn(),
}))

const fetchDaemonsMock = vi.fn().mockResolvedValue([])
const fetchServerSitesMock = vi.fn()

vi.mock('@/features/daemons/api', () => ({
  fetchDaemons: (...args: unknown[]) => fetchDaemonsMock(...args),
  createDaemon: vi.fn(),
  deleteDaemon: vi.fn(),
  fetchDaemonLogs: vi.fn(),
  restartDaemon: vi.fn(),
  startDaemon: vi.fn(),
  stopDaemon: vi.fn(),
}))

vi.mock('@/features/sites/api', () => ({
  fetchServerSites: (...args: unknown[]) => fetchServerSitesMock(...args),
}))

describe('DaemonsTab presets', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchServerSitesMock.mockResolvedValue([
      {
        id: 'site-1',
        domain: 'app.example.test',
        webroot: '/var/www/app.example.test/current/public',
        runtime: 'php',
      },
    ])
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('loads server sites and shows preset controls in create sheet', async () => {
    mount(DaemonsTab, {
      props: { serverId: 'server-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const addButton = Array.from(document.body.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Add daemon'))

    addButton?.click()
    await flushPromises()

    expect(fetchServerSitesMock).toHaveBeenCalledWith('server-1')
    expect(document.body.querySelector('[data-testid="daemon-preset-select"]')).not.toBeNull()
  })
})
