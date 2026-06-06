<script setup lang="ts">
import type { Component } from 'vue'
import { Button } from '@/components/ui/button'

interface Props {
  title: string
  description: string
  icon: Component
}

defineProps<Props>()

defineEmits<{
  action: []
}>()
</script>

<template>
  <div
    v-bind="$attrs"
    class="animate-page-in flex flex-col items-start gap-4 rounded-lg border border-dashed bg-muted/30 px-6 py-12 transition-shadow duration-200 hover:shadow-sm sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="flex items-start gap-4">
      <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <component :is="icon" class="size-5" />
      </div>
      <div>
        <h2 class="font-medium">
          {{ title }}
        </h2>
        <p class="mt-1 max-w-md text-sm text-muted-foreground">
          {{ description }}
        </p>
      </div>
    </div>

    <Button v-if="$slots.default" type="button" @click="$emit('action')">
      <slot />
    </Button>
  </div>
</template>
