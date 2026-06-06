<script setup lang="ts">
import { Button } from '@/components/ui/button'

interface Props {
  message: string
  retryLabel?: string
  isRetrying?: boolean
}

withDefaults(defineProps<Props>(), {
  retryLabel: 'Try again',
  isRetrying: false,
})

defineEmits<{
  retry: []
}>()
</script>

<template>
  <div
    v-bind="$attrs"
    class="panel border-dashed p-8 text-center"
    role="alert"
  >
    <p class="text-sm text-muted-foreground">
      {{ message }}
    </p>
    <Button
      type="button"
      variant="outline"
      size="sm"
      class="mt-4"
      :disabled="isRetrying"
      @click="$emit('retry')"
    >
      {{ isRetrying ? 'Retrying…' : retryLabel }}
    </Button>
  </div>
</template>
