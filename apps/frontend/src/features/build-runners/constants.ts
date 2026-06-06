import type { BuildRunnerRuntime } from '@/features/build-runners/types'

export const BUILD_RUNNER_RUNTIME_OPTIONS: Array<{ value: BuildRunnerRuntime; label: string }> = [
  { value: 'php', label: 'PHP' },
  { value: 'nodejs', label: 'Node.js' },
  { value: 'python', label: 'Python' },
  { value: 'go', label: 'Go' },
  { value: 'static', label: 'Static' },
  { value: 'docker', label: 'Docker' },
]

export function buildRunnerRuntimeLabel(runtime: BuildRunnerRuntime): string {
  return BUILD_RUNNER_RUNTIME_OPTIONS.find(option => option.value === runtime)?.label ?? runtime
}
