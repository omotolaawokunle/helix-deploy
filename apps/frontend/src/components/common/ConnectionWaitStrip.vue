<script setup lang="ts">
interface Props {
  label?: string
}

withDefaults(defineProps<Props>(), {
  label: 'Verifying SSH connection…',
})
</script>

<template>
  <div
    class="mt-4 space-y-2 border-t pt-4"
    role="status"
    aria-live="polite"
  >
    <div class="h-1.5 w-full animate-pulse rounded-full bg-muted motion-reduce:animate-none" />
    <div class="h-1.5 w-2/3 animate-pulse rounded-full bg-muted motion-reduce:animate-none" />
    <p class="text-xs text-muted-foreground">
      {{ label }}
    </p>
  </div>
</template>
