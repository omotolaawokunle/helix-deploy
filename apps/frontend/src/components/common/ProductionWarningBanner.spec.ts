import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'

describe('ProductionWarningBanner', () => {
  it('renders only when isProduction is true', () => {
    const hidden = mount(ProductionWarningBanner, {
      props: {
        resourceName: 'api-server',
        isProduction: false,
      },
    })

    expect(hidden.find('[data-testid="production-warning-banner"]').exists()).toBe(false)

    const visible = mount(ProductionWarningBanner, {
      props: {
        resourceName: 'api-server',
        isProduction: true,
      },
    })

    expect(visible.get('[data-testid="production-warning-banner"]').text()).toContain('api-server')
    expect(visible.text()).toContain('PRODUCTION')
  })

  it('renders inline variant message for deploy flows', () => {
    const inline = mount(ProductionWarningBanner, {
      props: {
        resourceName: 'app.example.test',
        isProduction: true,
        variant: 'inline',
      },
    })

    expect(inline.get('[data-testid="production-warning-banner"]').text()).toContain(
      'deploying to PRODUCTION',
    )
  })
})
