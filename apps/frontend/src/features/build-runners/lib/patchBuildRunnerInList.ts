import type { BuildRunner, BuildRunnerStatus } from '@/features/build-runners/types'

export interface BuildRunnerLivePatch {
  runnerId: string
  status?: string
  activeBuilds?: number
  maxConcurrentBuilds?: number
  availableSlots?: number
}

export function patchBuildRunnerInList(
  runners: readonly BuildRunner[],
  patch: BuildRunnerLivePatch,
): BuildRunner[] | 'missing' {
  const index = runners.findIndex(runner => runner.id === patch.runnerId)

  if (index === -1) {
    return 'missing'
  }

  const current = runners[index]

  return runners.map((runner, runnerIndex) => {
    if (runnerIndex !== index) {
      return runner
    }

    return {
      ...current,
      status: (patch.status ?? current.status) as BuildRunnerStatus,
      activeBuilds: patch.activeBuilds ?? current.activeBuilds,
      maxConcurrentBuilds: patch.maxConcurrentBuilds ?? current.maxConcurrentBuilds,
      availableSlots: patch.availableSlots ?? current.availableSlots,
    }
  })
}
