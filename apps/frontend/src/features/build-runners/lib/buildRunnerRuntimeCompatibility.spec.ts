import { describe, expect, it } from 'vitest'
import {
  evaluateBuildRunnerRuntimeCompatibility,
  isRunnerInSitePool,
  runnerSupportsSiteRuntime,
} from '@/features/build-runners/lib/buildRunnerRuntimeCompatibility'
import type { BuildRunner } from '@/features/build-runners/types'

function createRunner(overrides: Partial<BuildRunner> = {}): BuildRunner {
  return {
    id: 'runner-1',
    name: 'Runner A',
    ipAddress: '10.0.0.1',
    sshPort: 22,
    sshUser: 'deploy',
    status: 'online',
    maxConcurrentBuilds: 2,
    activeBuilds: 0,
    availableSlots: 2,
    cpuCores: null,
    ramGb: null,
    supportedRuntimes: ['php'],
    project: null,
    createdAt: '2026-01-01T00:00:00Z',
    updatedAt: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('runnerSupportsSiteRuntime', () => {
  it('returns true when runtime is listed on the runner', () => {
    expect(runnerSupportsSiteRuntime(createRunner(), 'php')).toBe(true)
  })

  it('returns false when runtime is missing', () => {
    expect(runnerSupportsSiteRuntime(createRunner(), 'nodejs')).toBe(false)
  })
})

describe('isRunnerInSitePool', () => {
  it('includes org-wide runners for any site project', () => {
    expect(isRunnerInSitePool(createRunner(), 'project-1')).toBe(true)
  })

  it('includes project-scoped runners only for matching projects', () => {
    const runner = createRunner({
      project: { id: 'project-1', name: 'App' },
    })

    expect(isRunnerInSitePool(runner, 'project-1')).toBe(true)
    expect(isRunnerInSitePool(runner, 'project-2')).toBe(false)
  })
})

describe('evaluateBuildRunnerRuntimeCompatibility', () => {
  it('returns null when build strategy is not runner', () => {
    expect(
      evaluateBuildRunnerRuntimeCompatibility({
        siteRuntime: 'php',
        siteProjectId: null,
        buildStrategy: 'on_server',
        buildRunnerId: null,
        buildRunners: [],
      }),
    ).toBeNull()
  })

  it('warns when a selected runner does not support the site runtime', () => {
    const warning = evaluateBuildRunnerRuntimeCompatibility({
      siteRuntime: 'nodejs',
      siteProjectId: null,
      buildStrategy: 'runner',
      buildRunnerId: 'runner-1',
      buildRunners: [createRunner({ supportedRuntimes: ['php'] })],
    })

    expect(warning?.message).toContain('does not support Node.js builds')
  })

  it('does not warn when auto-select has a compatible online runner', () => {
    expect(
      evaluateBuildRunnerRuntimeCompatibility({
        siteRuntime: 'php',
        siteProjectId: null,
        buildStrategy: 'runner',
        buildRunnerId: null,
        buildRunners: [createRunner({ supportedRuntimes: ['php'] })],
      }),
    ).toBeNull()
  })

  it('warns when auto-select has no compatible online runners', () => {
    const warning = evaluateBuildRunnerRuntimeCompatibility({
      siteRuntime: 'nodejs',
      siteProjectId: null,
      buildStrategy: 'runner',
      buildRunnerId: null,
      buildRunners: [
        createRunner({ supportedRuntimes: ['php'] }),
        createRunner({
          id: 'runner-2',
          name: 'Offline Node',
          supportedRuntimes: ['nodejs'],
          status: 'offline',
        }),
      ],
    })

    expect(warning?.message).toContain('No online build runner')
  })
})
