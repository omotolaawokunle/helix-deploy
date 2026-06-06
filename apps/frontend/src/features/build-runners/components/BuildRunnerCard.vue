<script setup lang="ts">
import { computed } from 'vue'
import { LoaderCircleIcon, PlugIcon, SettingsIcon, Trash2Icon } from '@lucide/vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConnectionWaitStrip from '@/components/common/ConnectionWaitStrip.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { buildRunnerRuntimeLabel } from '@/features/build-runners/constants'
import type { BuildRunner } from '@/features/build-runners/types'

interface Props {
  runner: BuildRunner
  canManage?: boolean
  isTestingConnection?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canManage: false,
  isTestingConnection: false,
})

const emit = defineEmits<{
  edit: []
  delete: []
  'test-connection': []
}>()

const isConnecting = computed(
  () => props.runner.status === 'connecting' || props.isTestingConnection,
)

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

const hasActiveSlots = computed(() => props.runner.activeBuilds > 0)
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
        {{ buildRunnerRuntimeLabel(runtime) }}
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

    <div
      v-if="canManage"
      class="mt-4 flex flex-wrap gap-2 border-t pt-4"
      data-testid="build-runner-actions"
    >
      <Button
        type="button"
        size="sm"
        variant="outline"
        @click="emit('edit')"
      >
        <SettingsIcon class="mr-1.5 size-3.5" aria-hidden="true" />
        Edit
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        :disabled="isTestingConnection"
        data-testid="build-runner-test-connection"
        @click="emit('test-connection')"
      >
        <LoaderCircleIcon
          v-if="isTestingConnection"
          class="mr-1.5 size-3.5 animate-spin motion-reduce:animate-none"
          aria-hidden="true"
        />
        <PlugIcon
          v-else
          class="mr-1.5 size-3.5"
          aria-hidden="true"
        />
        Test connection
      </Button>
      <Button
        type="button"
        size="sm"
        variant="destructive"
        :disabled="hasActiveSlots"
        :title="hasActiveSlots ? 'Wait for active builds to finish before deleting.' : undefined"
        data-testid="build-runner-delete"
        @click="emit('delete')"
      >
        <Trash2Icon class="mr-1.5 size-3.5" aria-hidden="true" />
        Delete
      </Button>
    </div>
  </article>
</template>
