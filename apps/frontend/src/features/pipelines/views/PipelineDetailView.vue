<script setup lang="ts">
import { GitBranchIcon } from '@lucide/vue'
import ComingSoonView from '@/components/common/ComingSoonView.vue'
</script>

<template>
  <ComingSoonView
    title="Pipeline"
    description="Pipeline configuration and run history."
    feature-label="Pipeline details"
    :icon="GitBranchIcon"
  />
</template>
