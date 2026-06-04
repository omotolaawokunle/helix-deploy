<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowLeftIcon } from '@lucide/vue'

interface Props {
  to: string
  label: string
}

defineProps<Props>()
</script>

<template>
  <RouterLink
    :to="to"
    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
  >
    <ArrowLeftIcon class="size-4" />
    {{ label }}
  </RouterLink>
</template>
