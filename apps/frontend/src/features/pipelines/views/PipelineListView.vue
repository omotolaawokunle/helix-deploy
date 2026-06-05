<script setup lang="ts">
import { GitBranchIcon } from '@lucide/vue'
import ComingSoonView from '@/components/common/ComingSoonView.vue'
</script>

<template>
  <ComingSoonView
    title="Pipelines"
    description="Define build and deploy stages for your applications."
    feature-label="Pipelines"
    :icon="GitBranchIcon"
  />
</template>
