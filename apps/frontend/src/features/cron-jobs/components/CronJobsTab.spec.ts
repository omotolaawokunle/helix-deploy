import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CronJobsTab from '@/features/cron-jobs/components/CronJobsTab.vue'

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

const describeCronExpressionMock = vi.fn()

vi.mock('@/features/cron-jobs/api', () => ({
  fetchCronJobs: vi.fn().mockResolvedValue([]),
  describeCronExpression: (...args: unknown[]) => describeCronExpressionMock(...args),
  createCronJob: vi.fn(),
  updateCronJob: vi.fn(),
  toggleCronJob: vi.fn(),
}))

const fetchServerSitesMock = vi.fn().mockResolvedValue([
  {
    id: 'site-1',
    domain: 'app.example.test',
    webroot: '/var/www/app.example.test/current/public',
    runtime: 'php',
  },
])

vi.mock('@/features/sites/api', () => ({
  fetchServerSites: (...args: unknown[]) => fetchServerSitesMock(...args),
}))

describe('CronJobsTab expression description', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('updates description reactively as the user types', async () => {
    describeCronExpressionMock.mockResolvedValue('Every 5 minutes')

    mount(CronJobsTab, {
      props: { serverId: 'server-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const addButton = Array.from(document.body.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Add cron job'))

    addButton?.click()
    await flushPromises()

    const input = document.body.querySelector('[data-testid="cron-create-expression-input"]') as HTMLInputElement
    input.value = '*/5 * * * *'
    input.dispatchEvent(new Event('input', { bubbles: true }))

    await vi.advanceTimersByTimeAsync(350)
    await flushPromises()

    expect(describeCronExpressionMock).toHaveBeenCalledWith('*/5 * * * *')

    const description = document.body.querySelector('[data-testid="cron-expression-description"]')
    expect(description?.textContent).toContain('Every 5 minutes')
  })

  it('shows scheduler preset controls in create sheet', async () => {
    mount(CronJobsTab, {
      props: { serverId: 'server-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const addButton = Array.from(document.body.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Add cron job'))

    addButton?.click()
    await flushPromises()

    expect(document.body.querySelector('[data-testid="cron-preset-select"]')).not.toBeNull()
  })
})

describe('CronJobsTab discovered jobs', () => {
  it('shows server crontab jobs without edit controls', async () => {
    const { fetchCronJobs } = await import('@/features/cron-jobs/api')
    vi.mocked(fetchCronJobs).mockResolvedValueOnce([{
      id: 'cron-1',
      serverId: 'server-1',
      organizationId: 'org-1',
      expression: '* * * * *',
      command: 'cd /var/www/live/current && php artisan schedule:run',
      user: 'root',
      active: true,
      external: true,
      siteId: 'site-1',
      description: 'Scheduled as `* * * * *`',
      lastRunAt: null,
      createdAt: '2026-10-27T00:00:00Z',
      updatedAt: '2026-10-27T00:00:00Z',
    }])

    mount(CronJobsTab, {
      props: { serverId: 'server-1' },
      attachTo: document.body,
    })

    await flushPromises()

    expect(document.body.textContent).toContain('Server crontab')
    const expression = document.body.querySelector('[data-testid="cron-expression-input"]') as HTMLInputElement
    expect(expression.disabled).toBe(true)
  })
})
