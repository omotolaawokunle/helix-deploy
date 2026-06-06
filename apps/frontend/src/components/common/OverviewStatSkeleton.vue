<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'

interface Props {
  rows?: number
}

withDefaults(defineProps<Props>(), {
  rows: 4,
})
</script>

<template>
  <dl class="panel divide-y">
    <div
      v-for="row in rows"
      :key="row"
      class="flex items-center justify-between gap-4 px-4 py-3"
    >
      <Skeleton class="h-4 w-32" />
      <Skeleton class="h-4 w-12" />
    </div>
  </dl>
</template>
