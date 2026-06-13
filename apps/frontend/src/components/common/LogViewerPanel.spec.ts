import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import LogViewerPanel from '@/components/common/LogViewerPanel.vue'

describe('LogViewerPanel', () => {
  it('shows loading state', () => {
    const wrapper = mount(LogViewerPanel, {
      props: {
        lines: [],
        isLoading: true,
      },
    })

    expect(wrapper.find('[data-testid="log-viewer-loading"]').exists()).toBe(true)
  })

  it('renders log lines', () => {
    const wrapper = mount(LogViewerPanel, {
      props: {
        lines: ['line one', 'line two'],
        isLoading: false,
      },
    })

    const content = wrapper.find('[data-testid="log-viewer-content"]')

    expect(content.text()).toContain('line one')
    expect(content.text()).toContain('line two')
  })

  it('shows error message with retry action', async () => {
    const wrapper = mount(LogViewerPanel, {
      props: {
        lines: [],
        isLoading: false,
        errorMessage: 'Unable to fetch logs.',
      },
    })

    expect(wrapper.find('[data-testid="log-viewer-error"]').text()).toContain('Unable to fetch logs.')

    await wrapper.find('[data-testid="log-viewer-error"] button').trigger('click')

    expect(wrapper.emitted('retry')).toHaveLength(1)
  })

  it('shows empty message when no lines', () => {
    const wrapper = mount(LogViewerPanel, {
      props: {
        lines: [],
        isLoading: false,
        emptyMessage: 'Nothing here yet.',
      },
    })

    expect(wrapper.find('[data-testid="log-viewer-empty"]').text()).toContain('Nothing here yet.')
  })

  it('rotates loading status messages while fetching', async () => {
    vi.useFakeTimers()

    const wrapper = mount(LogViewerPanel, {
      props: {
        lines: [],
        isLoading: true,
      },
    })

    expect(wrapper.find('[data-testid="log-viewer-loading"]').text())
      .toContain('Connecting over SSH')

    await vi.advanceTimersByTimeAsync(3300)

    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-testid="log-viewer-loading"]').text())
      .toContain('Running tail')

    vi.useRealTimers()
  })

  it('shows line count footer for ready snapshots', () => {
    const wrapper = mount(LogViewerPanel, {
      props: {
        lines: ['a', 'b', 'c'],
        isLoading: false,
        requestedLines: 100,
      },
    })

    expect(wrapper.find('[data-testid="log-viewer-footer"]').text()).toContain('Showing 3 of 100 requested lines')
  })

  it('uses virtual scrolling for large log snapshots', () => {
    const lines = Array.from({ length: 80 }, (_, index) => `line ${index}`)

    const wrapper = mount(LogViewerPanel, {
      props: {
        lines,
        isLoading: false,
      },
    })

    expect(wrapper.find('[data-testid="log-viewer-virtual"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="log-viewer-content"]').exists()).toBe(false)
  })
})
