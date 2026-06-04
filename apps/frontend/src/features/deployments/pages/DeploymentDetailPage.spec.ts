import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DeploymentDetailPage from '@/features/deployments/pages/DeploymentDetailPage.vue'
import type { DeploymentDetail } from '@/features/deployments/types'
import { DeploymentStatus } from '@/types'

const fetchDeploymentMock = vi.fn()
const rollbackDeploymentMock = vi.fn()
const cancelDeploymentMock = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'dep-1' }, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
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
}))

vi.mock('@/features/deployments/components/DeploymentLogViewer.vue', () => ({
  default: {
    name: 'DeploymentLogViewer',
    props: ['deploymentId'],
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
    vi.clearAllMocks()
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
})
