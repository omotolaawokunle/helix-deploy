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
})
