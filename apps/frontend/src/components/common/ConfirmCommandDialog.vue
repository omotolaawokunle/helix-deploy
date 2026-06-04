<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface Props {
  open: boolean
  title?: string
  description: string
  command: string
  confirmButtonLabel?: string
}

withDefaults(defineProps<Props>(), {
  title: 'Confirm command',
  confirmButtonLabel: 'Run command',
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
}>()

function closeDialog(): void {
  emit('update:open', false)
}

function handleConfirm(): void {
  emit('confirm')
  closeDialog()
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-lg" data-testid="confirm-command-dialog">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>

      <pre
        class="log-panel max-h-40 overflow-auto p-4"
        data-testid="confirm-command-preview"
      >{{ command }}</pre>

      <DialogFooter>
        <Button variant="outline" type="button" @click="closeDialog">
          Cancel
        </Button>
        <Button
          type="button"
          data-testid="confirm-command-button"
          @click="handleConfirm"
        >
          {{ confirmButtonLabel }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
