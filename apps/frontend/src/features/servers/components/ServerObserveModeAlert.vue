<script setup lang="ts">
import { EyeIcon } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'

interface Props {
  testId?: string
}

withDefaults(defineProps<Props>(), {
  testId: 'observe-mode-server-banner',
})
</script>

<template>
  <Alert
    class="border-border bg-muted/30 text-foreground *:data-[slot=alert-description]:text-muted-foreground"
    :data-testid="testId"
  >
    <EyeIcon class="text-primary" aria-hidden="true" />
    <AlertTitle>Observe mode</AlertTitle>
    <AlertDescription>
      HelixDeploy monitors health and inventory only. Deployments and provisioning stay off so your
      existing nginx, PHP, and database setup is not changed.
    </AlertDescription>
  </Alert>
</template>
