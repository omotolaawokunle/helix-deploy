import type { OnboardingInput, OnboardingStep } from '@/features/onboarding/types'

export function computeOnboardingSteps(input: OnboardingInput): OnboardingStep[] {
  const hasServer = input.serverCount > 0
  const hasProject = input.projectCount > 0
  const hasSite = input.siteCount > 0
  const hasDeployment = input.deploymentCount > 0

  const serverRoute = input.firstServerId !== null
    ? `/servers/${input.firstServerId}`
    : '/servers'

  const siteRoute = input.firstServerId !== null
    ? `/servers/${input.firstServerId}`
    : '/servers'

  const deployRoute = input.firstSiteId !== null && input.firstSiteServerId !== null
    ? `/servers/${input.firstSiteServerId}/sites/${input.firstSiteId}`
    : '/dashboard'

  return [
    {
      id: 'add-server',
      title: 'Add a server',
      description: 'Register a Linux VPS with SSH access. HelixDeploy connects over SSH — no agent required.',
      completed: hasServer,
      to: '/servers',
      actionLabel: 'Add server',
    },
    {
      id: 'provision-server',
      title: 'Provision the server',
      description: 'Install Nginx, PHP, Node.js, and other services. Skip if your stack is already configured.',
      completed: input.hasProvisionedServer,
      optional: true,
      to: serverRoute,
      actionLabel: 'Provision',
    },
    {
      id: 'create-project',
      title: 'Create a project',
      description: 'Group servers and environments by application or client. Recommended before you deploy.',
      completed: hasProject,
      optional: true,
      to: '/projects',
      actionLabel: 'New project',
    },
    {
      id: 'create-site',
      title: 'Create a site',
      description: 'Connect a Git repository or Docker image on a server, then configure the runtime.',
      completed: hasSite,
      to: siteRoute,
      actionLabel: 'Create site',
    },
    {
      id: 'connect-integration',
      title: 'Connect a DNS provider',
      description: 'Link Cloudflare or DigitalOcean to auto-create DNS records when you add sites. Skip if you manage DNS manually.',
      completed: input.hasDnsIntegration,
      optional: true,
      to: '/settings/integrations',
      actionLabel: 'Integrations',
    },
    {
      id: 'deploy',
      title: 'Deploy',
      description: 'Trigger your first deployment and watch logs stream in real time.',
      completed: hasDeployment,
      to: deployRoute,
      actionLabel: 'Deploy',
    },
  ]
}

export function countCompletedSteps(steps: OnboardingStep[]): number {
  return steps.filter(step => step.completed).length
}

export function findCurrentStep(steps: OnboardingStep[]): OnboardingStep | null {
  return steps.find(step => !step.completed) ?? null
}

export function isOnboardingComplete(steps: OnboardingStep[]): boolean {
  const requiredSteps = steps.filter(step => !step.optional)

  return requiredSteps.every(step => step.completed)
}
