<script setup lang="ts">
import { AlertTriangleIcon } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import type { BuildRunnerRuntimeWarning } from '@/features/build-runners/lib/buildRunnerRuntimeCompatibility'

interface Props {
  warning: BuildRunnerRuntimeWarning
}

defineProps<Props>()
</script>

<template>
  <Alert
    variant="destructive"
    data-testid="build-runner-runtime-warning"
  >
    <AlertTriangleIcon aria-hidden="true" />
    <AlertTitle>Build runner runtime mismatch</AlertTitle>
    <AlertDescription>
      {{ warning.message }}
      <RouterLink
        to="/build-runners"
        class="mt-1 inline-block font-medium underline underline-offset-2"
      >
        Manage build runners
      </RouterLink>
    </AlertDescription>
  </Alert>
</template>
