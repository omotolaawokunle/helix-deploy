<script setup lang="ts">
import { InfoIcon } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import type { OnboardingHintId } from '@/features/onboarding/lib/hintStorage'
import { useFirstVisitHint } from '@/features/onboarding/composables/useFirstVisitHint'

interface Props {
  hintId: OnboardingHintId
  title: string
  description: string
}

const props = defineProps<Props>()

const { isVisible, dismiss } = useFirstVisitHint(props.hintId)
</script>

<template>
  <Alert
    v-if="isVisible"
    class="border-primary/20 bg-primary/5 text-foreground *:data-[slot=alert-description]:text-muted-foreground"
    :data-testid="`first-visit-hint-${hintId}`"
  >
    <InfoIcon class="text-primary" aria-hidden="true" />
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <AlertTitle>{{ title }}</AlertTitle>
        <AlertDescription>
          {{ description }}
        </AlertDescription>
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="shrink-0 border-primary/30 bg-background"
        :data-testid="`first-visit-hint-dismiss-${hintId}`"
        @click="dismiss"
      >
        Got it
      </Button>
    </div>
  </Alert>
</template>
