import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { useSslRefreshFeedback, sslRowEntranceDelay } from '@/features/servers/composables/useSslRefreshFeedback'

describe('useSslRefreshFeedback', () => {
  it('exposes refresh hint when mounted in a component', () => {
    const wrapper = mount(defineComponent({
      setup() {
        const isBackgroundRefreshing = ref(true)
        const { refreshHint, showRefreshComplete } = useSslRefreshFeedback(isBackgroundRefreshing)

        return { refreshHint, showRefreshComplete }
      },
      template: '<span>{{ refreshHint }}</span>',
    }))

    expect(wrapper.text()).toContain('certbot')
  })

  it('caps ssl row entrance delay', () => {
    expect(sslRowEntranceDelay(0)).toBe('0ms')
    expect(sslRowEntranceDelay(8)).toBe('360ms')
    expect(sslRowEntranceDelay(20)).toBe('360ms')
  })
})
