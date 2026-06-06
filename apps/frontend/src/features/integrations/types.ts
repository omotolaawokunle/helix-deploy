export type DnsProvider = 'cloudflare' | 'digitalocean'

export type CloudflareConnectionStatus = 'connected' | 'disconnected' | 'error'

export interface DnsProviderConnection {
  connected: boolean
  status: CloudflareConnectionStatus
  connectedAt: string | null
  connectedBy: string | null
}

export type CloudflareConnection = DnsProviderConnection
export type DigitalOceanConnection = DnsProviderConnection

export interface DnsProviderZone {
  id: string
  name: string
  status: string
}

export type CloudflareZone = DnsProviderZone

export interface ProjectDnsZone {
  id: string
  projectId: string
  dnsProvider: DnsProvider
  zoneId: string
  baseDomain: string
  assignedBy: string | null
  assignedAt: string | null
}

export type DnsStatus = 'none' | 'pending' | 'active' | 'failed'
export type SslStatus = 'none' | 'pending' | 'active' | 'failed'
export type SslChallenge = 'http-01' | 'dns-01'

export const DNS_PROVIDER_LABELS: Record<DnsProvider, string> = {
  cloudflare: 'Cloudflare',
  digitalocean: 'DigitalOcean',
}

export function dnsProviderLabel(provider: DnsProvider | string | null | undefined): string {
  if (provider === null || provider === undefined || provider === '') {
    return '—'
  }

  if (provider in DNS_PROVIDER_LABELS) {
    return DNS_PROVIDER_LABELS[provider as DnsProvider]
  }

  return provider
}
