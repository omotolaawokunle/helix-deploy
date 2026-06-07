export const ONBOARDING_DISMISSED_PREFIX = 'helix-onboarding-dismissed'

export function onboardingDismissedKey(orgId: string): string {
  return `${ONBOARDING_DISMISSED_PREFIX}-${orgId}`
}

export function readOnboardingDismissed(orgId: string): boolean {
  return localStorage.getItem(onboardingDismissedKey(orgId)) === 'true'
}

export function writeOnboardingDismissed(orgId: string): void {
  localStorage.setItem(onboardingDismissedKey(orgId), 'true')
}

export function clearOnboardingDismissed(orgId: string): void {
  localStorage.removeItem(onboardingDismissedKey(orgId))
}
