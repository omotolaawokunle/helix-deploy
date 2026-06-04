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

  watch(
    lines,
    (allLines) => {
      const nextSteps: LogStep[] = []
      const orphanLines: string[] = []
      let currentStep: LogStep | null = null
      let running = false

      for (const line of allLines) {
        const startingMatch = line.match(STARTING_PATTERN)
        if (startingMatch !== null) {
          const name = startingMatch[1] ?? 'step'
          currentStep = {
            id: slugify(name),
            name,
            status: 'running',
            lines: [],
          }
          nextSteps.push(currentStep)
          running = true
          continue
        }

        const completeMatch = line.match(COMPLETE_PATTERN)
        if (completeMatch !== null) {
          const name = completeMatch[1] ?? 'step'
          const existing = nextSteps.find(step => step.name === name)
          if (existing !== undefined) {
            existing.status = 'completed'
            existing.lines.push(line)
            currentStep = null
          } else {
            nextSteps.push({
              id: slugify(name),
              name,
              status: 'completed',
              lines: [line],
            })
          }
          continue
        }

        const errorMatch = line.match(ERROR_PATTERN)
        if (errorMatch !== null) {
          const name = errorMatch[1] ?? 'step'
          const existing = nextSteps.find(step => step.name === name)
          if (existing !== undefined) {
            existing.status = 'failed'
            existing.lines.push(line)
          } else {
            nextSteps.push({
              id: slugify(name),
              name,
              status: 'failed',
              lines: [line],
            })
          }
          running = false
          currentStep = null
          continue
        }

        if (currentStep !== null) {
          currentStep.lines.push(line)
        } else {
          orphanLines.push(line)
        }
      }

      steps.value = nextSteps
      unassignedLines.value = orphanLines
      hasActiveStep.value = running
    },
    { deep: true, immediate: true },
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
