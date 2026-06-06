import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  assignProjectDnsZone,
  buildHostnameFromPrefix,
  fetchDnsProviderConnections,
} from '@/features/integrations/api'
import { api } from '@/lib/axios'

vi.mock('@/lib/axios', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

describe('integrations api', () => {
  afterEach(() => {
    vi.clearAllMocks()
  })

  it('builds apex hostname from @ prefix', () => {
    expect(buildHostnameFromPrefix('@', 'example.test')).toBe('example.test')
  })

  it('builds subdomain hostname from prefix', () => {
    expect(buildHostnameFromPrefix('staging', 'example.test')).toBe('staging.example.test')
  })

  it('assigns project dns zone with provider', async () => {
    vi.mocked(api.post).mockResolvedValue({
      data: {
        data: {
          id: 'zone-1',
          projectId: 'project-1',
          dnsProvider: 'digitalocean',
          zoneId: 'example.test',
          baseDomain: 'example.test',
          assignedBy: 'user-1',
          assignedAt: null,
        },
      },
    })

    const zone = await assignProjectDnsZone('project-1', {
      dnsProvider: 'digitalocean',
      zoneId: 'example.test',
      baseDomain: 'example.test',
    })

    expect(zone.dnsProvider).toBe('digitalocean')
    expect(api.post).toHaveBeenCalledWith('/api/v1/projects/project-1/dns-zones', {
      dnsProvider: 'digitalocean',
      zoneId: 'example.test',
      baseDomain: 'example.test',
    })
  })

  it('loads both dns provider connection statuses', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ data: { data: { connected: true, status: 'connected', connectedAt: null, connectedBy: null } } })
      .mockResolvedValueOnce({ data: { data: { connected: false, status: 'disconnected', connectedAt: null, connectedBy: null } } })

    const connections = await fetchDnsProviderConnections('org-1')

    expect(connections.cloudflare.connected).toBe(true)
    expect(connections.digitalocean.connected).toBe(false)
  })
})
