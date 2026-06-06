import { describe, expect, it } from 'vitest'
import { patchSiteDnsSslFromBroadcast } from '@/features/sites/composables/useSiteProvisioningChannel'
import type { SiteDnsSslStatusChangedPayload } from '@/features/sites/types'

describe('patchSiteDnsSslFromBroadcast', () => {
  it('updates matching site dns and ssl fields', () => {
    const site = {
      id: 'site-1',
      dnsStatus: 'pending',
      dnsError: 'old',
      sslStatus: 'failed',
      sslError: 'old ssl',
    }

    const payload: SiteDnsSslStatusChangedPayload = {
      siteId: 'site-1',
      serverId: 'server-1',
      organizationId: 'org-1',
      domain: 'app.example.test',
      dnsStatus: 'active',
      dnsError: null,
      sslStatus: 'active',
      sslError: null,
    }

    patchSiteDnsSslFromBroadcast(site, payload)

    expect(site.dnsStatus).toBe('active')
    expect(site.dnsError).toBeNull()
    expect(site.sslStatus).toBe('active')
    expect(site.sslError).toBeNull()
  })

  it('ignores payloads for other sites', () => {
    const site = {
      id: 'site-1',
      dnsStatus: 'pending',
      dnsError: null,
      sslStatus: 'none',
      sslError: null,
    }

    patchSiteDnsSslFromBroadcast(site, {
      siteId: 'site-2',
      serverId: 'server-1',
      organizationId: 'org-1',
      domain: 'other.example.test',
      dnsStatus: 'active',
      dnsError: null,
      sslStatus: 'active',
      sslError: null,
    })

    expect(site.dnsStatus).toBe('pending')
    expect(site.sslStatus).toBe('none')
  })
})
