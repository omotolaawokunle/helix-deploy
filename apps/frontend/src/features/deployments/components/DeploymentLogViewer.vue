<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useBatchedUpdates } from '@/composables/useBatchedUpdates'
import {
  CheckCircle2Icon,
  ChevronDownIcon,
  CircleIcon,
  LoaderCircleIcon,
  MinusIcon,
  XCircleIcon,
} from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatDurationSeconds } from '@/lib/format'
import { fetchDeployment } from '@/features/deployments/api'
import DeploymentLogLines from '@/features/deployments/components/DeploymentLogLines.vue'
import { useDeploymentStream } from '@/features/deployments/composables/useDeploymentStream'
import type {
  DeploymentCompletedPayload,
  DeploymentLogStep,
  DeploymentStepPhase,
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
const MAX_LOG_LINES_PER_STEP = 2000

const steps = ref<DeploymentLogStep[]>([])
const stepIndexById = new Map<string, number>()
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const scrollContainerRef = ref<HTMLElement | null>(null)
const userExpandedSteps = ref<Set<string>>(new Set())
const userCollapsedSteps = ref<Set<string>>(new Set())
const autoScrollEnabled = ref(props.autoScroll)
const showJumpToLatest = ref(false)
const activePhaseTab = ref<DeploymentStepPhase>('build')
let scrollRafId: number | null = null

function rebuildStepIndex(): void {
  stepIndexById.clear()

  steps.value.forEach((step, index) => {
    stepIndexById.set(step.id, index)
  })
}

const phaseOrder: Record<DeploymentStepPhase, number> = {
  build: 0,
  deploy: 1,
}

const hasBuildPhase = computed(
  () => steps.value.some(step => step.phase === 'build'),
)

const visibleSteps = computed((): DeploymentLogStep[] => {
  if (!hasBuildPhase.value) {
    return steps.value
  }

  return steps.value.filter(step => step.phase === activePhaseTab.value)
})

const buildStepCount = computed(
  () => steps.value.filter(step => step.phase === 'build').length,
)

const deployStepCount = computed(
  () => steps.value.filter(step => step.phase === 'deploy').length,
)

function isLogTruncated(step: DeploymentLogStep): boolean {
  return step.lines.length >= MAX_LOG_LINES_PER_STEP
}

function sortStepsInPlace(): void {
  steps.value.sort((left, right) => {
    const phaseDiff = phaseOrder[left.phase] - phaseOrder[right.phase]

    if (phaseDiff !== 0) {
      return phaseDiff
    }

    return left.order - right.order
  })
  rebuildStepIndex()
}

function mapApiStep(step: DeploymentStepResource): DeploymentLogStep {
  return {
    id: step.id,
    name: step.name,
    order: step.order,
    phase: step.phase ?? 'deploy',
    status: step.status,
    duration: null,
    lines: [],
  }
}

function upsertStep(
  stepId: string,
  patch: Partial<DeploymentLogStep> & { name?: string; order?: number; phase?: DeploymentStepPhase },
): void {
  const index = stepIndexById.get(stepId)

  if (index === undefined) {
    steps.value.push({
      id: stepId,
      name: patch.name ?? 'Step',
      order: patch.order ?? steps.value.length + 1,
      phase: patch.phase ?? 'deploy',
      status: patch.status ?? 'pending',
      duration: patch.duration ?? null,
      lines: patch.lines ?? [],
    })
    sortStepsInPlace()

    return
  }

  const current = steps.value[index]
  steps.value[index] = { ...current, ...patch }
}

interface PendingLogLine {
  stepId: string
  line: string
  timestamp: string
}

function trimLogLines(
  lines: Array<{ timestamp: string; content: string }>,
): Array<{ timestamp: string; content: string }> {
  if (lines.length <= MAX_LOG_LINES_PER_STEP) {
    return lines
  }

  return lines.slice(lines.length - MAX_LOG_LINES_PER_STEP)
}

function appendLogLines(batch: readonly PendingLogLine[]): void {
  const linesByStep = new Map<string, Array<{ timestamp: string; content: string }>>()

  for (const item of batch) {
    const existing = linesByStep.get(item.stepId) ?? []
    existing.push({ timestamp: item.timestamp, content: item.line })
    linesByStep.set(item.stepId, existing)
  }

  for (const [stepId, newLines] of linesByStep) {
    const index = stepIndexById.get(stepId)

    if (index === undefined) {
      upsertStep(stepId, {
        status: 'running',
        lines: newLines,
      })

      continue
    }

    const current = steps.value[index]
    steps.value[index] = {
      ...current,
      lines: trimLogLines([...current.lines, ...newLines]),
    }
  }
}

const { push: queueLogLine } = useBatchedUpdates<PendingLogLine>((batch) => {
  appendLogLines(batch)

  if (props.autoScroll && autoScrollEnabled.value) {
    void scrollToBottom()
  }
})

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
  const updateScrollState = (): void => {
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

  if (import.meta.env.MODE === 'test') {
    updateScrollState()

    return
  }

  if (scrollRafId !== null) {
    return
  }

  scrollRafId = requestAnimationFrame(() => {
    scrollRafId = null
    updateScrollState()
  })
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
  const index = stepIndexById.get(stepId)

  if (index === undefined) {
    return false
  }

  const step = steps.value[index]

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

function statusIcon(status: DeploymentStepStatus) {
  const icons = {
    pending: CircleIcon,
    running: LoaderCircleIcon,
    success: CheckCircle2Icon,
    failed: XCircleIcon,
    skipped: MinusIcon,
  }

  return icons[status]
}

function statusIconClass(status: DeploymentStepStatus): string {
  const classes: Record<DeploymentStepStatus, string> = {
    pending: 'text-zinc-500',
    running: 'animate-spin text-blue-400 motion-reduce:animate-none',
    success: 'text-green-400',
    failed: 'text-red-400',
    skipped: 'text-zinc-600',
  }

  return classes[status]
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

async function loadInitialSteps(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    const deployment = await fetchDeployment(props.deploymentId)
    steps.value = deployment.steps.map(mapApiStep)
    sortStepsInPlace()
  } catch {
    loadError.value = 'Unable to load deployment logs.'
  } finally {
    isLoading.value = false
  }
}

useDeploymentStream(props.deploymentId, {
  onLogLine: (stepId, line, timestamp) => {
    queueLogLine({ stepId, line, timestamp })
  },
  onStepStarted: (stepId, name, order, status, phase) => {
    upsertStep(stepId, {
      name,
      order,
      status: status as DeploymentStepStatus,
      phase: phase as DeploymentStepPhase,
    })

    if (
      phase === 'deploy'
      && hasBuildPhase.value
      && activePhaseTab.value === 'build'
    ) {
      activePhaseTab.value = 'deploy'
    }
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

    <div
      v-else-if="loadError !== null"
      class="flex flex-col items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4"
      data-testid="deployment-log-error"
    >
      <p class="text-sm text-destructive">
        {{ loadError }}
      </p>
      <Button
        type="button"
        variant="outline"
        size="sm"
        @click="loadInitialSteps"
      >
        Try again
      </Button>
    </div>

    <div
      v-else
      ref="scrollContainerRef"
      class="min-h-96 overflow-y-auto rounded-lg border border-zinc-800 bg-zinc-950 font-mono text-sm text-zinc-100"
      data-testid="deployment-log-scroll"
      @scroll="handleScroll"
    >
      <Tabs
        v-if="hasBuildPhase"
        v-model="activePhaseTab"
        class="border-b border-zinc-800 px-4 pt-3"
      >
        <TabsList class="grid w-full max-w-xs grid-cols-2 bg-zinc-900">
          <TabsTrigger value="build" data-testid="deployment-phase-tab-build">
            Build
            <span class="ml-1.5 tabular-nums text-zinc-500">{{ buildStepCount }}</span>
          </TabsTrigger>
          <TabsTrigger value="deploy" data-testid="deployment-phase-tab-deploy">
            Deploy
            <span class="ml-1.5 tabular-nums text-zinc-500">{{ deployStepCount }}</span>
          </TabsTrigger>
        </TabsList>
      </Tabs>

      <div
        v-for="step in visibleSteps"
        :key="step.id"
        class="border-b border-zinc-800 last:border-b-0"
        :data-testid="`deployment-step-${step.id}`"
      >
        <button
          type="button"
          class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-zinc-900/70"
          :class="stepHeaderClass(step.status)"
          :aria-expanded="isStepExpanded(step.id)"
          @click="toggleStep(step.id)"
        >
          <ChevronDownIcon
            class="size-4 shrink-0 text-zinc-500 transition-transform duration-200 motion-reduce:transition-none"
            :class="isStepExpanded(step.id) ? 'rotate-180' : ''"
            aria-hidden="true"
          />
          <span class="relative flex size-4 shrink-0 items-center justify-center">
            <component
              :is="statusIcon(step.status)"
              class="size-4"
              :class="statusIconClass(step.status)"
              aria-hidden="true"
            />
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
          <span
            v-if="!hasBuildPhase"
            class="text-xs text-zinc-500"
          >
            {{ step.order }}
          </span>
        </button>

        <div
          v-if="isStepExpanded(step.id)"
          class="border-t border-zinc-800"
          :data-testid="`deployment-step-body-${step.id}`"
        >
          <DeploymentLogLines
            :lines="step.lines"
            :step-id="step.id"
            :follow-tail="step.status === 'running'"
          />
          <p
            v-if="isLogTruncated(step)"
            class="border-t border-zinc-800 px-4 py-2 text-xs text-zinc-600"
            data-testid="deployment-log-truncated"
          >
            Showing latest {{ MAX_LOG_LINES_PER_STEP.toLocaleString() }} lines
          </p>
        </div>
      </div>

      <p
        v-if="visibleSteps.length === 0"
        class="px-4 py-8 text-center text-sm text-zinc-500"
      >
        Waiting for deployment output…
      </p>
    </div>

    <Button
      v-if="showJumpToLatest"
      type="button"
      variant="outline"
      size="sm"
      class="absolute bottom-4 right-4 gap-1.5 border-zinc-700 bg-zinc-900 text-zinc-100 shadow-lg hover:bg-zinc-800"
      data-testid="jump-to-latest"
      @click="jumpToLatest"
    >
      <ChevronDownIcon class="size-3.5" aria-hidden="true" />
      Jump to latest
    </Button>
  </div>
</template>
