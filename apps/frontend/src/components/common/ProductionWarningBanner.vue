<script setup lang="ts">
import { TriangleAlertIcon } from '@lucide/vue'

interface Props {
  resourceName: string
  isProduction: boolean
}

defineProps<Props>()
</script>

<template>
  <div
    v-if="isProduction"
    data-testid="production-warning-banner"
    class="flex w-full items-center justify-center gap-2 border-b border-red-800/30 bg-red-600 px-4 py-2.5 text-center text-sm font-medium text-white"
    role="alert"
  >
    <TriangleAlertIcon class="size-4 shrink-0" aria-hidden="true" />
    <span>You are viewing a PRODUCTION resource: {{ resourceName }}</span>
  </div>
</template>
