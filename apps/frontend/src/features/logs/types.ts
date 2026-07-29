export const SERVER_LOGS_BROADCAST_EVENTS = {
  serverReady: 'server.logs.ready',
  siteReady: 'site.logs.ready',
} as const

export type ServerLogsBroadcastEventName =
  (typeof SERVER_LOGS_BROADCAST_EVENTS)[keyof typeof SERVER_LOGS_BROADCAST_EVENTS]

export function privateServerLogsChannel(serverId: string): string {
  return `server.${serverId}.logs`
}

export type LogFetchStatus = 'loading' | 'ready' | 'failed'

export type SiteLogType = 'application'

export type ServerLogType = 'nginx_access' | 'nginx_error'

export interface LogFetchResponse {
  status: LogFetchStatus
  lines: string[]
  message?: string | null
  logType?: SiteLogType | ServerLogType | null
  linesRequested?: number | null
}

export interface ServerLogsReadyPayload {
  serverId: string
  organizationId: string
  logType: ServerLogType
  linesRequested: number
  status: 'ready' | 'failed'
  message?: string | null
}

export interface SiteLogsReadyPayload {
  serverId: string
  organizationId: string
  siteId: string
  logType: SiteLogType
  linesRequested: number
  status: 'ready' | 'failed'
  message?: string | null
}
