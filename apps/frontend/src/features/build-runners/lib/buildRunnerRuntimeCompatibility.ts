import { buildRunnerRuntimeLabel } from '@/features/build-runners/constants'
import type { BuildRunner, BuildRunnerRuntime } from '@/features/build-runners/types'
import type { SiteBuildStrategy } from '@/types'

export interface BuildRunnerRuntimeWarning {
  message: string
}

export function runnerSupportsSiteRuntime(runner: BuildRunner, siteRuntime: string): boolean {
  return runner.supportedRuntimes.includes(siteRuntime as BuildRunnerRuntime)
}

export function isRunnerInSitePool(runner: BuildRunner, siteProjectId: string | null): boolean {
  if (runner.project === null) {
    return true
  }

  if (siteProjectId === null) {
    return false
  }

  return runner.project.id === siteProjectId
}

export function siteRuntimeLabel(siteRuntime: string): string {
  const knownRuntimes: BuildRunnerRuntime[] = ['php', 'nodejs', 'python', 'go', 'static', 'docker']

  if (knownRuntimes.includes(siteRuntime as BuildRunnerRuntime)) {
    return buildRunnerRuntimeLabel(siteRuntime as BuildRunnerRuntime)
  }

  return siteRuntime
}

export function evaluateBuildRunnerRuntimeCompatibility(params: {
  siteRuntime: string
  siteProjectId: string | null
  buildStrategy: SiteBuildStrategy
  buildRunnerId: string | null
  buildRunners: BuildRunner[]
}): BuildRunnerRuntimeWarning | null {
  if (params.buildStrategy !== 'runner') {
    return null
  }

  const runtimeLabel = siteRuntimeLabel(params.siteRuntime)

  if (params.buildRunnerId !== null) {
    const selected = params.buildRunners.find(runner => runner.id === params.buildRunnerId)

    if (selected === undefined) {
      return {
        message: `The selected build runner is no longer available. This site requires ${runtimeLabel} builds.`,
      }
    }

    if (!runnerSupportsSiteRuntime(selected, params.siteRuntime)) {
      return {
        message: `"${selected.name}" does not support ${runtimeLabel} builds. Deployments using this runner will fail until you pick a compatible runner or update its supported runtimes.`,
      }
    }

    return null
  }

  const compatibleOnline = params.buildRunners.filter(
    runner => runner.status === 'online'
      && isRunnerInSitePool(runner, params.siteProjectId)
      && runnerSupportsSiteRuntime(runner, params.siteRuntime),
  )

  if (compatibleOnline.length === 0) {
    return {
      message: `No online build runner in your pool supports ${runtimeLabel} builds for this site. Deployments may fail until you add or configure a compatible runner.`,
    }
  }

  return null
}
