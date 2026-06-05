<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import type { BuildRunner } from '@/features/build-runners/types'

interface Props {
  runner: BuildRunner
}

const props = defineProps<Props>()

const statusLabel = computed((): string => {
  const labels: Record<string, string> = {
    connecting: 'Connecting',
    online: 'Online',
    offline: 'Offline',
    maintenance: 'Maintenance',
  }

  return labels[props.runner.status] ?? props.runner.status
})

const statusClass = computed((): string => {
  const classes: Record<string, string> = {
    connecting: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200',
    online: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    offline: 'bg-destructive/10 text-destructive',
    maintenance: 'bg-muted text-muted-foreground',
  }

  return classes[props.runner.status] ?? 'bg-muted text-muted-foreground'
})

const slotSummary = computed(
  () => `${props.runner.activeBuilds} / ${props.runner.maxConcurrentBuilds} slots in use`,
)
</script>

<template>
  <article
    class="panel p-5"
    data-testid="build-runner-card"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-base font-semibold">
          {{ runner.name }}
        </h3>
        <p class="mt-0.5 font-mono text-xs text-muted-foreground">
          {{ runner.sshUser }}@{{ runner.ipAddress }}:{{ runner.sshPort }}
        </p>
      </div>

      <Badge
        variant="outline"
        :class="['border-transparent capitalize', statusClass]"
      >
        {{ statusLabel }}
      </Badge>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
      <Badge
        v-for="runtime in runner.supportedRuntimes"
        :key="runtime"
        variant="secondary"
        class="text-xs font-normal capitalize"
      >
        {{ runtime }}
      </Badge>
      <Badge
        v-if="runner.project"
        variant="outline"
        class="text-xs font-normal"
      >
        {{ runner.project.name }}
      </Badge>
    </div>

    <div class="mt-4 flex items-center justify-between border-t pt-4 text-sm text-muted-foreground">
      <span>{{ slotSummary }}</span>
      <span v-if="runner.cpuCores !== null || runner.ramGb !== null">
        <template v-if="runner.cpuCores !== null">{{ runner.cpuCores }} vCPU</template>
        <template v-if="runner.cpuCores !== null && runner.ramGb !== null"> · </template>
        <template v-if="runner.ramGb !== null">{{ runner.ramGb }} GB RAM</template>
      </span>
    </div>
  </article>
</template>
