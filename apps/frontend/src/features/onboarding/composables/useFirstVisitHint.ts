import { computed, ref, type ComputedRef, type Ref } from 'vue'
import {
  readHintSeen,
  writeHintSeen,
  type OnboardingHintId,
} from '@/features/onboarding/lib/hintStorage'

interface UseFirstVisitHintReturn {
  isVisible: ComputedRef<boolean>
  dismiss: () => void
  revision: Ref<number>
}

export function useFirstVisitHint(hintId: OnboardingHintId): UseFirstVisitHintReturn {
  const revision = ref(0)

  const isVisible = computed((): boolean => {
    void revision.value

    return !readHintSeen(hintId)
  })

  function dismiss(): void {
    writeHintSeen(hintId)
    revision.value += 1
  }

  return {
    isVisible,
    dismiss,
    revision,
  }
}
