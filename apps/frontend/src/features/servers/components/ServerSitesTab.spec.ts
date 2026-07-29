import { flushPromises, mount } from '@vue/test-utils'
import { SelectRoot } from 'reka-ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ServerSitesTab from '@/features/servers/components/ServerSitesTab.vue'

const fetchServerSitesMock = vi.fn()

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}))

vi.mock('@/composables/useActiveOrg', () => ({
  useActiveOrg: () => ({ orgId: { value: 'org-1' } }),
}))

vi.mock('@/stores/useRealtimeStore', () => ({
  useRealtimeStore: () => ({
    serverInventoryRefreshId: null,
    consumeServerInventoryRefresh: vi.fn(),
  }),
}))

vi.mock('@/features/sites/composables/useSiteProvisioningChannel', () => ({
  useSiteProvisioningChannel: vi.fn(),
  patchSiteDnsSslFromBroadcast: vi.fn(),
}))

vi.mock('@/features/integrations/composables/useDnsProviderConnections', () => ({
  useDnsProviderConnections: () => ({
    ensureLoaded: vi.fn().mockResolvedValue(undefined),
    connectionFlags: { value: { cloudflare: false, digitalocean: false } },
  }),
}))

vi.mock('@/features/integrations/api', () => ({
  fetchProjectDnsZones: vi.fn().mockResolvedValue([]),
  buildHostnameFromPrefix: vi.fn(),
}))

vi.mock('@/features/servers/api', () => ({
  fetchProjects: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/sites/api', () => ({
  fetchServerSites: (...args: unknown[]) => fetchServerSitesMock(...args),
  createSite: vi.fn().mockResolvedValue({ id: 'site-1' }),
}))

async function openAddSiteSheet(): Promise<ReturnType<typeof mount>> {
  const wrapper = mount(ServerSitesTab, {
    props: { serverId: 'server-1' },
    attachTo: document.body,
  })

  await flushPromises()

  const addButton = Array.from(document.body.querySelectorAll('button'))
    .find((button) => button.textContent?.includes('Add site'))

  expect(addButton).toBeDefined()
  addButton?.click()
  await flushPromises()

  return wrapper
}

describe('ServerSitesTab add-site deploy mode', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchServerSitesMock.mockResolvedValue([])
  })

  it('shows deploy mode and git runtime by default', async () => {
    const wrapper = await openAddSiteSheet()

    expect(document.body.querySelector('[data-testid="deploy-mode-select"]')).not.toBeNull()
    expect(document.body.querySelector('[data-testid="git-runtime-section"]')).not.toBeNull()
    expect(document.body.querySelector('[data-testid="docker-deploy-section"]')).toBeNull()

    wrapper.unmount()
  })

  it('shows docker fields and hides git runtime when docker deploy mode is selected', async () => {
    const wrapper = await openAddSiteSheet()

    const deployModeSelect = wrapper.findAllComponents(SelectRoot)[1]
    deployModeSelect.vm.$emit('update:modelValue', 'docker')
    await flushPromises()

    expect(document.body.querySelector('[data-testid="docker-deploy-section"]')).not.toBeNull()
    expect(document.body.querySelector('[data-testid="docker-build-mode-select"]')).not.toBeNull()
    expect(document.body.querySelector('[data-testid="git-runtime-section"]')).toBeNull()

    wrapper.unmount()
  })
})
