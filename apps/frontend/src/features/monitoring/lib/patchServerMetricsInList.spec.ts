import { describe, expect, it } from 'vitest'
import { patchServerMetricsInList } from '@/features/monitoring/lib/patchServerMetricsInList'
import { ServerStatus, ServerProvider, ManagementMode, type Server } from '@/types'

const baseServer: Server = {
  id: 'server-1',
  hostname: 'prod-1',
  ipAddress: '10.0.0.1',
  sshPort: 22,
  sshUser: 'deploy',
  provider: ServerProvider.Generic,
  providerInstanceId: null,
  region: null,
  serverType: null,
  os: null,
  phpVersion: null,
  nodeVersion: null,
  status: ServerStatus.Active,
  managementMode: ManagementMode.Managed,
  environment: null,
  project: null,
  tags: [],
  installedServices: [],
  healthStatus: {
    cpuPercent: 10,
    memoryUsedPercent: 50,
    diskUsedPercent: 30,
    lastCheckedAt: '2026-06-05T10:00:00Z',
  },
  createdAt: '2026-01-01',
  updatedAt: '2026-01-01',
}

describe('patchServerMetricsInList', () => {
  it('updates health metrics for a matching server', () => {
    const result = patchServerMetricsInList([baseServer], {
      serverId: 'server-1',
      cpuPercent: 25,
      memoryUsedPercent: 60,
      diskUsedPercent: 45,
      lastCheckedAt: '2026-06-05T12:00:00Z',
    })

    expect(result).not.toBe('missing')

    if (result === 'missing') {
      return
    }

    expect(result[0].healthStatus).toEqual({
      cpuPercent: 25,
      memoryUsedPercent: 60,
      diskUsedPercent: 45,
      lastCheckedAt: '2026-06-05T12:00:00Z',
    })
  })

  it('returns missing when the server is not in the list', () => {
    expect(
      patchServerMetricsInList([baseServer], { serverId: 'missing' }),
    ).toBe('missing')
  })
})
