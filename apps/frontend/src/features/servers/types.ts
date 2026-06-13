import type { Environment, Project } from '@/types'

export interface RegisterServerPayload {
  name: string
  hostname: string
  ipAddress: string
  sshPort: number
  sshUser: string
  provider: string
  managementMode: string
  authMethod: 'generate' | 'import'
  privateKey?: string
  projectId?: string
  environmentId?: string
  region?: string | null
  serverType?: string | null
  providerInstanceId?: string | null
  os?: string | null
  tags?: string[]
}

export interface ServerRegistrationResponse {
  server: import('@/types').Server
  publicKey: string | null
}

export interface ProvisionServerPayload {
  scripts: string[]
  options?: {
    phpVersion?: string
    nodeVersion?: number
    redisPassword?: string
  }
}

export interface ProvisionServerResponse {
  jobId: string
  channel: string
}

export interface ProjectOption extends Pick<Project, 'id' | 'name'> {
  description?: string | null
}

export interface EnvironmentOption extends Pick<Environment, 'id' | 'name'> {
  label?: string | null
  isProduction?: boolean
}

export const PROVISIONING_SCRIPTS = [
  'create-deploy-user',
  'nginx',
  'php',
  'mysql',
  'postgresql',
  'redis',
  'nodejs',
  'python',
  'supervisor',
  'docker',
  'certbot',
] as const

export type ProvisioningScript = (typeof PROVISIONING_SCRIPTS)[number]

export const PROVISIONING_BROADCAST_EVENTS = {
  logLine: 'provisioning.log_line',
  completed: 'provisioning.completed',
} as const

export type ProvisioningBroadcastEventName =
  (typeof PROVISIONING_BROADCAST_EVENTS)[keyof typeof PROVISIONING_BROADCAST_EVENTS]

export function privateServerProvisioningChannel(serverId: string): string {
  return `server.${serverId}.provisioning`
}

export interface ProvisioningLogLinePayload {
  serverId: string
  runId: string
  line: string
}

export interface ProvisioningCompletedPayload {
  serverId: string
  runId: string
  installedServices: string[]
}

export interface ProvisionTemplate {
  id: string
  label: string
  scripts: ProvisioningScript[]
  phpVersion?: string
  nodeVersion?: number
}

export const PROVISION_TEMPLATES: ProvisionTemplate[] = [
  {
    id: 'laravel',
    label: 'Laravel Stack',
    scripts: ['nginx', 'php', 'mysql', 'redis', 'supervisor', 'certbot'],
    phpVersion: '8.3',
  },
  {
    id: 'node-api',
    label: 'Node API Stack',
    scripts: ['nginx', 'nodejs', 'postgresql', 'redis'],
    nodeVersion: 20,
  },
  {
    id: 'worker',
    label: 'Worker Stack',
    scripts: ['supervisor', 'redis', 'python'],
  },
]

export const SCRIPT_ESTIMATED_MINUTES: Record<ProvisioningScript, number> = {
  'create-deploy-user': 2,
  nginx: 3,
  php: 4,
  mysql: 4,
  postgresql: 4,
  redis: 2,
  nodejs: 3,
  python: 3,
  supervisor: 2,
  docker: 5,
  certbot: 2,
}

export const PHP_VERSIONS = ['8.1', '8.2', '8.3'] as const
export const NODE_VERSIONS = [18, 20, 22] as const

export type ServiceRuntimeStatus = 'running' | 'stopped' | 'failed' | 'unknown'

export interface InstalledServiceRecord {
  key: string
  label: string
  installed: boolean
  status: ServiceRuntimeStatus
  statusCheckedAt: string | null
  controllable: boolean
}
