<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { ChevronDownIcon } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { formatDurationSeconds } from '@/lib/format'
import { fetchDeployment } from '@/features/deployments/api'
import { useDeploymentStream } from '@/features/deployments/composables/useDeploymentStream'
import type {
  DeploymentCompletedPayload,
  DeploymentLogStep,
  DeploymentStepResource,
  DeploymentStepStatus,
} from '@/features/deployments/types'

interface Props {
  deploymentId: string
  autoScroll?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  autoScroll: true,
})

const emit = defineEmits<{
  completed: [payload: DeploymentCompletedPayload]
  'approval-required': [payload: Record<string, unknown>]
}>()

const SCROLL_THRESHOLD_PX = 50

const steps = ref<DeploymentLogStep[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const scrollContainerRef = ref<HTMLElement | null>(null)
const userExpandedSteps = ref<Set<string>>(new Set())
const userCollapsedSteps = ref<Set<string>>(new Set())
const autoScrollEnabled = ref(props.autoScroll)
const showJumpToLatest = ref(false)

const sortedSteps = computed(() =>
  [...steps.value].sort((left, right) => left.order - right.order),
)

function mapApiStep(step: DeploymentStepResource): DeploymentLogStep {
  return {
    id: step.id,
    name: step.name,
    order: step.order,
    status: step.status,
    duration: null,
    lines: [],
  }
}

function upsertStep(
  stepId: string,
  patch: Partial<DeploymentLogStep> & { name?: string; order?: number },
): void {
  const index = steps.value.findIndex(step => step.id === stepId)

  if (index === -1) {
    steps.value = [
      ...steps.value,
      {
        id: stepId,
        name: patch.name ?? 'Step',
        order: patch.order ?? steps.value.length + 1,
        status: patch.status ?? 'pending',
        duration: patch.duration ?? null,
        lines: patch.lines ?? [],
      },
    ]

    return
  }

  const current = steps.value[index]
  const updated = { ...current, ...patch }
  const next = [...steps.value]
  next[index] = updated
  steps.value = next
}

function appendLogLine(stepId: string, line: string, timestamp: string): void {
  const index = steps.value.findIndex(step => step.id === stepId)

  if (index === -1) {
    upsertStep(stepId, {
      status: 'running',
      lines: [{ timestamp, content: line }],
    })

    return
  }

  const current = steps.value[index]
  upsertStep(stepId, {
    lines: [...current.lines, { timestamp, content: line }],
  })
}

function isNearBottom(element: HTMLElement): boolean {
  const distanceFromBottom = element.scrollHeight - element.scrollTop - element.clientHeight

  return distanceFromBottom <= SCROLL_THRESHOLD_PX
}

async function scrollToBottom(): Promise<void> {
  await nextTick()

  const element = scrollContainerRef.value

  if (element !== null) {
    element.scrollTop = element.scrollHeight
  }
}

function handleScroll(): void {
  const element = scrollContainerRef.value

  if (element === null || !props.autoScroll) {
    return
  }

  if (isNearBottom(element)) {
    autoScrollEnabled.value = true
    showJumpToLatest.value = false

    return
  }

  autoScrollEnabled.value = false
  showJumpToLatest.value = true
}

async function jumpToLatest(): Promise<void> {
  autoScrollEnabled.value = true
  showJumpToLatest.value = false
  await scrollToBottom()
}

function toggleStep(stepId: string): void {
  if (isStepExpanded(stepId)) {
    userCollapsedSteps.value = new Set([...userCollapsedSteps.value, stepId])
    userExpandedSteps.value.delete(stepId)
  } else {
    userExpandedSteps.value = new Set([...userExpandedSteps.value, stepId])
    userCollapsedSteps.value.delete(stepId)
  }
}

function isStepExpanded(stepId: string): boolean {
  const step = steps.value.find(item => item.id === stepId)

  if (step === undefined) {
    return false
  }

  if (userCollapsedSteps.value.has(stepId)) {
    return false
  }

  if (step.status === 'running' || step.status === 'failed') {
    return true
  }

  if (step.status === 'pending' || step.status === 'skipped') {
    return false
  }

  return userExpandedSteps.value.has(stepId)
}

function stepHeaderClass(status: DeploymentStepStatus): string {
  if (status === 'running') {
    return 'bg-zinc-900 border-l-2 border-blue-500'
  }

  if (status === 'failed') {
    return 'bg-zinc-900 border-l-2 border-red-500'
  }

  return ''
}

function statusIconClass(status: DeploymentStepStatus): string {
  const classes: Record<DeploymentStepStatus, string> = {
    pending: 'text-zinc-500',
    running: 'animate-spin text-blue-400',
    success: 'text-green-400',
    failed: 'text-red-400',
    skipped: 'text-zinc-600',
  }

  return classes[status]
}

function statusIconLabel(status: DeploymentStepStatus): string {
  const labels: Record<DeploymentStepStatus, string> = {
    pending: '○',
    running: '◌',
    success: '✓',
    failed: '✗',
    skipped: '—',
  }

  return labels[status]
}

function statusScreenReaderLabel(status: DeploymentStepStatus): string {
  const labels: Record<DeploymentStepStatus, string> = {
    pending: 'Pending',
    running: 'Running',
    success: 'Success',
    failed: 'Failed',
    skipped: 'Skipped',
  }

  return labels[status]
}

function lineColorClass(content: string): string {
  if (content.startsWith('  +') || content.startsWith('[success]')) {
    return 'text-green-400'
  }

  if (content.startsWith('  -') || content.startsWith('[error]') || content.startsWith('Error')) {
    return 'text-red-400'
  }

  if (content.toLowerCase().startsWith('warning')) {
    return 'text-yellow-400'
  }

  return 'text-zinc-300'
}

function formatTimestamp(iso: string): string {
  try {
    return new Date(iso).toLocaleTimeString()
  } catch {
    return iso
  }
}

async function loadInitialSteps(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    const deployment = await fetchDeployment(props.deploymentId)
    steps.value = deployment.steps.map(mapApiStep)
  } catch {
    loadError.value = 'Unable to load deployment logs.'
  } finally {
    isLoading.value = false
  }
}

useDeploymentStream(props.deploymentId, {
  onLogLine: (stepId, line, timestamp) => {
    appendLogLine(stepId, line, timestamp)
  },
  onStepStarted: (stepId, name, order, status) => {
    upsertStep(stepId, {
      name,
      order,
      status: status as DeploymentStepStatus,
    })
  },
  onStepUpdate: (stepId, status, duration) => {
    upsertStep(stepId, {
      status: status as DeploymentStepStatus,
      duration,
    })
  },
  onComplete: (payload) => {
    emit('completed', payload)
  },
  onApprovalRequired: (payload) => {
    emit('approval-required', payload)
  },
})

watch(
  () => steps.value.map(step => step.lines.length).join(','),
  async () => {
    if (!props.autoScroll || !autoScrollEnabled.value) {
      return
    }

    await scrollToBottom()
  },
)

onMounted(async () => {
  await loadInitialSteps()
  await scrollToBottom()
})
</script>

<template>
  <div class="relative" data-testid="deployment-log-viewer">
    <div v-if="isLoading" class="space-y-2">
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="min-h-96 w-full rounded-lg" />
    </div>

    <p v-else-if="loadError !== null" class="text-sm text-destructive">
      {{ loadError }}
    </p>

    <div
      v-else
      ref="scrollContainerRef"
      class="min-h-96 overflow-y-auto rounded-lg border border-zinc-800 bg-zinc-950 font-mono text-sm text-zinc-100"
      data-testid="deployment-log-scroll"
      @scroll="handleScroll"
    >
      <div
        v-for="step in sortedSteps"
        :key="step.id"
        class="border-b border-zinc-800 last:border-b-0"
        :data-testid="`deployment-step-${step.id}`"
      >
        <button
          type="button"
          class="flex w-full items-center gap-3 px-4 py-3 text-left"
          :class="stepHeaderClass(step.status)"
          @click="toggleStep(step.id)"
        >
          <ChevronDownIcon
            class="size-4 shrink-0 text-zinc-500 transition-transform"
            :class="isStepExpanded(step.id) ? 'rotate-180' : ''"
          />
          <span class="relative w-4 shrink-0 text-center text-sm">
            <span
              :class="[statusIconClass(step.status), 'motion-reduce:animate-none']"
              aria-hidden="true"
            >
              {{ statusIconLabel(step.status) }}
            </span>
            <span class="sr-only">{{ statusScreenReaderLabel(step.status) }}</span>
          </span>
          <span class="flex-1 text-sm font-medium">{{ step.name }}</span>
          <Badge
            v-if="step.duration !== null"
            variant="outline"
            class="border-zinc-700 bg-transparent text-zinc-400"
          >
            {{ formatDurationSeconds(step.duration) }}
          </Badge>
          <span class="text-xs text-zinc-500">
            {{ step.order }}
          </span>
        </button>

        <div
          v-if="isStepExpanded(step.id)"
          class="border-t border-zinc-800"
          :data-testid="`deployment-step-body-${step.id}`"
        >
          <pre
            v-for="(logLine, index) in step.lines"
            :key="`${step.id}-${index}`"
            class="whitespace-pre-wrap break-words px-2 py-px text-xs leading-relaxed"
          >
            <span class="mr-3 select-none text-zinc-600">{{ formatTimestamp(logLine.timestamp) }}</span>
            <span :class="lineColorClass(logLine.content)">{{ logLine.content }}</span>
          </pre>
          <p
            v-if="step.lines.length === 0"
            class="px-4 py-3 text-xs text-zinc-500"
          >
            Waiting for output…
          </p>
        </div>
      </div>

      <p
        v-if="sortedSteps.length === 0"
        class="px-4 py-8 text-center text-sm text-zinc-500"
      >
        Waiting for deployment output…
      </p>
    </div>

    <button
      v-if="showJumpToLatest"
      type="button"
      class="absolute bottom-4 right-4 rounded-md border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-100 shadow-lg hover:bg-zinc-800"
      data-testid="jump-to-latest"
      @click="jumpToLatest"
    >
      ↓ Jump to latest
    </button>
  </div>
</template>
