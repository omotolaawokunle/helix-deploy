export const ORGANIZATION_BROADCAST_EVENTS = {
  serverConnected: 'server.connected',
  serverConnectionFailed: 'server.connection_failed',
  serverHealthChanged: 'server.health_changed',
  serverDeleted: 'server.deleted',
  serverInventoryDiscovered: 'server.inventory_discovered',
  deploymentStarted: 'deployment.started',
  deploymentCompleted: 'deployment.completed',
  deploymentFailed: 'deployment.failed',
  deploymentRolledBack: 'deployment.rolled_back',
  buildRunnerOnline: 'build_runner.online',
  buildRunnerOffline: 'build_runner.offline',
  buildRunnerSlotsUpdated: 'build_runner.slots_updated',
  serverMetricsUpdated: 'server.metrics_updated',
  daemonChanged: 'daemon.changed',
  serverServiceStatusUpdated: 'server.service_status_updated',
} as const

export type OrganizationBroadcastEventName =
  (typeof ORGANIZATION_BROADCAST_EVENTS)[keyof typeof ORGANIZATION_BROADCAST_EVENTS]

export function privateOrganizationChannel(organizationId: string): string {
  return `organizations.${organizationId}`
}

export interface ServerConnectedPayload {
  serverId: string
  status: string
  os: string | null
  phpVersion: string | null
  nodeVersion: string | null
}

export interface ServerInventoryDiscoveredPayload {
  serverId: string
  installedServices: Record<string, { installed?: boolean }>
  sitesCreated: number
  sitesUpdated: number
  discoveredSiteCount: number
}

export interface ServerConnectionFailedPayload {
  serverId: string
  status: string
  reason: string | null
}

export interface ServerHealthChangedPayload {
  serverId: string
  previousStatus: string
  currentStatus: string
}

export interface ServerMetricsUpdatedPayload {
  serverId: string
  cpuPercent?: number | null
  memoryUsedPercent?: number | null
  diskUsedPercent?: number | null
  lastCheckedAt?: string | null
}

export interface ServerDeletedPayload {
  serverId: string
  organizationId: string
}

export interface BuildRunnerStatusPayload {
  runnerId: string
  status?: string
  reason?: string | null
  activeBuilds?: number
  maxConcurrentBuilds?: number
  availableSlots?: number
}

export type BuildRunnerSlotsPayload = Pick<
  BuildRunnerStatusPayload,
  'runnerId' | 'activeBuilds' | 'maxConcurrentBuilds' | 'availableSlots'
>

export interface DaemonChangedPayload {
  serverId: string
  organizationId: string
  daemonId: string
  action: 'created' | 'updated' | 'deleted'
  daemon: Record<string, unknown> | null
}

export interface ServerServiceStatusUpdatedPayload {
  serverId: string
  services: Array<{
    key: string
    label: string
    installed: boolean
    status: string
    statusCheckedAt: string | null
    controllable: boolean
  }>
}
