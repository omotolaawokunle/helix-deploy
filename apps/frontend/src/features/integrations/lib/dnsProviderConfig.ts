import {
  connectCloudflare,
  connectDigitalOcean,
  disconnectCloudflare,
  disconnectDigitalOcean,
  fetchCloudflareConnection,
  fetchDigitalOceanConnection,
} from '@/features/integrations/api'
import type { DnsProvider, DnsProviderConnection } from '@/features/integrations/types'

export interface DnsProviderUiConfig {
  title: string
  description: string
  tokenInputId: string
  tokenLabel: string
  tokenPlaceholder: string
  tokenHint: string
  connectLabel: string
  connectingLabel: string
  disconnectTitle: string
  disconnectDescription: string
  notConnectedAdminHint: string
  loadErrorMessage: string
  connectErrorMessage: string
  disconnectErrorMessage: string
  connectSuccessMessage: string
  disconnectSuccessMessage: string
  testId: string
}

export const DNS_PROVIDER_UI: Record<DnsProvider, DnsProviderUiConfig> = {
  cloudflare: {
    title: 'Cloudflare DNS',
    description:
      'Connect a Cloudflare API token to manage DNS records for assigned project zones. Tokens are encrypted and never shown after save.',
    tokenInputId: 'cloudflare-api-token',
    tokenLabel: 'Cloudflare API token',
    tokenPlaceholder: 'Paste token with Zone.DNS:Edit on approved zones',
    tokenHint: 'Use a scoped token limited to the zones you plan to assign to projects.',
    connectLabel: 'Connect Cloudflare',
    connectingLabel: 'Connecting…',
    disconnectTitle: 'Disconnect Cloudflare',
    disconnectDescription:
      'Sites with managed DNS will stop receiving automatic record updates. Existing Cloudflare records are not removed.',
    notConnectedAdminHint:
      'Ask an organization admin to connect Cloudflare before assigning DNS zones to projects.',
    loadErrorMessage: 'Unable to load Cloudflare connection status.',
    connectErrorMessage: 'Unable to connect Cloudflare.',
    disconnectErrorMessage: 'Unable to disconnect Cloudflare.',
    connectSuccessMessage: 'Cloudflare connected.',
    disconnectSuccessMessage: 'Cloudflare disconnected.',
    testId: 'cloudflare-connection-panel',
  },
  digitalocean: {
    title: 'DigitalOcean DNS',
    description:
      'Connect a DigitalOcean API token to manage DNS records for assigned project domains. Tokens are encrypted and never shown after save.',
    tokenInputId: 'digitalocean-api-token',
    tokenLabel: 'DigitalOcean API token',
    tokenPlaceholder: 'Paste token with domain read/write scope',
    tokenHint: 'Use a scoped token limited to the domains you plan to assign to projects.',
    connectLabel: 'Connect DigitalOcean',
    connectingLabel: 'Connecting…',
    disconnectTitle: 'Disconnect DigitalOcean',
    disconnectDescription:
      'Sites with managed DNS will stop receiving automatic record updates. Existing DigitalOcean records are not removed.',
    notConnectedAdminHint:
      'Ask an organization admin to connect DigitalOcean before assigning DNS zones to projects.',
    loadErrorMessage: 'Unable to load DigitalOcean connection status.',
    connectErrorMessage: 'Unable to connect DigitalOcean.',
    disconnectErrorMessage: 'Unable to disconnect DigitalOcean.',
    connectSuccessMessage: 'DigitalOcean connected.',
    disconnectSuccessMessage: 'DigitalOcean disconnected.',
    testId: 'digitalocean-connection-panel',
  },
}

export const DNS_PROVIDER_CONNECT: Record<
  DnsProvider,
  (organizationId: string, token: string) => Promise<DnsProviderConnection>
> = {
  cloudflare: connectCloudflare,
  digitalocean: connectDigitalOcean,
}

export const DNS_PROVIDER_DISCONNECT: Record<
  DnsProvider,
  (organizationId: string) => Promise<void>
> = {
  cloudflare: disconnectCloudflare,
  digitalocean: disconnectDigitalOcean,
}

export const DNS_PROVIDER_FETCH: Record<
  DnsProvider,
  (organizationId: string) => Promise<DnsProviderConnection>
> = {
  cloudflare: fetchCloudflareConnection,
  digitalocean: fetchDigitalOceanConnection,
}

export function dnsRecordDescription(provider: DnsProvider | null | undefined): string {
  if (provider === 'cloudflare') {
    return 'A record pointing to the server IP (grey cloud / DNS only, not proxied).'
  }

  if (provider === 'digitalocean') {
    return 'A record pointing to the server IP via DigitalOcean DNS.'
  }

  return 'A record pointing to the server IP via your connected DNS provider.'
}
