import { defineComponent, onUnmounted } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useDeploymentStream } from '@/features/deployments/composables/useDeploymentStream'

class MockEventSource {
  static instances: MockEventSource[] = []

  url: string

  withCredentials: boolean

  listeners = new Map<string, (event: MessageEvent<string>) => void>()

  closed = false

  constructor(url: string, options?: { withCredentials?: boolean }) {
    this.url = url
    this.withCredentials = options?.withCredentials ?? false
    MockEventSource.instances.push(this)
  }

  addEventListener(type: string, listener: (event: MessageEvent<string>) => void): void {
    this.listeners.set(type, listener)
  }

  close(): void {
    this.closed = true
  }

  emit(type: string, data: unknown): void {
    const listener = this.listeners.get(type)

    if (listener !== undefined) {
      listener({ data: JSON.stringify(data) } as MessageEvent<string>)
    }
  }
}

describe('useDeploymentStream', () => {
  beforeEach(() => {
    MockEventSource.instances = []
    vi.stubGlobal('EventSource', MockEventSource)
    vi.stubEnv('VITE_API_URL', 'https://api.test')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.unstubAllEnvs()
  })

  it('calls onLogLine with correct stepId and line', async () => {
    const onLogLine = vi.fn()

    mount(defineComponent({
      setup() {
        useDeploymentStream('dep-1', {
          onLogLine,
          onStepUpdate: vi.fn(),
          onStepStarted: vi.fn(),
          onComplete: vi.fn(),
          onApprovalRequired: vi.fn(),
        })
      },
      template: '<div />',
    }))

    await flushPromises()

    const source = MockEventSource.instances[0]
    source.emit('log.line', {
      stepId: 'step-1',
      line: 'Cloning repository',
      timestamp: '2026-06-04T12:00:00Z',
    })

    expect(onLogLine).toHaveBeenCalledWith(
      'step-1',
      'Cloning repository',
      '2026-06-04T12:00:00Z',
    )
    expect(source.withCredentials).toBe(true)
    expect(source.url).toBe('https://api.test/api/v1/deployments/dep-1/stream')
  })

  it('calls onComplete when deployment.completed event received', async () => {
    const onComplete = vi.fn()

    mount(defineComponent({
      setup() {
        useDeploymentStream('dep-2', {
          onLogLine: vi.fn(),
          onStepUpdate: vi.fn(),
          onStepStarted: vi.fn(),
          onComplete,
          onApprovalRequired: vi.fn(),
        })
      },
      template: '<div />',
    }))

    await flushPromises()

    const source = MockEventSource.instances[0]
    const payload = {
      status: 'succeeded',
      duration: 42,
      releaseId: 'rel-1',
      commitHash: 'abc123',
    }

    source.emit('deployment.completed', payload)

    expect(onComplete).toHaveBeenCalledWith(payload)
    expect(source.closed).toBe(true)
  })

  it('calls teardown on unmount (EventSource.close called)', async () => {
    const wrapper = mount(defineComponent({
      setup() {
        const { teardown } = useDeploymentStream('dep-3', {
          onLogLine: vi.fn(),
          onStepUpdate: vi.fn(),
          onStepStarted: vi.fn(),
          onComplete: vi.fn(),
          onApprovalRequired: vi.fn(),
        })

        onUnmounted(teardown)
      },
      template: '<div />',
    }))

    await flushPromises()

    const source = MockEventSource.instances[0]
    expect(source.closed).toBe(false)

    wrapper.unmount()
    await flushPromises()

    expect(source.closed).toBe(true)
  })
})
