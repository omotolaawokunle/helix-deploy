export const DAEMON_BROADCAST_EVENTS = {
  changed: 'daemon.changed',
  logsReady: 'daemon.logs.ready',
} as const

export type DaemonBroadcastEventName =
  (typeof DAEMON_BROADCAST_EVENTS)[keyof typeof DAEMON_BROADCAST_EVENTS]

export function privateServerDaemonsChannel(serverId: string): string {
  return `server.${serverId}.daemons`
}

export interface DaemonChangedPayload {
  serverId: string
  organizationId: string
  daemonId: string
  action: 'created' | 'updated' | 'deleted'
  daemon: {
    id: string
    serverId: string
    organizationId: string
    name: string
    command: string
    directory: string | null
    user: string
    processes: number
    status: string
    createdAt: string | null
    updatedAt: string | null
  } | null
}

export interface DaemonLogsReadyPayload {
  serverId: string
  organizationId: string
  daemonId: string
  status: 'ready' | 'failed'
  lines: string[]
  message?: string | null
}
