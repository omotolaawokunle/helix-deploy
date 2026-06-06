import { describe, expect, it } from 'vitest'
import { patchBuildRunnerInList } from '@/features/build-runners/lib/patchBuildRunnerInList'
import type { BuildRunner } from '@/features/build-runners/types'

function buildRunner(overrides: Partial<BuildRunner> = {}): BuildRunner {
  return {
    id: 'runner-1',
    name: 'CI Runner',
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
    createdAt: '2026-06-01T00:00:00Z',
    updatedAt: '2026-06-01T00:00:00Z',
    ...overrides,
  }
}

describe('patchBuildRunnerInList', () => {
  it('updates slot counts for a matching runner', () => {
    const result = patchBuildRunnerInList([buildRunner()], {
      runnerId: 'runner-1',
      activeBuilds: 1,
      availableSlots: 1,
    })

    expect(result).not.toBe('missing')

    if (result === 'missing') {
      return
    }

    expect(result[0]?.activeBuilds).toBe(1)
    expect(result[0]?.availableSlots).toBe(1)
  })

  it('updates status without clearing slot counts', () => {
    const result = patchBuildRunnerInList([buildRunner()], {
      runnerId: 'runner-1',
      status: 'offline',
      activeBuilds: 0,
      availableSlots: 2,
    })

    expect(result).not.toBe('missing')

    if (result === 'missing') {
      return
    }

    expect(result[0]?.status).toBe('offline')
    expect(result[0]?.maxConcurrentBuilds).toBe(2)
  })

  it('returns missing when runner is not in the list', () => {
    expect(
      patchBuildRunnerInList([buildRunner()], { runnerId: 'runner-2', activeBuilds: 1 }),
    ).toBe('missing')
  })
})
