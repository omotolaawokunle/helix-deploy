import { computed, ref, watch, type ComputedRef, type Ref } from 'vue'

export type LogStepStatus = 'pending' | 'running' | 'completed' | 'failed'

export interface LogStep {
  id: string
  name: string
  status: LogStepStatus
  lines: string[]
}

const STARTING_PATTERN = /^\[(.+?)\] Starting\.\.\.$/
const COMPLETE_PATTERN = /^✓ (.+?) complete/
const ERROR_PATTERN = /^\[(.+?)\] ERROR:/

function slugify(name: string): string {
  return name.toLowerCase().replace(/\s+/g, '-')
}

export function useLogSteps(lines: Ref<string[]>): {
  steps: Ref<LogStep[]>
  unassignedLines: Ref<string[]>
  isRunning: ComputedRef<boolean>
} {
  const steps = ref<LogStep[]>([])
  const unassignedLines = ref<string[]>([])
  const hasActiveStep = ref(false)
  let currentStep: LogStep | null = null
  let lastProcessedIndex = 0

  function resetState(): void {
    steps.value = []
    unassignedLines.value = []
    hasActiveStep.value = false
    currentStep = null
    lastProcessedIndex = 0
  }

  function findStepByName(name: string): LogStep | undefined {
    return steps.value.find(step => step.name === name)
  }

  function processLine(line: string): void {
    const startingMatch = line.match(STARTING_PATTERN)

    if (startingMatch !== null) {
      const name = startingMatch[1] ?? 'step'
      currentStep = {
        id: slugify(name),
        name,
        status: 'running',
        lines: [],
      }
      steps.value.push(currentStep)
      hasActiveStep.value = true

      return
    }

    const completeMatch = line.match(COMPLETE_PATTERN)

    if (completeMatch !== null) {
      const name = completeMatch[1] ?? 'step'
      const existing = findStepByName(name)

      if (existing !== undefined) {
        existing.status = 'completed'
        existing.lines.push(line)
        currentStep = null
        hasActiveStep.value = false
      } else {
        steps.value.push({
          id: slugify(name),
          name,
          status: 'completed',
          lines: [line],
        })
        hasActiveStep.value = false
      }

      return
    }

    const errorMatch = line.match(ERROR_PATTERN)

    if (errorMatch !== null) {
      const name = errorMatch[1] ?? 'step'
      const existing = findStepByName(name)

      if (existing !== undefined) {
        existing.status = 'failed'
        existing.lines.push(line)
      } else {
        steps.value.push({
          id: slugify(name),
          name,
          status: 'failed',
          lines: [line],
        })
      }

      hasActiveStep.value = false
      currentStep = null

      return
    }

    if (currentStep !== null) {
      currentStep.lines.push(line)
    } else {
      unassignedLines.value.push(line)
    }
  }

  watch(
    lines,
    (allLines) => {
      if (allLines.length < lastProcessedIndex) {
        resetState()
      }

      for (let index = lastProcessedIndex; index < allLines.length; index += 1) {
        processLine(allLines[index] ?? '')
      }

      lastProcessedIndex = allLines.length
    },
    { immediate: true },
  )

  const isRunning = computed(
    () => hasActiveStep.value || steps.value.some(step => step.status === 'running'),
  )

  return {
    steps,
    unassignedLines,
    isRunning,
  }
}
