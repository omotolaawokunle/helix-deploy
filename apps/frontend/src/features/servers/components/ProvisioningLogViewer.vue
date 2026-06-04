<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import {
  CheckCircle2Icon,
  ChevronDownIcon,
  CircleIcon,
  LoaderCircleIcon,
  XCircleIcon,
} from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import {
  useLogSteps,
  type LogStep,
  type LogStepStatus,
} from '@/features/deployments/composables/useLogSteps'

interface Props {
  lines: string[]
  title?: string
  isComplete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Output',
  isComplete: false,
})

const terminalRef = ref<HTMLElement | null>(null)
const expandedSteps = ref<Set<string>>(new Set())

const linesRef = computed(() => props.lines)
const { steps, unassignedLines, isRunning } = useLogSteps(linesRef)

const statusIcon = (status: LogStepStatus) => {
  const icons = {
    pending: CircleIcon,
    running: LoaderCircleIcon,
    completed: CheckCircle2Icon,
    failed: XCircleIcon,
  }

  return icons[status]
}

const statusClass = (status: LogStepStatus): string => {
  const classes: Record<LogStepStatus, string> = {
    pending: 'text-muted-foreground',
    running: 'animate-spin text-blue-400',
    completed: 'text-green-400',
    failed: 'text-red-400',
  }

  return classes[status]
}

function toggleStep(step: LogStep): void {
  const next = new Set(expandedSteps.value)

  if (next.has(step.id)) {
    next.delete(step.id)
  } else {
    next.add(step.id)
  }

  expandedSteps.value = next
}

function isExpanded(step: LogStep): boolean {
  return expandedSteps.value.has(step.id) || step.status === 'running'
}

watch(
  () => props.lines.length,
  async () => {
    await nextTick()

    if (terminalRef.value !== null) {
      terminalRef.value.scrollTop = terminalRef.value.scrollHeight
    }
  },
)

watch(
  steps,
  (nextSteps) => {
    const running = nextSteps.find(step => step.status === 'running')

    if (running !== undefined) {
      expandedSteps.value = new Set([...expandedSteps.value, running.id])
    }
  },
  { deep: true },
)
</script>

<template>
  <div class="space-y-4" data-testid="provisioning-log-viewer">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-medium">
        {{ title }}
      </h2>
      <Badge
        variant="outline"
        :class="cn(
          'border-transparent capitalize',
          isComplete ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' : '',
          isRunning && !isComplete ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200' : '',
        )"
      >
        <LoaderCircleIcon
          v-if="isRunning && !isComplete"
          class="mr-1 size-3 animate-spin"
        />
        {{ isComplete ? 'Complete' : isRunning ? 'Running' : 'Waiting' }}
      </Badge>
    </div>

    <div
      v-if="steps.length > 0"
      class="space-y-2 rounded-lg border bg-card"
    >
      <div
        v-for="step in steps"
        :key="step.id"
        class="border-b last:border-b-0"
      >
        <button
          type="button"
          class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/50"
          @click="toggleStep(step)"
        >
          <component
            :is="statusIcon(step.status)"
            class="size-4 shrink-0"
            :class="statusClass(step.status)"
          />
          <span class="flex-1 text-sm font-medium capitalize">{{ step.name }}</span>
          <ChevronDownIcon
            class="size-4 text-muted-foreground transition-transform"
            :class="isExpanded(step) ? 'rotate-180' : ''"
          />
        </button>

        <div
          v-if="isExpanded(step) && step.lines.length > 0"
          class="border-t bg-zinc-950 px-4 py-3 font-mono text-xs text-zinc-100"
        >
          <div v-for="(line, index) in step.lines" :key="`${step.id}-${index}`">
            {{ line }}
          </div>
        </div>
      </div>
    </div>

    <div
      ref="terminalRef"
      class="max-h-[32rem] overflow-y-auto rounded-lg bg-zinc-950 p-4 font-mono text-xs leading-relaxed text-zinc-100"
      data-testid="log-terminal"
    >
      <template v-if="unassignedLines.length > 0 || (steps.length === 0 && lines.length > 0)">
        <div
          v-for="(line, index) in unassignedLines.length > 0 ? unassignedLines : lines"
          :key="index"
          class="whitespace-pre-wrap break-words"
        >
          {{ line }}
        </div>
      </template>
      <p v-else-if="lines.length === 0" class="text-zinc-500">
        Waiting for output…
      </p>
    </div>
  </div>
</template>
