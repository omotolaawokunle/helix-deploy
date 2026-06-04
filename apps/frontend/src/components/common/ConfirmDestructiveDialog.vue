<script setup lang="ts">
import { computed, ref } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface Props {
  open: boolean
  title: string
  description: string
  confirmText: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
}>()

const typedConfirmation = ref('')
const isSubmitting = ref(false)

const canConfirm = computed(() => typedConfirmation.value === props.confirmText)

function closeDialog(): void {
  typedConfirmation.value = ''
  emit('update:open', false)
}

async function handleConfirm(): Promise<void> {
  if (!canConfirm.value) {
    return
  }

  isSubmitting.value = true

  try {
    emit('confirm')
    closeDialog()
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md" @pointer-down-outside.prevent>
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>

      <div class="space-y-2">
        <Label for="confirm-text">
          Type <span class="font-mono font-semibold">{{ confirmText }}</span> to confirm
        </Label>
        <Input
          id="confirm-text"
          v-model="typedConfirmation"
          data-testid="confirm-text-input"
          autocomplete="off"
        />
      </div>

      <DialogFooter>
        <Button variant="outline" type="button" @click="closeDialog">
          Cancel
        </Button>
        <Button
          variant="destructive"
          type="button"
          data-testid="confirm-destructive-button"
          :disabled="!canConfirm || isSubmitting"
          @click="handleConfirm"
        >
          Confirm
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
