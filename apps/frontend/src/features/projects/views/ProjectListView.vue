<script setup lang="ts">
import { FolderKanbanIcon } from '@lucide/vue'
import ComingSoonView from '@/components/common/ComingSoonView.vue'
</script>

<template>
  <ComingSoonView
    title="Projects"
    description="Group servers and sites by application or client."
    feature-label="Projects"
    :icon="FolderKanbanIcon"
  />
</template>
