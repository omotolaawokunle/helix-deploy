import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import CommandRunnerTab from '@/features/commands/components/CommandRunnerTab.vue'

vi.mock('vue-sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn(), message: vi.fn() },
}))

vi.mock('@/features/commands/api', () => ({
  fetchCommands: vi.fn().mockResolvedValue({ data: [], meta: { next_cursor: null } }),
  runCommand: vi.fn(),
  isCommandConfirmationRequired: vi.fn().mockReturnValue(false),
}))

describe('CommandRunnerTab', () => {
  it('shows production warning banner on production servers', async () => {
    mount(CommandRunnerTab, {
      props: { serverId: 'server-1', isProduction: true },
      attachTo: document.body,
    })

    await flushPromises()

    const banner = document.body.querySelector('[data-testid="production-warning-banner"]')
    expect(banner).toBeTruthy()
  })

  it('shows confirmation dialog before running on production', async () => {
    mount(CommandRunnerTab, {
      props: { serverId: 'server-1', isProduction: true },
      attachTo: document.body,
    })

    await flushPromises()

    const input = document.body.querySelector('[data-testid="command-input"]') as HTMLInputElement
    input.value = 'php artisan cache:clear'
    input.dispatchEvent(new Event('input', { bubbles: true }))

    const runButton = document.body.querySelector('[data-testid="command-run-button"]') as HTMLButtonElement
    runButton.click()
    await flushPromises()

    const dialog = document.body.querySelector('[data-testid="confirm-command-dialog"]')
    expect(dialog).toBeTruthy()
  })
})
