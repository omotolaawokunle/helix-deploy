<script setup lang="ts">
interface Props {
  title: string
  description?: string
  loading?: boolean
}

defineProps<Props>()
</script>

<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0 space-y-1">
      <template v-if="loading">
        <div class="h-8 w-48 max-w-full animate-pulse rounded-md bg-muted" />
        <div
          v-if="description !== undefined"
          class="h-4 w-72 max-w-full animate-pulse rounded-md bg-muted"
        />
      </template>
      <template v-else>
        <h1 class="page-title animate-page-in">
          {{ title }}
        </h1>
        <p
          v-if="description"
          class="animate-page-in animate-page-in-delay-1 text-sm text-muted-foreground"
        >
          {{ description }}
        </p>
      </template>
    </div>

    <div
      v-if="$slots.actions && !loading"
      class="animate-page-in animate-page-in-delay-1 flex shrink-0 flex-wrap items-center gap-2"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
