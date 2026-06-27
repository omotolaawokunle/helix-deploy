import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import SiteLogsTab from '@/features/sites/components/SiteLogsTab.vue'

async function mountSiteLogsTab(props: {
  siteId: string
  serverId: string
  runtime: string
}): Promise<ReturnType<typeof mount>> {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/servers/:id',
        name: 'server-detail',
        component: { template: '<div />' },
      },
    ],
  })

  await router.push('/')

  return mount(SiteLogsTab, {
    props,
    global: {
      plugins: [router],
    },
  })
}

vi.mock('@/features/sites/api', () => ({
  fetchSiteLogs: vi.fn(async () => ({
    status: 'loading' as const,
    lines: [],
  })),
}))

vi.mock('@/composables/useServerLogsChannel', () => ({
  useServerLogsChannel: vi.fn(),
}))

describe('SiteLogsTab', () => {
  it('shows unavailable state for static runtimes with link to server logs', async () => {
    const wrapper = await mountSiteLogsTab({
      siteId: 'site-1',
      serverId: 'server-1',
      runtime: 'static',
    })

    expect(wrapper.find('[data-testid="site-logs-unavailable"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('No application logs for this runtime')

    const link = wrapper.find('a')

    expect(link.attributes('href')).toContain('/servers/server-1')
    expect(link.attributes('href')).toContain('tab=logs')
  })

  it('renders log controls for php sites', async () => {
    const wrapper = await mountSiteLogsTab({
      siteId: 'site-1',
      serverId: 'server-1',
      runtime: 'php',
    })

    expect(wrapper.find('[data-testid="site-logs-refresh"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="site-logs-unavailable"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Laravel log file')
  })
})
