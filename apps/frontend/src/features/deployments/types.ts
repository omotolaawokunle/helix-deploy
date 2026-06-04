export const DEPLOYMENT_BROADCAST_EVENTS = {
  started: 'deployment.started',
  stepStarted: 'deployment.step.started',
  stepFinished: 'deployment.step.finished',
  logLine: 'deployment.log_line',
  completed: 'deployment.completed',
  failed: 'deployment.failed',
} as const

export type DeploymentBroadcastEventName =
  (typeof DEPLOYMENT_BROADCAST_EVENTS)[keyof typeof DEPLOYMENT_BROADCAST_EVENTS]

export function privateDeploymentChannel(deploymentId: string): string {
  return `deployment.${deploymentId}`
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
  commitHash: string | null
  commitMessage: string | null
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
