export const ORGANIZATION_BROADCAST_EVENTS = {
  serverConnected: 'server.connected',
  serverConnectionFailed: 'server.connection_failed',
  serverHealthChanged: 'server.health_changed',
  serverDeleted: 'server.deleted',
  deploymentStarted: 'deployment.started',
  deploymentCompleted: 'deployment.completed',
  deploymentFailed: 'deployment.failed',
  deploymentRolledBack: 'deployment.rolled_back',
  buildRunnerOnline: 'build_runner.online',
  buildRunnerOffline: 'build_runner.offline',
  daemonChanged: 'daemon.changed',
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

export interface ServerDeletedPayload {
  serverId: string
  organizationId: string
}

export interface BuildRunnerStatusPayload {
  runnerId: string
  status: string
}

export interface DaemonChangedPayload {
  serverId: string
  organizationId: string
  daemonId: string
  action: 'created' | 'updated' | 'deleted'
  daemon: Record<string, unknown> | null
}
