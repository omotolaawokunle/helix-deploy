import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchNginxConfig } from '@/features/sites/api'
import NginxConfigTab from '@/features/sites/components/NginxConfigTab.vue'

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/features/sites/api', () => ({
  fetchNginxConfig: vi.fn().mockResolvedValue({
    siteId: 'site-1',
    domain: 'app.test',
    status: 'ready',
    config: 'server { listen 80; }',
    updatedAt: null,
  }),
  saveNginxConfig: vi.fn(),
}))

describe('NginxConfigTab', () => {
  it('makes textarea editable when Edit is clicked', async () => {
    const wrapper = mount(NginxConfigTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const textarea = document.body.querySelector('[data-testid="nginx-config-textarea"]') as HTMLTextAreaElement
    expect(textarea.readOnly).toBe(true)

    const editButton = document.body.querySelector('[data-testid="nginx-edit-button"]') as HTMLButtonElement
    editButton.click()
    await flushPromises()

    expect(textarea.readOnly).toBe(false)
    wrapper.unmount()
  })

  it('shows the file returned from the server after the read finishes', async () => {
    vi.useFakeTimers()
    vi.mocked(fetchNginxConfig)
      .mockReset()
      .mockResolvedValueOnce({
        siteId: 'site-1',
        domain: 'app.test',
        status: 'loading',
        config: '',
        updatedAt: null,
      })
      .mockResolvedValueOnce({
        siteId: 'site-1',
        domain: 'app.test',
        status: 'ready',
        config: 'location /app {\n    proxy_pass http://127.0.0.1:8080;\n}',
        updatedAt: null,
      })

    const wrapper = mount(NginxConfigTab, {
      props: { siteId: 'site-1' },
      attachTo: document.body,
    })

    await flushPromises()

    const textarea = document.body.querySelector('[data-testid="nginx-config-textarea"]') as HTMLTextAreaElement
    expect(textarea.value).toBe('')
    expect(fetchNginxConfig).toHaveBeenCalledWith('site-1', { refresh: true })

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()

    expect(fetchNginxConfig).toHaveBeenLastCalledWith('site-1', { refresh: false })
    expect(textarea.value).toContain('location /app')
    wrapper.unmount()
    vi.useRealTimers()
  })
})

afterEach(() => {
  vi.useRealTimers()
  vi.mocked(fetchNginxConfig).mockReset().mockResolvedValue({
    siteId: 'site-1',
    domain: 'app.test',
    status: 'ready',
    config: 'server { listen 80; }',
    updatedAt: null,
  })
})
