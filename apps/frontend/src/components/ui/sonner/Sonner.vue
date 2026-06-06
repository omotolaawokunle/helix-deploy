<script lang="ts" setup>
import type { ToasterProps } from 'vue-sonner'
import { computed } from 'vue'

import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
  XIcon,
} from '@lucide/vue'
import { Toaster as Sonner } from 'vue-sonner'
import { cn } from '@/lib/utils'

const props = defineProps<ToasterProps>()

const mergedToastOptions = computed(() => ({
  ...props.toastOptions,
  classes: {
    toast: 'rounded-2xl',
    ...props.toastOptions?.classes,
  },
}))

const sonnerProps = computed(() => {
  const { toastOptions, ...rest } = props
  return rest
})
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'oklch(var(--popover))',
      '--normal-text': 'oklch(var(--popover-foreground))',
      '--normal-border': 'oklch(var(--border))',
      '--border-radius': 'var(--radius)',
      '--gray2': 'color-mix(in oklch, oklch(var(--popover)) 90%, transparent)',
      '--gray3': 'oklch(var(--border))',
      '--gray4': 'oklch(var(--border))',
      '--gray5': 'oklch(var(--border))',
      '--gray12': 'oklch(var(--popover-foreground))',
    }"
    :toast-options="mergedToastOptions"
    v-bind="sonnerProps"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
