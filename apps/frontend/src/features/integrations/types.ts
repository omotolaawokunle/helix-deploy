export type CloudflareConnectionStatus = 'connected' | 'disconnected' | 'error'

export interface CloudflareConnection {
  connected: boolean
  status: CloudflareConnectionStatus
  connectedAt: string | null
  connectedBy: string | null
}

export interface CloudflareZone {
  id: string
  name: string
  status: string
}

export interface ProjectDnsZone {
  id: string
  projectId: string
  zoneId: string
  baseDomain: string
  assignedBy: string | null
  assignedAt: string | null
}

export type DnsStatus = 'none' | 'pending' | 'active' | 'failed'
export type SslStatus = 'none' | 'pending' | 'active' | 'failed'
export type SslChallenge = 'http-01' | 'dns-01'
