import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DeploymentLogViewer from '@/features/deployments/components/DeploymentLogViewer.vue'
import type { DeploymentDetail } from '@/features/deployments/types'

const fetchDeploymentMock = vi.fn()
const streamSetup = vi.fn()

vi.mock('@/features/deployments/api', () => ({
  fetchDeployment: (...args: unknown[]) => fetchDeploymentMock(...args),
}))

vi.mock('@/features/deployments/composables/useDeploymentStream', () => ({
  useDeploymentStream: (
    deploymentId: string,
    callbacks: {
      onLogLine: (stepId: string, line: string, timestamp: string) => void
      onStepUpdate: (stepId: string, status: string, duration: number | null) => void
      onStepStarted: (stepId: string, name: string, order: number, status: string) => void
    },
  ) => {
    streamSetup(deploymentId, callbacks)

    return { teardown: vi.fn() }
  },
}))

function buildDeployment(overrides: Partial<DeploymentDetail> = {}): DeploymentDetail {
  return {
    id: 'dep-1',
    organizationId: 'org-1',
    siteId: 'site-1',
    type: 'deploy',
    status: 'running',
    triggerType: 'manual',
    branch: 'main',
    commitHash: 'abcdef123456',
    commitMessage: 'Fix deploy viewer',
    releasePath: null,
    pipelineRunId: null,
    isRollbackable: false,
    triggeredBy: { id: 'user-1', name: 'Alex' },
    startedAt: '2026-06-04T10:00:00Z',
    finishedAt: null,
    createdAt: '2026-06-04T10:00:00Z',
    updatedAt: '2026-06-04T10:00:00Z',
    duration: null,
    activeReleaseId: null,
    site: null,
    steps: [
      {
        id: 'step-1',
        deploymentId: 'dep-1',
        name: 'Clone repository',
        status: 'success',
        order: 1,
        exitCode: 0,
        startedAt: '2026-06-04T10:00:00Z',
        finishedAt: '2026-06-04T10:00:10Z',
      },
      {
        id: 'step-2',
        deploymentId: 'dep-1',
        name: 'Install dependencies',
        status: 'running',
        order: 2,
        exitCode: null,
        startedAt: '2026-06-04T10:00:11Z',
        finishedAt: null,
      },
    ],
    ...overrides,
  }
}

describe('DeploymentLogViewer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchDeploymentMock.mockResolvedValue(buildDeployment())
  })

  it('renders existing steps on mount', async () => {
    const wrapper = mount(DeploymentLogViewer, {
      props: { deploymentId: 'dep-1' },
    })

    await flushPromises()

    expect(wrapper.find('[data-testid="deployment-step-step-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="deployment-step-step-2"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Clone repository')
    expect(wrapper.text()).toContain('Install dependencies')
  })

  it('appends new log lines in correct step section', async () => {
    const wrapper = mount(DeploymentLogViewer, {
      props: { deploymentId: 'dep-1' },
    })

    await flushPromises()

    const callbacks = streamSetup.mock.calls[0][1] as {
      onLogLine: (stepId: string, line: string, timestamp: string) => void
    }

    callbacks.onLogLine('step-2', '  + composer install', '2026-06-04T10:01:00Z')
    await nextTick()

    const body = wrapper.find('[data-testid="deployment-step-body-step-2"]')
    expect(body.text()).toContain('composer install')
    expect(body.find('.text-green-400').exists()).toBe(true)
  })

  it('running step is expanded and highlighted', async () => {
    const wrapper = mount(DeploymentLogViewer, {
      props: { deploymentId: 'dep-1' },
    })

    await flushPromises()

    const runningHeader = wrapper.find('[data-testid="deployment-step-step-2"] button')
    expect(runningHeader.classes().join(' ')).toContain('border-blue-500')
    expect(wrapper.find('[data-testid="deployment-step-body-step-2"]').exists()).toBe(true)
  })

  it('failed step is expanded with red border', async () => {
    fetchDeploymentMock.mockResolvedValue(buildDeployment({
      steps: [
        {
          id: 'step-fail',
          deploymentId: 'dep-1',
          name: 'Deploy release',
          status: 'failed',
          order: 1,
          exitCode: 1,
          startedAt: '2026-06-04T10:00:00Z',
          finishedAt: '2026-06-04T10:00:05Z',
        },
      ],
    }))

    const wrapper = mount(DeploymentLogViewer, {
      props: { deploymentId: 'dep-1' },
    })

    await flushPromises()

    const failedHeader = wrapper.find('[data-testid="deployment-step-step-fail"] button')
    expect(failedHeader.classes().join(' ')).toContain('border-red-500')
    expect(wrapper.find('[data-testid="deployment-step-body-step-fail"]').exists()).toBe(true)
  })

  it('auto-scroll stops when user scrolls up; jump to latest appears', async () => {
    const wrapper = mount(DeploymentLogViewer, {
      props: { deploymentId: 'dep-1', autoScroll: true },
      attachTo: document.body,
    })

    await flushPromises()

    const scrollEl = wrapper.find('[data-testid="deployment-log-scroll"]').element as HTMLElement
    Object.defineProperty(scrollEl, 'scrollHeight', { value: 1000, configurable: true })
    Object.defineProperty(scrollEl, 'clientHeight', { value: 400, configurable: true })
    scrollEl.scrollTop = 0

    scrollEl.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.find('[data-testid="jump-to-latest"]').exists()).toBe(true)
  })

  it('jump to latest re-enables auto-scroll', async () => {
    const wrapper = mount(DeploymentLogViewer, {
      props: { deploymentId: 'dep-1', autoScroll: true },
      attachTo: document.body,
    })

    await flushPromises()

    const scrollEl = wrapper.find('[data-testid="deployment-log-scroll"]').element as HTMLElement
    Object.defineProperty(scrollEl, 'scrollHeight', { value: 800, configurable: true })
    Object.defineProperty(scrollEl, 'clientHeight', { value: 400, configurable: true })
    scrollEl.scrollTop = 0
    scrollEl.dispatchEvent(new Event('scroll'))
    await nextTick()

    await wrapper.get('[data-testid="jump-to-latest"]').trigger('click')
    await nextTick()

    expect(wrapper.find('[data-testid="jump-to-latest"]').exists()).toBe(false)

    const callbacks = streamSetup.mock.calls[0][1] as {
      onLogLine: (stepId: string, line: string, timestamp: string) => void
    }

    callbacks.onLogLine('step-2', 'new line', '2026-06-04T10:02:00Z')
    await nextTick()
    await flushPromises()

    expect(scrollEl.scrollTop).toBe(scrollEl.scrollHeight)
  })
})
