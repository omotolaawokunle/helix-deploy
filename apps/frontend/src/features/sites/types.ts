export enum SiteStatus {
  Provisioning = 'provisioning',
  Active = 'active',
  Failed = 'failed',
}

export const SITE_BROADCAST_EVENTS = {
  provisioningStarted: 'site.provisioning.started',
  created: 'site.created',
  provisioningFailed: 'site.provisioning.failed',
  dnsSslStatusChanged: 'site.dns_ssl.status_changed',
  envVarsPullPreviewReady: 'env.vars.pull.preview.ready',
  envVarsPulled: 'env.vars.pulled',
} as const

export type SiteBroadcastEventName =
  (typeof SITE_BROADCAST_EVENTS)[keyof typeof SITE_BROADCAST_EVENTS]

export interface SiteProvisioningStartedPayload {
  siteId: string
  serverId: string
  organizationId: string
  domain: string
  status: SiteStatus
  runtime: string
}

export interface SiteCreatedPayload {
  siteId: string
  serverId: string
  organizationId: string
  domain: string
  status: SiteStatus
  runtime: string
  webroot: string
}

export interface SiteProvisioningFailedPayload {
  siteId: string
  serverId: string
  organizationId: string
  domain: string
  message: string
  siteRemoved: boolean
}

export interface SiteDnsSslStatusChangedPayload {
  siteId: string
  serverId: string
  organizationId: string
  domain: string
  dnsStatus: string | null
  dnsError: string | null
  sslStatus: string | null
  sslError: string | null
}

export interface EnvVarPullPreviewReadyPayload {
  siteId: string
  serverId: string
  organizationId: string
  status: 'ready' | 'failed'
  diff?: {
    serverFileExists: boolean
    new: string[]
    changed: string[]
    unchanged: string[]
    helixOnly: string[]
    skipped: Array<{ key: string; reason: string }>
  } | null
  message?: string | null
}

export interface EnvVarsPulledPayload {
  siteId: string
  serverId: string
  organizationId: string
  strategy: string
  created: number
  updated: number
  deleted: number
}

export interface CreateSiteAcceptedResponse {
  data: {
    id: string
    domain: string
    status: SiteStatus
    serverId: string
    organizationId: string
  }
  channel: string
}

export function serverSitesChannel(serverId: string): string {
  return `server.${serverId}.sites`
}

export function privateServerSitesChannel(serverId: string): string {
  return `private-${serverSitesChannel(serverId)}`
}
