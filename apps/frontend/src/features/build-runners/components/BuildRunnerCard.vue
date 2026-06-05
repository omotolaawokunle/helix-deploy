<script setup lang="ts">
import { computed } from 'vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConnectionWaitStrip from '@/components/common/ConnectionWaitStrip.vue'
import { Badge } from '@/components/ui/badge'
import type { BuildRunner, BuildRunnerRuntime } from '@/features/build-runners/types'

interface Props {
  runner: BuildRunner
}

const props = defineProps<Props>()

const isConnecting = computed(() => props.runner.status === 'connecting')

const runtimeLabels: Record<BuildRunnerRuntime, string> = {
  php: 'PHP',
  nodejs: 'Node.js',
  python: 'Python',
  go: 'Go',
  static: 'Static',
  docker: 'Docker',
}

function runtimeLabel(runtime: BuildRunnerRuntime): string {
  return runtimeLabels[runtime] ?? runtime
}

const slotSummary = computed(
  () => `${props.runner.activeBuilds} / ${props.runner.maxConcurrentBuilds} slots in use`,
)

const hardwareSummary = computed((): string | null => {
  const parts: string[] = []

  if (props.runner.cpuCores !== null) {
    parts.push(`${props.runner.cpuCores} vCPU`)
  }

  if (props.runner.ramGb !== null) {
    parts.push(`${props.runner.ramGb} GB RAM`)
  }

  return parts.length > 0 ? parts.join(' · ') : null
})
</script>

<template>
  <article
    class="panel p-5 transition-colors duration-200"
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

      <StatusBadge :status="runner.status" type="build-runner" />
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
      <Badge
        v-for="runtime in runner.supportedRuntimes"
        :key="runtime"
        variant="secondary"
        class="text-xs font-normal"
      >
        {{ runtimeLabel(runtime) }}
      </Badge>
      <Badge
        v-if="runner.project"
        variant="outline"
        class="text-xs font-normal"
      >
        {{ runner.project.name }}
      </Badge>
    </div>

    <ConnectionWaitStrip
      v-if="isConnecting"
      data-testid="build-runner-connecting"
    />

    <div class="mt-4 flex items-center justify-between border-t pt-4 text-sm text-muted-foreground">
      <span>{{ slotSummary }}</span>
      <span v-if="hardwareSummary !== null" class="text-xs">
        {{ hardwareSummary }}
      </span>
    </div>
  </article>
</template>
