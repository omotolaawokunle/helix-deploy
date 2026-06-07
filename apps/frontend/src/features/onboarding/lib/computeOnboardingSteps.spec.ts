import { describe, expect, it } from 'vitest'
import {
  computeOnboardingSteps,
  countCompletedSteps,
  findCurrentStep,
  isOnboardingComplete,
} from '@/features/onboarding/lib/computeOnboardingSteps'
import type { OnboardingInput } from '@/features/onboarding/types'

const emptyInput: OnboardingInput = {
  serverCount: 0,
  hasProvisionedServer: false,
  projectCount: 0,
  siteCount: 0,
  deploymentCount: 0,
  hasDnsIntegration: false,
  firstServerId: null,
  firstSiteId: null,
  firstSiteServerId: null,
}

describe('computeOnboardingSteps', () => {
  it('returns six steps with none completed for a fresh org', () => {
    const steps = computeOnboardingSteps(emptyInput)

    expect(steps).toHaveLength(6)
    expect(steps.every(step => !step.completed)).toBe(true)
    expect(findCurrentStep(steps)?.id).toBe('add-server')
  })

  it('marks server and provision steps complete when services are installed', () => {
    const steps = computeOnboardingSteps({
      ...emptyInput,
      serverCount: 1,
      hasProvisionedServer: true,
      firstServerId: 'server-1',
    })

    expect(steps.find(step => step.id === 'add-server')?.completed).toBe(true)
    expect(steps.find(step => step.id === 'provision-server')?.completed).toBe(true)
    expect(findCurrentStep(steps)?.id).toBe('create-project')
  })

  it('uses site detail route for deploy when a site exists', () => {
    const steps = computeOnboardingSteps({
      ...emptyInput,
      serverCount: 1,
      siteCount: 1,
      firstServerId: 'server-1',
      firstSiteId: 'site-1',
      firstSiteServerId: 'server-1',
    })

    expect(steps.find(step => step.id === 'deploy')?.to).toBe('/servers/server-1/sites/site-1')
  })

  it('marks DNS integration step complete when a provider is connected', () => {
    const steps = computeOnboardingSteps({
      ...emptyInput,
      hasDnsIntegration: true,
    })

    expect(steps.find(step => step.id === 'connect-integration')?.completed).toBe(true)
    expect(steps.find(step => step.id === 'connect-integration')?.optional).toBe(true)
    expect(steps.find(step => step.id === 'connect-integration')?.to).toBe('/settings/integrations')
  })

  it('considers onboarding complete when required steps are done', () => {
    const steps = computeOnboardingSteps({
      ...emptyInput,
      serverCount: 1,
      siteCount: 1,
      deploymentCount: 1,
      firstServerId: 'server-1',
      firstSiteId: 'site-1',
      firstSiteServerId: 'server-1',
    })

    expect(isOnboardingComplete(steps)).toBe(true)
    expect(countCompletedSteps(steps)).toBe(3)
  })

  it('does not require optional steps for completion', () => {
    const steps = computeOnboardingSteps({
      ...emptyInput,
      serverCount: 1,
      siteCount: 1,
      deploymentCount: 1,
      firstServerId: 'server-1',
      firstSiteId: 'site-1',
      firstSiteServerId: 'server-1',
    })

    expect(steps.find(step => step.id === 'create-project')?.completed).toBe(false)
    expect(steps.find(step => step.id === 'provision-server')?.completed).toBe(false)
    expect(isOnboardingComplete(steps)).toBe(true)
  })
})
