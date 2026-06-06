<script setup lang="ts">
import { computed } from 'vue'
import { TriangleAlertIcon } from '@lucide/vue'
import { cn } from '@/lib/utils'

interface Props {
  resourceName: string
  isProduction: boolean
  variant?: 'banner' | 'inline'
  message?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'banner',
})

const displayMessage = computed((): string => {
  if (props.message !== undefined) {
    return props.message
  }

  if (props.variant === 'inline') {
    return 'You are deploying to PRODUCTION'
  }

  return `You are viewing a PRODUCTION resource: ${props.resourceName}`
})
</script>

<template>
  <div
    v-if="isProduction"
    data-testid="production-warning-banner"
    :class="cn(
      'flex items-center gap-2 text-sm font-medium',
      variant === 'banner' && 'w-full justify-center border-b border-red-800/30 bg-red-600 px-4 py-2.5 text-center text-white',
      variant === 'inline' && 'rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-destructive',
    )"
    role="alert"
  >
    <TriangleAlertIcon
      class="size-4 shrink-0"
      :class="variant === 'inline' ? 'text-destructive' : 'text-white'"
      aria-hidden="true"
    />
    <span>{{ displayMessage }}</span>
  </div>
</template>
