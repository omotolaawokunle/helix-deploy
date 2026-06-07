import { createSharedComposable } from '@vueuse/core'
import { computed, ref, type ComputedRef, type Ref } from 'vue'
import {
  computeOnboardingSteps,
  countCompletedSteps,
  findCurrentStep,
  isOnboardingComplete,
} from '@/features/onboarding/lib/computeOnboardingSteps'
import {
  clearOnboardingDismissed,
  readOnboardingDismissed,
  writeOnboardingDismissed,
} from '@/features/onboarding/lib/onboardingStorage'
import type { OnboardingInput, OnboardingStep } from '@/features/onboarding/types'

interface UseOnboardingDismissReturn {
  dismissedRevision: Ref<number>
  isDismissed: (orgId: string | undefined) => boolean
  dismiss: (orgId: string) => void
  restore: (orgId: string) => void
}

export const useOnboardingDismiss = createSharedComposable((): UseOnboardingDismissReturn => {
  const dismissedRevision = ref(0)

  function isDismissed(orgId: string | undefined): boolean {
    if (orgId === undefined) {
      return true
    }

    void dismissedRevision.value

    return readOnboardingDismissed(orgId)
  }

  function dismiss(orgId: string): void {
    writeOnboardingDismissed(orgId)
    dismissedRevision.value += 1
  }

  function restore(orgId: string): void {
    clearOnboardingDismissed(orgId)
    dismissedRevision.value += 1
  }

  return {
    dismissedRevision,
    isDismissed,
    dismiss,
    restore,
  }
})

interface UseOnboardingProgressOptions {
  orgId: Ref<string | undefined>
  input: Ref<OnboardingInput>
}

interface UseOnboardingProgressReturn {
  steps: ComputedRef<OnboardingStep[]>
  completedCount: ComputedRef<number>
  currentStep: ComputedRef<OnboardingStep | null>
  isComplete: ComputedRef<boolean>
  showPanel: ComputedRef<boolean>
  dismiss: () => void
}

export function useOnboardingProgress(options: UseOnboardingProgressOptions): UseOnboardingProgressReturn {
  const { isDismissed, dismiss: dismissOrg } = useOnboardingDismiss()

  const steps = computed((): OnboardingStep[] => computeOnboardingSteps(options.input.value))

  const completedCount = computed((): number => countCompletedSteps(steps.value))

  const currentStep = computed((): OnboardingStep | null => findCurrentStep(steps.value))

  const isComplete = computed((): boolean => isOnboardingComplete(steps.value))

  const showPanel = computed((): boolean => {
    const orgId = options.orgId.value

    if (orgId === undefined || isDismissed(orgId) || isComplete.value) {
      return false
    }

    return true
  })

  function dismiss(): void {
    const orgId = options.orgId.value

    if (orgId === undefined) {
      return
    }

    dismissOrg(orgId)
  }

  return {
    steps,
    completedCount,
    currentStep,
    isComplete,
    showPanel,
    dismiss,
  }
}
