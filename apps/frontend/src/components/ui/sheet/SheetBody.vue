<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <div
    data-slot="sheet-body"
    :class="cn('min-h-0 flex-1 overflow-y-auto px-4 py-4', props.class)"
  >
    <slot />
  </div>
</template>
