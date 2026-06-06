import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'

describe('ConfirmDestructiveDialog', () => {
  it('disables confirm button until correct text is typed', async () => {
    mount(ConfirmDestructiveDialog, {
      attachTo: document.body,
      props: {
        open: true,
        title: 'Delete server',
        description: 'This action cannot be undone.',
        confirmText: 'delete-server',
      },
    })

    await flushPromises()
    await nextTick()

    const confirmButton = document.body.querySelector(
      '[data-testid="confirm-destructive-button"]',
    ) as HTMLButtonElement
    const input = document.body.querySelector(
      '[data-testid="confirm-text-input"]',
    ) as HTMLInputElement

    expect(confirmButton).toBeTruthy()
    expect(confirmButton.disabled).toBe(true)

    input.value = 'delete-server'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()

    expect(confirmButton.disabled).toBe(false)
  })
})
