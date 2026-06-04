import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import AuditView from '@/features/audit/views/AuditView.vue'
import type { AuditLogEntry } from '@/types'
import { TeamRole } from '@/types'

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

const fetchOrganizationAuditLogsMock = vi.fn()

vi.mock('@/features/audit/api', () => ({
  fetchOrganizationAuditLogs: (...args: unknown[]) => fetchOrganizationAuditLogsMock(...args),
  exportAuditLogs: vi.fn(),
}))

vi.mock('@/features/organizations/api', () => ({
  fetchOrganizationMembers: vi.fn().mockResolvedValue([]),
}))

const entry: AuditLogEntry = {
  id: 'log-1',
  operation: 'site.updated',
  actor: { id: 'user-1', name: 'Owner', email: 'owner@test' },
  resourceType: 'App\\Modules\\Sites\\Models\\Site',
  resourceId: 'site-1',
  ipAddress: '127.0.0.1',
  requestId: null,
  createdAt: '2026-06-04T10:00:00Z',
  beforeState: { domain: 'old.test' },
  afterState: { domain: 'new.test' },
}

describe('AuditView row expand', () => {
  it('shows before/after state for owners', async () => {
    setActivePinia(createPinia())
    fetchOrganizationAuditLogsMock.mockResolvedValue({ data: [entry], meta: { next_cursor: null } })

    const { useAuthStore } = await import('@/features/auth/stores/useAuthStore')
    const authStore = useAuthStore()
    authStore.$patch({
      currentRole: TeamRole.Owner,
      user: {
        id: 'user-1',
        name: 'Owner',
        email: 'owner@test',
        emailVerifiedAt: '2026-01-01',
        currentOrganizationId: 'org-1',
        currentOrganization: {
          id: 'org-1',
          name: 'Acme',
          slug: 'acme',
          createdAt: '2026-01-01',
          updatedAt: '2026-01-01',
        },
        createdAt: '2026-01-01',
      },
    })

    mount(AuditView, { attachTo: document.body })
    await flushPromises()

    const row = document.body.querySelector('[data-testid="audit-log-row"]') as HTMLTableRowElement
    row.click()
    await flushPromises()

    const expanded = document.body.querySelector('[data-testid="audit-log-expanded"]')
    expect(expanded?.textContent).toContain('before_state')
    expect(expanded?.textContent).toContain('old.test')
  })

  it('hides state details for admins', async () => {
    setActivePinia(createPinia())
    fetchOrganizationAuditLogsMock.mockResolvedValue({
      data: [{ ...entry, beforeState: undefined, afterState: undefined }],
      meta: { next_cursor: null },
    })

    const { useAuthStore } = await import('@/features/auth/stores/useAuthStore')
    const authStore = useAuthStore()
    authStore.$patch({
      currentRole: TeamRole.Admin,
      user: {
        id: 'user-2',
        name: 'Admin',
        email: 'admin@test',
        emailVerifiedAt: '2026-01-01',
        currentOrganizationId: 'org-1',
        currentOrganization: {
          id: 'org-1',
          name: 'Acme',
          slug: 'acme',
          createdAt: '2026-01-01',
          updatedAt: '2026-01-01',
        },
        createdAt: '2026-01-01',
      },
    })

    const wrapper = mount(AuditView, { attachTo: document.body })
    await flushPromises()

    await wrapper.find('[data-testid="audit-log-row"]').trigger('click')
    await flushPromises()

    const expanded = wrapper.find('[data-testid="audit-log-expanded"]')
    expect(expanded.text()).toContain('only visible to organization owners')
    expect(expanded.text()).not.toContain('old.test')
    wrapper.unmount()
  })
})
