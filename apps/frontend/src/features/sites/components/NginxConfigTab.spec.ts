import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NginxConfigTab from '@/features/sites/components/NginxConfigTab.vue'

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn() },
}))

vi.mock('@/features/sites/api', () => ({
  fetchNginxConfig: vi.fn().mockResolvedValue({
    siteId: 'site-1',
    domain: 'app.test',
    config: 'server { listen 80; }',
    updatedAt: '2026-06-04T10:00:00Z',
  }),
  saveNginxConfig: vi.fn(),
}))

describe('NginxConfigTab', () => {
  it('makes textarea editable when Edit is clicked', async () => {
    mount(NginxConfigTab, {
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
  })
})
