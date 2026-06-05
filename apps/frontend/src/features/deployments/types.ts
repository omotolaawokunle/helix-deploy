export const DEPLOYMENT_BROADCAST_EVENTS = {
  started: 'deployment.started',
  stepStarted: 'deployment.step.started',
  stepFinished: 'deployment.step.finished',
  logLine: 'deployment.log_line',
  completed: 'deployment.completed',
  rolledBack: 'deployment.rolled_back',
  failed: 'deployment.failed',
} as const

export type DeploymentBroadcastEventName =
  (typeof DEPLOYMENT_BROADCAST_EVENTS)[keyof typeof DEPLOYMENT_BROADCAST_EVENTS]

export function privateDeploymentChannel(deploymentId: string): string {
  return `deployment.${deploymentId}`
}

export type DeploymentStepStatus =
  | 'pending'
  | 'running'
  | 'success'
  | 'failed'
  | 'skipped'

export type DeploymentStepPhase = 'build' | 'deploy'

export interface DeploymentLogLine {
  timestamp: string
  content: string
}

export interface DeploymentLogStep {
  id: string
  name: string
  order: number
  phase: DeploymentStepPhase
  status: DeploymentStepStatus
  duration: number | null
  lines: DeploymentLogLine[]
}

export interface DeploymentTriggeredBy {
  id: string
  name: string
}

export interface DeploymentSiteSummary {
  id: string
  domain: string
  deployBranch: string
  serverId: string
  isProduction: boolean
}

export interface DeploymentStepResource {
  id: string
  deploymentId: string
  name: string
  status: DeploymentStepStatus
  phase: DeploymentStepPhase
  order: number
  exitCode: number | null
  startedAt: string | null
  finishedAt: string | null
}

export interface DeploymentDetail {
  id: string
  organizationId: string
  siteId: string
  type: string
  status: string
  triggerType: string
  branch: string | null
  commitHash: string | null
  commitMessage: string | null
  releasePath: string | null
  pipelineRunId: string | null
  buildStrategy: string | null
  buildRunnerId: string | null
  buildArtifactId: string | null
  isRollbackable: boolean
  triggeredBy: DeploymentTriggeredBy | null
  startedAt: string | null
  finishedAt: string | null
  createdAt: string
  updatedAt: string
  duration: number | null
  activeReleaseId: string | null
  site: DeploymentSiteSummary | null
  steps: DeploymentStepResource[]
}

export interface DeploymentListItem extends Omit<DeploymentDetail, 'steps' | 'site' | 'releasePath'> {
  releaseId: string | null
  isActiveRelease: boolean
  site?: DeploymentSiteSummary | null
}

export interface DeploymentStartedPayload {
  deploymentId: string
  siteId: string
  organizationId: string
  status: string
  branch: string | null
  startedAt: string | null
}

export interface DeploymentStepEventPayload {
  deploymentId: string
  stepName: string
  order: number
}

export interface DeploymentStepFinishedPayload extends DeploymentStepEventPayload {
  status: string
}

export interface DeploymentLogLinePayload {
  deploymentId: string
  line: string
}

export interface DeploymentCompletedPayload {
  deploymentId: string
  siteId: string
  organizationId: string
  status: string
  duration: number | null
  releaseId: string | null
  commitHash: string | null
  finishedAt: string | null
}

export interface DeploymentFailedPayload {
  deploymentId: string
  siteId: string
  organizationId: string
  status: string
  message: string
  failedStepName: string | null
  finishedAt: string | null
}

export interface DeploymentApprovalRequiredPayload {
  deploymentId: string
  siteId: string
  reason: string
}

export interface CursorPaginatedMeta {
  path: string
  per_page: number
  next_cursor: string | null
  prev_cursor: string | null
}

export interface CursorPaginatedResponse<T> {
  data: T[]
  meta: CursorPaginatedMeta
}

export interface TriggerDeploymentPayload {
  branch?: string
}

export interface RollbackDeploymentPayload {
  reason?: string
}
