import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import OrgSwitcher from '@/components/layout/OrgSwitcher.vue'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'

vi.mock('@/features/auth/api', () => ({
  switchOrganization: vi.fn(),
  fetchOrganizations: vi.fn().mockResolvedValue([]),
  createOrganization: vi.fn(),
}))

import { switchOrganization } from '@/features/auth/api'

describe('OrgSwitcher', () => {
  it('renders all user organizations and calls switch on select', async () => {
    const reloadSpy = vi.fn()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...window.location, reload: reloadSpy },
    })
    vi.mocked(switchOrganization).mockResolvedValue()

    setActivePinia(createPinia())
    const authStore = useAuthStore()

    authStore.$patch({
      organizations: [
        { id: 'org-1', name: 'Alpha Org', slug: 'alpha', createdAt: new Date().toISOString() },
        { id: 'org-2', name: 'Beta Org', slug: 'beta', createdAt: new Date().toISOString() },
      ],
    })
    authStore.setAuthUser({
      id: 'user-1',
      name: 'Test User',
      email: 'test@example.com',
      emailVerifiedAt: new Date().toISOString(),
      currentOrganizationId: 'org-1',
      currentOrganization: authStore.organizations[0],
      createdAt: new Date().toISOString(),
    })

    const wrapper = mount(OrgSwitcher, {
      attachTo: document.body,
    })

    await wrapper.get('button').trigger('click')
    await flushPromises()

    const options = document.body.querySelectorAll('[data-testid="org-option"]')

    expect(options).toHaveLength(2)
    expect(options[0]?.textContent).toContain('Alpha Org')
    expect(options[1]?.textContent).toContain('Beta Org')

    ;(options[1] as HTMLElement).click()
    await flushPromises()

    expect(switchOrganization).toHaveBeenCalledWith('org-2')
    expect(reloadSpy).toHaveBeenCalled()

    wrapper.unmount()
  })
})
