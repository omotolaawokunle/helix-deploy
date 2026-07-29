import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DeploymentDetailPage from '@/features/deployments/pages/DeploymentDetailPage.vue'
import type { DeploymentDetail } from '@/features/deployments/types'
import { DeploymentStatus } from '@/types'

const fetchDeploymentMock = vi.fn()
const rollbackDeploymentMock = vi.fn()
const cancelDeploymentMock = vi.fn()
const logViewerControls = vi.hoisted(() => ({
  emitCompletedOnMount: false,
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'dep-1' }, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>',
  },
}))

vi.mock('vue-sonner', () => ({
  toast: {
    error: vi.fn(),
    success: vi.fn(),
    warning: vi.fn(),
  },
}))

vi.mock('@/features/deployments/api', () => ({
  fetchDeployment: (...args: unknown[]) => fetchDeploymentMock(...args),
  rollbackDeployment: (...args: unknown[]) => rollbackDeploymentMock(...args),
  cancelDeployment: (...args: unknown[]) => cancelDeploymentMock(...args),
  approvePipelineRun: vi.fn(),
  rejectPipelineRun: vi.fn(),
}))

vi.mock('@/features/deployments/components/DeploymentLogViewer.vue', () => ({
  default: {
    name: 'DeploymentLogViewer',
    props: ['deploymentId'],
    emits: ['completed', 'approval-required'],
    mounted() {
      if (logViewerControls.emitCompletedOnMount) {
        this.$emit('completed', {
          status: 'success',
          duration: 12,
          releaseId: 'rel-1',
          commitHash: 'abcdef1234567890',
        })
      }
    },
    template: '<div data-testid="deployment-log-viewer-stub" />',
  },
}))

function buildDeployment(overrides: Partial<DeploymentDetail> = {}): DeploymentDetail {
  return {
    id: 'dep-1',
    organizationId: 'org-1',
    siteId: 'site-1',
    type: 'deploy',
    status: DeploymentStatus.Succeeded,
    triggerType: 'manual',
    branch: 'main',
    commitHash: 'abcdef1234567890',
    commitMessage: 'Ship feature',
    releasePath: '/var/www/example/releases/1',
    pipelineRunId: null,
    buildStrategy: null,
    buildRunnerId: null,
    buildArtifactId: null,
    isRollbackable: true,
    triggeredBy: { id: 'user-1', name: 'Alex' },
    startedAt: '2026-06-04T10:00:00Z',
    finishedAt: '2026-06-04T10:05:00Z',
    createdAt: '2026-06-04T10:00:00Z',
    updatedAt: '2026-06-04T10:05:00Z',
    duration: 300,
    activeReleaseId: 'rel-1',
    site: {
      id: 'site-1',
      domain: 'app.example.test',
      deployBranch: 'main',
      serverId: 'server-1',
      isProduction: false,
    },
    steps: [],
    ...overrides,
  }
}

describe('DeploymentDetailPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    logViewerControls.emitCompletedOnMount = false
    fetchDeploymentMock.mockResolvedValue(buildDeployment())
  })

  it('shows rollback button for rollbackable deployments', async () => {
    const wrapper = mount(DeploymentDetailPage, {
      attachTo: document.body,
    })

    await flushPromises()

    expect(wrapper.find('[data-testid="rollback-button"]').exists()).toBe(true)
  })

  it('does not show rollback button for failed deployments', async () => {
    fetchDeploymentMock.mockResolvedValue(buildDeployment({
      status: DeploymentStatus.Failed,
      isRollbackable: false,
    }))

    const wrapper = mount(DeploymentDetailPage)

    await flushPromises()

    expect(wrapper.find('[data-testid="rollback-button"]').exists()).toBe(false)
  })

  it('shows production warning banner on production deployments', async () => {
    fetchDeploymentMock.mockResolvedValue(buildDeployment({
      site: {
        id: 'site-1',
        domain: 'app.example.test',
        deployBranch: 'main',
        serverId: 'server-1',
        isProduction: true,
      },
    }))

    const wrapper = mount(DeploymentDetailPage)

    await flushPromises()

    expect(wrapper.find('[data-testid="production-warning-banner"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="environment-badge"]').exists()).toBe(true)
  })

  it('production rollback requires reason and typed confirmation before submit', async () => {
    fetchDeploymentMock.mockResolvedValue(buildDeployment({
      site: {
        id: 'site-1',
        domain: 'app.example.test',
        deployBranch: 'main',
        serverId: 'server-1',
        isProduction: true,
      },
    }))

    mount(DeploymentDetailPage, {
      attachTo: document.body,
    })

    await flushPromises()

    const rollbackButton = document.body.querySelector(
      '[data-testid="rollback-button"]',
    ) as HTMLButtonElement
    rollbackButton.click()
    await flushPromises()

    const submitButton = document.body.querySelector(
      '[data-testid="confirm-destructive-button"]',
    ) as HTMLButtonElement

    expect(submitButton.disabled).toBe(true)

    const textarea = document.body.querySelector(
      '[data-testid="rollback-reason"]',
    ) as HTMLTextAreaElement
    textarea.value = 'Critical regression in checkout flow'
    textarea.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    expect(submitButton.disabled).toBe(true)

    const confirmInput = document.body.querySelector(
      '[data-testid="confirm-text-input"]',
    ) as HTMLInputElement
    confirmInput.value = 'rollback'
    confirmInput.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    expect(submitButton.disabled).toBe(false)
  })

  it('refreshes quietly on stream completion without remounting the page shell', async () => {
    logViewerControls.emitCompletedOnMount = true

    let resolveSecondFetch: ((value: DeploymentDetail) => void) | undefined
    const secondFetch = new Promise<DeploymentDetail>((resolve) => {
      resolveSecondFetch = resolve
    })

    fetchDeploymentMock
      .mockResolvedValueOnce(buildDeployment({ status: DeploymentStatus.Running, isRollbackable: false }))
      .mockImplementationOnce(() => secondFetch)

    const wrapper = mount(DeploymentDetailPage, {
      attachTo: document.body,
    })

    await flushPromises()

    expect(fetchDeploymentMock).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="deployment-log-viewer-stub"]').exists()).toBe(true)
    expect(wrapper.find('.page-title').exists()).toBe(true)

    resolveSecondFetch?.(buildDeployment({ status: DeploymentStatus.Failed }))
    await flushPromises()

    expect(wrapper.find('[data-testid="deployment-log-viewer-stub"]').exists()).toBe(true)
    expect(wrapper.find('.page-title').exists()).toBe(true)
    expect(fetchDeploymentMock).toHaveBeenCalledTimes(2)
  })

  it('does not refetch when stream completion arrives for an already-failed deployment', async () => {
    logViewerControls.emitCompletedOnMount = true
    fetchDeploymentMock.mockResolvedValue(buildDeployment({
      status: DeploymentStatus.Failed,
      isRollbackable: false,
    }))

    const wrapper = mount(DeploymentDetailPage, {
      attachTo: document.body,
    })

    await flushPromises()

    expect(fetchDeploymentMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="deployment-log-viewer-stub"]').exists()).toBe(true)
    expect(wrapper.find('.page-title').exists()).toBe(true)
  })
})
