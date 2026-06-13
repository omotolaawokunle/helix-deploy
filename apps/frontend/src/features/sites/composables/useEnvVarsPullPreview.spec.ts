import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { fetchEnvVarsPullPreview } from '@/features/sites/api'
import { useEnvVarsPullPreview } from '@/features/sites/composables/useEnvVarsPullPreview'

vi.mock('@/features/sites/api', () => ({
  fetchEnvVarsPullPreview: vi.fn(),
}))

describe('useEnvVarsPullPreview', () => {
  it('deduplicates concurrent preview requests', async () => {
    const fetchMock = vi.mocked(fetchEnvVarsPullPreview)
    let resolvePreview: ((value: Awaited<ReturnType<typeof fetchEnvVarsPullPreview>>) => void) | undefined

    fetchMock.mockImplementation(() => new Promise((resolve) => {
      resolvePreview = resolve
    }))

    const TestComponent = defineComponent({
      setup() {
        const siteId = ref('site-1')
        const { loadPreview, pullPreview } = useEnvVarsPullPreview({ siteId })

        return { loadPreview, pullPreview }
      },
      template: '<div />',
    })

    const wrapper = mount(TestComponent)
    const vm = wrapper.vm as {
      loadPreview: (refresh?: boolean, silent?: boolean) => Promise<void>
      pullPreview: { new: string[] } | null
    }

    const first = vm.loadPreview(false)
    const second = vm.loadPreview(false)

    resolvePreview?.({
      status: 'ready',
      serverFileExists: true,
      new: ['APP_KEY'],
      changed: [],
      unchanged: [],
      helixOnly: [],
      skipped: [],
    })

    await Promise.all([first, second])

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(vm.pullPreview?.new).toEqual(['APP_KEY'])
    wrapper.unmount()
  })
})
