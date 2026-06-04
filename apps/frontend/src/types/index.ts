export enum ServerStatus {
  Connecting = 'connecting',
  Active = 'active',
  Disconnected = 'disconnected',
  Maintenance = 'maintenance',
}

export enum ServerProvider {
  Aws = 'aws',
  Hetzner = 'hetzner',
  DigitalOcean = 'digitalocean',
  Vultr = 'vultr',
  Linode = 'linode',
  Generic = 'generic',
}

export enum ManagementMode {
  Managed = 'managed',
  Observe = 'observe',
}

export enum Runtime {
  Docker = 'docker',
  Native = 'native',
  Kubernetes = 'kubernetes',
}

export enum DeployMode {
  Rolling = 'rolling',
  BlueGreen = 'blue-green',
  Recreate = 'recreate',
}

export enum DeploymentStatus {
  Pending = 'pending',
  Running = 'running',
  Succeeded = 'succeeded',
  Failed = 'failed',
  Cancelled = 'cancelled',
}

export enum DeploymentType {
  Deploy = 'deploy',
  Rollback = 'rollback',
  Hotfix = 'hotfix',
}

export enum TriggerType {
  Manual = 'manual',
  Push = 'push',
  Schedule = 'schedule',
  Webhook = 'webhook',
}

export enum CredentialType {
  SshKey = 'ssh_key',
  Token = 'token',
  Password = 'password',
  Environment = 'environment',
}

export enum TeamRole {
  Owner = 'owner',
  Admin = 'admin',
  Maintainer = 'maintainer',
  Member = 'member',
  Viewer = 'viewer',
}

export enum DaemonStatus {
  Running = 'running',
  Stopped = 'stopped',
  Crashed = 'crashed',
}

export interface User {
  id: string
  name: string
  email: string
  emailVerifiedAt: string | null
  currentOrganizationId: string | null
  currentOrganization?: Organization | null
  timezone?: string
  createdAt: string
  updatedAt?: string
}

export interface Organization {
  id: string
  name: string
  slug: string
  createdAt: string
  updatedAt: string
}

export interface OrganizationMember {
  id: string
  organizationId: string
  userId: string
  role: TeamRole
  joinedAt: string
}

export interface Team {
  id: string
  organizationId: string
  name: string
  role: TeamRole
  createdAt: string
  updatedAt: string
}

export interface Project {
  id: string
  organizationId: string
  name: string
  slug: string
  description: string | null
  repositoryUrl: string | null
  createdAt: string
  updatedAt: string
}

export interface Environment {
  id: string
  projectId: string
  name: string
  runtime: Runtime
  deployMode: DeployMode
  createdAt: string
  updatedAt: string
}

export interface ServerEnvironmentSummary {
  id: string
  name: string
  label: string | null
  isProduction: boolean
}

export interface ServerProjectSummary {
  id: string
  name: string
  description: string | null
}

export interface ServerHealthStatus {
  diskUsedPercent?: number
  diskTotalGb?: number
  lastCheckedAt?: string
  fingerprintVerified?: boolean
}

export interface Server {
  id: string
  hostname: string
  ipAddress: string
  sshPort: number
  sshUser: string
  provider: ServerProvider
  region: string | null
  serverType: string | null
  os: string | null
  phpVersion: string | null
  nodeVersion: string | null
  status: ServerStatus
  managementMode: ManagementMode
  environment: ServerEnvironmentSummary | null
  project: ServerProjectSummary | null
  tags: string[]
  installedServices: string[]
  healthStatus: ServerHealthStatus | null
  createdAt: string
  updatedAt: string
}

export interface ServerGroup {
  id: string
  organizationId: string
  name: string
  description: string | null
  createdAt: string
  updatedAt: string
}

export interface Site {
  id: string
  organizationId: string
  projectId: string
  serverId: string
  name: string
  domain: string
  runtime: Runtime
  createdAt: string
  updatedAt: string
}

export interface DeploymentStep {
  id: string
  deploymentId: string
  name: string
  order: number
  status: DeploymentStatus
  startedAt: string | null
  finishedAt: string | null
}

export interface Deployment {
  id: string
  organizationId: string
  siteId: string
  releaseId: string | null
  status: DeploymentStatus
  type: DeploymentType
  triggerType: TriggerType
  startedAt: string | null
  finishedAt: string | null
  createdAt: string
  updatedAt: string
  steps: DeploymentStep[]
}

export interface Release {
  id: string
  organizationId: string
  projectId: string
  version: string
  commitSha: string
  createdAt: string
  updatedAt: string
}

export interface PipelineStep {
  id: string
  pipelineId: string
  name: string
  order: number
  runtime: Runtime
  createdAt: string
  updatedAt: string
}

export interface Pipeline {
  id: string
  organizationId: string
  projectId: string
  name: string
  triggerType: TriggerType
  steps: PipelineStep[]
  createdAt: string
  updatedAt: string
}

export interface CronJob {
  id: string
  organizationId: string
  siteId: string
  command: string
  expression: string
  enabled: boolean
  createdAt: string
  updatedAt: string
}

export interface SupervisorProcess {
  id: string
  organizationId: string
  siteId: string
  name: string
  command: string
  status: DaemonStatus
  createdAt: string
  updatedAt: string
}

export interface Credential {
  id: string
  organizationId: string
  name: string
  type: CredentialType
  description: string | null
  createdAt: string
  updatedAt: string
}

export interface EnvVar {
  id: string
  organizationId: string
  siteId: string
  key: string
  valueMasked: boolean
  createdAt: string
  updatedAt: string
}

export interface AuditLog {
  id: string
  organizationId: string
  actorId: string
  action: string
  resourceType: string
  resourceId: string
  beforeState: Record<string, unknown> | null
  afterState: Record<string, unknown> | null
  createdAt: string
}

export interface InfrastructureEvent {
  id: string
  organizationId: string
  source: string
  level: 'info' | 'warning' | 'error'
  message: string
  metadata: Record<string, unknown> | null
  createdAt: string
}
