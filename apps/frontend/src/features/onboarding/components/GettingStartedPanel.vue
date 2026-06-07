<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckCircle2Icon, CircleIcon } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import type { OnboardingStep } from '@/features/onboarding/types'
import { cn } from '@/lib/utils'

interface Props {
  steps: OnboardingStep[]
  completedCount: number
  currentStep: OnboardingStep | null
}

const props = defineProps<Props>()

defineEmits<{
  dismiss: []
}>()

const progressPercent = computed((): number =>
  props.steps.length === 0 ? 0 : (props.completedCount / props.steps.length) * 100,
)
</script>

<template>
  <section
    class="panel overflow-hidden"
    data-testid="getting-started-panel"
  >
    <div class="flex flex-wrap items-start justify-between gap-4 border-b px-4 py-4 sm:px-6">
      <div class="space-y-1">
        <h2 class="text-base font-semibold">
          Getting started
        </h2>
        <p class="text-sm text-muted-foreground">
          Set up your first deployment — about 15 minutes to add a server.
        </p>
      </div>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="shrink-0 text-muted-foreground"
        data-testid="getting-started-dismiss"
        @click="$emit('dismiss')"
      >
        Dismiss
      </Button>
    </div>

    <div class="px-4 py-3 sm:px-6">
      <div class="mb-1 flex items-center justify-between text-xs text-muted-foreground">
        <span>{{ completedCount }} of {{ steps.length }} complete</span>
      </div>
      <div
        class="h-1.5 overflow-hidden rounded-full bg-muted"
        role="progressbar"
        :aria-valuenow="completedCount"
        :aria-valuemin="0"
        :aria-valuemax="steps.length"
        aria-label="Setup progress"
      >
        <div
          class="h-full rounded-full bg-primary transition-all duration-200"
          :style="{ width: `${progressPercent}%` }"
        />
      </div>
    </div>

    <ol class="divide-y">
      <li
        v-for="step in steps"
        :key="step.id"
        class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        :data-testid="`onboarding-step-${step.id}`"
      >
        <div class="flex min-w-0 items-start gap-3">
          <CheckCircle2Icon
            v-if="step.completed"
            class="mt-0.5 size-5 shrink-0 text-primary"
            aria-hidden="true"
          />
          <CircleIcon
            v-else
            class="mt-0.5 size-5 shrink-0 text-muted-foreground/60"
            aria-hidden="true"
          />
          <div class="min-w-0">
            <p
              class="font-medium"
              :class="cn(step.completed && 'text-muted-foreground line-through decoration-muted-foreground/50')"
            >
              {{ step.title }}
              <span
                v-if="step.optional"
                class="ml-1 text-xs font-normal text-muted-foreground"
              >
                (optional)
              </span>
            </p>
            <p class="mt-0.5 text-sm text-muted-foreground">
              {{ step.description }}
            </p>
          </div>
        </div>

        <Button
          v-if="!step.completed && step.to !== undefined && currentStep?.id === step.id"
          :as="RouterLink"
          :to="step.to"
          type="button"
          size="sm"
          class="shrink-0 sm:ml-4"
          :data-testid="`onboarding-action-${step.id}`"
        >
          {{ step.actionLabel }}
        </Button>
      </li>
    </ol>
  </section>
</template>
