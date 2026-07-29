import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SiteSettingsTab from '@/features/sites/components/SiteSettingsTab.vue'
import { Runtime, type Site } from '@/types'

const updateSiteMock = vi.fn()

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/composables/useActiveOrg', () => ({
  useActiveOrg: () => ({ orgId: { value: 'org-1' } }),
}))

vi.mock('@/features/auth/stores/useAuthStore', () => ({
  useAuthStore: () => ({ isAdmin: true }),
}))

vi.mock('@/features/sites/api', () => ({
  updateSite: (...args: unknown[]) => updateSiteMock(...args),
  rotateSiteWebhookSecret: vi.fn(),
  fetchGitProviders: vi.fn().mockResolvedValue([]),
  fetchGitRepositories: vi.fn().mockResolvedValue([]),
  fetchGitBranches: vi.fn().mockResolvedValue([]),
  deleteGitProviderToken: vi.fn(),
  storeGitProviderToken: vi.fn(),
  deleteSite: vi.fn(),
  fetchPipelines: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/build-runners/api', () => ({
  fetchBuildRunners: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/pipelines/api', () => ({
  fetchPipelines: vi.fn().mockResolvedValue([]),
}))

function createSite(overrides: Partial<Site> = {}): Site {
  return {
    id: 'site-1',
    organizationId: 'org-1',
    projectId: null,
    serverId: 'server-1',
    environmentId: null,
    domain: 'app.example.test',
    repositoryUrl: 'git@github.com:helix/example.git',
    repositoryProvider: 'github',
    gitCredentialConfigured: true,
    deployBranch: 'main',
    autoDeployEnabled: false,
    webhookUrl: null,
    hasWebhookSecret: false,
    preDeployScript: null,
    postDeployScript: null,
    preBuildScript: null,
    buildStrategy: 'on_server',
    buildRunnerId: null,
    runMigrations: false,
    dockerImage: null,
    dockerRegistry: null,
    dockerComposePath: null,
    deployMode: 'git',
    dockerBuildMode: null,
    phpVersion: null,
    pipelineId: null,
    runtime: Runtime.Native,
    status: 'active',
    autoCreateDns: false,
    isApex: false,
    projectDnsZoneId: null,
    dnsZoneId: null,
    dnsStatus: null,
    dnsProvider: null,
    dnsRecordIds: [],
    dnsError: null,
    enableSsl: false,
    sslStatus: null,
    sslProvider: null,
    sslError: null,
    sslChallenge: null,
    sslExpiresAt: null,
    sslCheckedAt: null,
    aliases: [],
    createdAt: '2026-01-01T00:00:00Z',
    updatedAt: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('SiteSettingsTab auto deploy', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    updateSiteMock.mockResolvedValue({
      site: createSite({ autoDeployEnabled: true, webhookUrl: 'https://api.test/hooks/sites/token' }),
      webhookSecret: 'secret-once',
    })
  })

  it('shows php version picker for php runtime sites', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: {
        site: createSite({ runtime: 'php' as Site['runtime'], phpVersion: '8.3' }),
        isProduction: false,
      },
      attachTo: document.body,
    })

    await flushPromises()

    expect(document.body.querySelector('[data-testid="php-version-section"]')).not.toBeNull()
    expect(document.body.querySelector('[data-testid="php-version-select"]')).not.toBeNull()

    wrapper.unmount()
  })

  it('shows auto deploy section for git sites', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: { site: createSite(), isProduction: false },
      attachTo: document.body,
    })

    await flushPromises()

    expect(document.body.querySelector('[data-testid="auto-deploy-section"]')).not.toBeNull()

    wrapper.unmount()
  })

  it('shows auto deploy section for docker build sites with repository', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: {
        site: createSite({
          deployMode: 'docker',
          dockerBuildMode: 'build',
          runtime: Runtime.Docker,
        }),
        isProduction: false,
      },
      attachTo: document.body,
    })

    await flushPromises()

    expect(document.body.querySelector('[data-testid="auto-deploy-section"]')).not.toBeNull()

    wrapper.unmount()
  })

  it('shows deploy mode controls for docker runtime sites', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: {
        site: createSite({
          deployMode: 'git',
          dockerBuildMode: null,
          runtime: Runtime.Docker,
        }),
        isProduction: false,
      },
      attachTo: document.body,
    })

    await flushPromises()

    expect(document.body.querySelector('[data-testid="deploy-mode-section"]')).not.toBeNull()
    expect(document.body.querySelector('[data-testid="deploy-mode-select"]')).not.toBeNull()

    wrapper.unmount()
  })

  it('hides auto deploy section for docker pull sites', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: {
        site: createSite({
          deployMode: 'docker',
          dockerBuildMode: 'pull',
          runtime: Runtime.Docker,
        }),
        isProduction: false,
      },
      attachTo: document.body,
    })

    await flushPromises()

    expect(document.body.querySelector('[data-testid="auto-deploy-section"]')).toBeNull()

    wrapper.unmount()
  })

  it('shows production confirmation when enabling auto deploy on production', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: { site: createSite(), isProduction: true },
      attachTo: document.body,
    })

    await flushPromises()

    const toggle = document.body.querySelector('[data-testid="auto-deploy-toggle"]') as HTMLInputElement
    toggle.checked = true
    toggle.dispatchEvent(new Event('change'))
    await flushPromises()

    expect(document.body.querySelector('[data-testid="production-auto-deploy-confirm"]')).not.toBeNull()

    wrapper.unmount()
  })

  it('does not require production confirmation on non-production sites', async () => {
    const wrapper = mount(SiteSettingsTab, {
      props: { site: createSite(), isProduction: false },
      attachTo: document.body,
    })

    await flushPromises()

    const toggle = document.body.querySelector('[data-testid="auto-deploy-toggle"]') as HTMLInputElement
    toggle.checked = true
    toggle.dispatchEvent(new Event('change'))
    await flushPromises()

    expect(document.body.querySelector('[data-testid="production-auto-deploy-confirm"]')).toBeNull()

    wrapper.unmount()
  })
})
