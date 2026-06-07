import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import FirstVisitHint from '@/features/onboarding/components/FirstVisitHint.vue'

describe('FirstVisitHint', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('shows the hint until dismissed', async () => {
    const wrapper = mount(FirstVisitHint, {
      props: {
        hintId: 'server-detail',
        title: 'SSH connection',
        description: 'HelixDeploy connects over SSH.',
      },
    })

    expect(wrapper.find('[data-testid="first-visit-hint-server-detail"]').exists()).toBe(true)

    await wrapper.get('[data-testid="first-visit-hint-dismiss-server-detail"]').trigger('click')

    expect(wrapper.find('[data-testid="first-visit-hint-server-detail"]').exists()).toBe(false)
  })

  it('does not show a previously dismissed hint', () => {
    localStorage.setItem('helix-hint-seen-site-detail', 'true')

    const wrapper = mount(FirstVisitHint, {
      props: {
        hintId: 'site-detail',
        title: 'Deployments',
        description: 'Trigger deploys from this tab.',
      },
    })

    expect(wrapper.find('[data-testid="first-visit-hint-site-detail"]').exists()).toBe(false)
  })
})
