<script setup lang="ts">
import { FolderKanbanIcon } from '@lucide/vue'
import ComingSoonView from '@/components/common/ComingSoonView.vue'
</script>

<template>
  <ComingSoonView
    title="Project"
    description="Project details and linked servers."
    feature-label="Project details"
    :icon="FolderKanbanIcon"
  />
</template>
