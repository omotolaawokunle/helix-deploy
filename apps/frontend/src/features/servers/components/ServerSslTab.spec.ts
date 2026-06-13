import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ServerSslTab from '@/features/servers/components/ServerSslTab.vue'
import type { ServerSslOverview } from '@/features/servers/types'

const fetchServerSslCertificatesMock = vi.fn()
const syncServerSslCertificatesMock = vi.fn()
const adoptServerSslCertificatesMock = vi.fn()
const renewServerSslCertificatesMock = vi.fn()

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/features/servers/api', () => ({
  fetchServerSslCertificates: (...args: unknown[]) => fetchServerSslCertificatesMock(...args),
  syncServerSslCertificates: (...args: unknown[]) => syncServerSslCertificatesMock(...args),
  adoptServerSslCertificates: (...args: unknown[]) => adoptServerSslCertificatesMock(...args),
  renewServerSslCertificates: (...args: unknown[]) => renewServerSslCertificatesMock(...args),
}))

const overview: ServerSslOverview = {
  hasCertbot: true,
  activeCertificateCount: 1,
  expiringSoonCount: 0,
  nearestExpiryAt: '2026-12-31T00:00:00+00:00',
  syncQueued: false,
  certificates: [
    {
      siteId: 'site-1',
      domain: 'app.example.test',
      sslStatus: 'active',
      sslExpiresAt: '2026-12-31T00:00:00+00:00',
      sslCheckedAt: '2026-06-01T00:00:00+00:00',
      daysUntilExpiry: 200,
    },
  ],
}

describe('ServerSslTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchServerSslCertificatesMock.mockResolvedValue(overview)
    syncServerSslCertificatesMock.mockResolvedValue(undefined)
    adoptServerSslCertificatesMock.mockResolvedValue(undefined)
    renewServerSslCertificatesMock.mockResolvedValue(undefined)
  })

  it('renders certificate rows after load', async () => {
    const wrapper = mount(ServerSslTab, {
      props: {
        serverId: 'server-1',
        isProduction: false,
        canManage: true,
      },
      attachTo: document.body,
    })

    await flushPromises()

    expect(fetchServerSslCertificatesMock).toHaveBeenCalledWith('server-1')
    expect(document.body.querySelectorAll('[data-testid="server-ssl-row"]').length).toBe(1)
    expect(document.body.textContent).toContain('app.example.test')

    wrapper.unmount()
  })

  it('queues adopt existing ssl', async () => {
    const wrapper = mount(ServerSslTab, {
      props: {
        serverId: 'server-1',
        isProduction: false,
        canManage: true,
      },
      attachTo: document.body,
    })

    await flushPromises()

    const adoptButton = document.body.querySelector('[data-testid="server-ssl-adopt-button"]') as HTMLButtonElement
    adoptButton.click()
    await flushPromises()

    expect(adoptServerSslCertificatesMock).toHaveBeenCalledWith('server-1')

    wrapper.unmount()
  })

  it('opens renew confirmation dialog', async () => {
    const wrapper = mount(ServerSslTab, {
      props: {
        serverId: 'server-1',
        isProduction: true,
        canManage: true,
      },
      attachTo: document.body,
    })

    await flushPromises()

    const renewButton = Array.from(document.body.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Renew all'))

    expect(renewButton).toBeDefined()
    renewButton?.click()
    await flushPromises()

    expect(document.body.textContent).toContain('Renew all SSL certificates')

    wrapper.unmount()
  })
})
