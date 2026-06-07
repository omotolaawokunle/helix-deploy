export type OnboardingStepId =
  | 'add-server'
  | 'provision-server'
  | 'create-project'
  | 'create-site'
  | 'connect-integration'
  | 'deploy'

export interface OnboardingStep {
  id: OnboardingStepId
  title: string
  description: string
  completed: boolean
  optional?: boolean
  to?: string
  actionLabel?: string
}

export interface OnboardingInput {
  serverCount: number
  hasProvisionedServer: boolean
  projectCount: number
  siteCount: number
  deploymentCount: number
  hasDnsIntegration: boolean
  firstServerId: string | null
  firstSiteId: string | null
  firstSiteServerId: string | null
}
