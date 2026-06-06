<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton'
import { TableBody, TableCell, TableRow } from '@/components/ui/table'

interface Props {
  rows?: number
  columns?: number
}

withDefaults(defineProps<Props>(), {
  rows: 5,
  columns: 4,
})
</script>

<template>
  <TableBody>
    <TableRow v-for="row in rows" :key="row">
      <TableCell v-for="column in columns" :key="column">
        <Skeleton class="h-4 w-full max-w-[12rem]" />
      </TableCell>
    </TableRow>
  </TableBody>
</template>
