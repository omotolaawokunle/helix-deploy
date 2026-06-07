import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import DashboardView from '@/features/monitoring/views/DashboardView.vue'

vi.mock('vue-router', () => ({
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a><slot /></a>',
  },
}))

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/features/monitoring/api', () => ({
  loadDashboardData: vi.fn().mockResolvedValue({
    servers: [{
      id: 'server-1',
      hostname: 'prod-1',
      status: 'active',
      installedServices: [],
      environment: { id: 'env-1', name: 'production', label: 'Production', isProduction: true },
    }],
    deployments: [],
    stats: {
      activeServers: 1,
      deploymentsToday: 0,
      successfulToday: 0,
      failedToday: 0,
      serversWithIssues: 0,
    },
  }),
}))

vi.mock('@/features/sites/api', () => ({
  fetchOrgSites: vi.fn().mockResolvedValue([
    {
      id: 'site-1',
      domain: 'app.example.test',
      deployBranch: 'main',
      serverId: 'server-1',
    },
  ]),
}))

vi.mock('@/features/projects/api', () => ({
  fetchProjects: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/integrations/api', () => ({
  fetchDnsProviderConnections: vi.fn().mockResolvedValue({
    cloudflare: { connected: false, status: 'disconnected', connectedAt: null, connectedBy: null },
    digitalocean: { connected: false, status: 'disconnected', connectedAt: null, connectedBy: null },
  }),
}))

vi.mock('@/features/deployments/api', () => ({
  triggerDeployment: vi.fn(),
}))

async function patchAuthOrg(orgId = 'org-1'): Promise<void> {
  setActivePinia(createPinia())

  const { useAuthStore } = await import('@/features/auth/stores/useAuthStore')
  const authStore = useAuthStore()
  authStore.$patch({
    user: {
      id: 'user-1',
      name: 'Alex',
      email: 'alex@example.test',
      emailVerifiedAt: '2026-01-01',
      currentOrganizationId: orgId,
      currentOrganization: {
        id: orgId,
        name: 'Acme',
        slug: 'acme',
        createdAt: '2026-01-01',
        updatedAt: '2026-01-01',
      },
      createdAt: '2026-01-01',
    },
  })
}

describe('DashboardView getting started', () => {
  it('shows the setup checklist for a fresh organization', async () => {
    const { loadDashboardData } = await import('@/features/monitoring/api')
    vi.mocked(loadDashboardData).mockResolvedValueOnce({
      servers: [],
      deployments: [],
      stats: {
        activeServers: 0,
        deploymentsToday: 0,
        successfulToday: 0,
        failedToday: 0,
        serversWithIssues: 0,
      },
    })

    await patchAuthOrg()

    const wrapper = mount(DashboardView, { attachTo: document.body })
    await flushPromises()

    expect(document.body.querySelector('[data-testid="getting-started-panel"]')).toBeTruthy()
    expect(document.body.querySelector('[data-testid="onboarding-action-add-server"]')).toBeTruthy()
    wrapper.unmount()
  })

  it('hides the setup checklist after dismiss', async () => {
    const { loadDashboardData } = await import('@/features/monitoring/api')
    vi.mocked(loadDashboardData).mockResolvedValueOnce({
      servers: [],
      deployments: [],
      stats: {
        activeServers: 0,
        deploymentsToday: 0,
        successfulToday: 0,
        failedToday: 0,
        serversWithIssues: 0,
      },
    })

    await patchAuthOrg('org-dismiss')

    const wrapper = mount(DashboardView, { attachTo: document.body })
    await flushPromises()

    const dismiss = document.body.querySelector('[data-testid="getting-started-dismiss"]') as HTMLButtonElement
    dismiss.click()
    await flushPromises()

    expect(document.body.querySelector('[data-testid="getting-started-panel"]')).toBeFalsy()
    wrapper.unmount()
  })
})

describe('DashboardView quick deploy', () => {
  it('shows production warning when a production site is selected', async () => {
    await patchAuthOrg()

    const wrapper = mount(DashboardView, { attachTo: document.body })
    await flushPromises()

    const vm = wrapper.vm as { quickDeploySiteId: string }
    vm.quickDeploySiteId = 'site-1'
    await flushPromises()

    const warning = document.body.querySelector('[data-testid="quick-deploy-production-warning"]')
    expect(warning).toBeTruthy()
    wrapper.unmount()
  })
})
