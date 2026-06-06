<script setup lang="ts">
import type { Component } from 'vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'

interface Props {
  title: string
  description: string
  icon: Component
  featureLabel: string
}

defineProps<Props>()
</script>

<template>
  <div class="space-y-8">
    <PageHeader :title="title" :description="description" />
    <EmptyState
      :title="`${featureLabel} coming soon`"
      description="This area is under active development. Check back in a future release."
      :icon="icon"
    />
  </div>
</template>
