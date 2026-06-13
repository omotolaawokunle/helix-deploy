import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ServerCard from '@/features/servers/components/ServerCard.vue'
import { ManagementMode, ServerStatus, type Server } from '@/types'

vi.mock('vue-router', () => ({
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a><slot /></a>',
  },
}))

function makeServer(overrides: Partial<Server> = {}): Server {
  return {
    id: 'server-1',
    hostname: 'web-01.example.test',
    ipAddress: '10.0.0.1',
    sshPort: 22,
    sshUser: 'deploy',
    provider: 'generic',
    providerInstanceId: null,
    region: null,
    serverType: null,
    os: 'Ubuntu 24.04',
    phpVersion: '8.3',
    nodeVersion: null,
    status: ServerStatus.Active,
    managementMode: ManagementMode.Managed,
    environment: null,
    project: null,
    tags: [],
    installedServices: [],
    healthStatus: null,
    sslSummary: null,
    createdAt: '2026-01-01T00:00:00+00:00',
    updatedAt: '2026-01-01T00:00:00+00:00',
    ...overrides,
  }
}

describe('ServerCard', () => {
  it('shows expiring soon ssl badge', () => {
    const wrapper = mount(ServerCard, {
      props: {
        server: makeServer({
          sslSummary: {
            activeCount: 2,
            expiringSoonCount: 1,
            nearestExpiryAt: '2026-06-20T00:00:00+00:00',
          },
        }),
      },
    })

    const badge = wrapper.get('[data-testid="server-ssl-badge"]')
    expect(badge.text()).toBe('SSL expiring soon')
    expect(badge.attributes('aria-label')).toContain('expiring within 30 days')
  })

  it('shows ssl active badge when certificates are healthy', () => {
    const wrapper = mount(ServerCard, {
      props: {
        server: makeServer({
          sslSummary: {
            activeCount: 1,
            expiringSoonCount: 0,
            nearestExpiryAt: '2026-12-31T00:00:00+00:00',
          },
        }),
      },
    })

    const badge = wrapper.get('[data-testid="server-ssl-badge"]')
    expect(badge.text()).toBe('SSL active')
  })

  it('hides ssl badge when no active certificates', () => {
    const wrapper = mount(ServerCard, {
      props: {
        server: makeServer({ sslSummary: null }),
      },
    })

    expect(wrapper.find('[data-testid="server-ssl-badge"]').exists()).toBe(false)
  })
})
