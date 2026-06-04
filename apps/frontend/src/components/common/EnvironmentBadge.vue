<script setup lang="ts">
import { ShieldIcon } from '@lucide/vue'
import { cn } from '@/lib/utils'

interface Props {
  environment: string
  isProduction: boolean
}

defineProps<Props>()
</script>

<template>
  <span
    data-testid="environment-badge"
    :class="cn(
      'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs',
      isProduction && 'bg-red-100 font-medium text-red-800 dark:bg-red-950 dark:text-red-200',
      !isProduction && environment.toLowerCase() === 'staging' && 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200',
      !isProduction && environment.toLowerCase() !== 'staging' && 'bg-muted text-muted-foreground',
    )"
  >
    <ShieldIcon v-if="isProduction" class="size-3" />
    {{ environment }}
  </span>
</template>
