export type OnboardingHintId = 'server-detail' | 'site-detail' | 'integrations-settings'

export const ONBOARDING_HINT_PREFIX = 'helix-hint-seen'

export function hintStorageKey(hintId: OnboardingHintId): string {
  return `${ONBOARDING_HINT_PREFIX}-${hintId}`
}

export function readHintSeen(hintId: OnboardingHintId): boolean {
  return localStorage.getItem(hintStorageKey(hintId)) === 'true'
}

export function writeHintSeen(hintId: OnboardingHintId): void {
  localStorage.setItem(hintStorageKey(hintId), 'true')
}

export function clearHintSeen(hintId: OnboardingHintId): void {
  localStorage.removeItem(hintStorageKey(hintId))
}
