<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import {
  DaemonStatus,
  DeploymentStatus,
  ServerStatus,
} from '@/types'

type StatusType = 'server' | 'deployment' | 'daemon' | 'build-runner' | 'site' | 'dns' | 'ssl'

interface Props {
  status: string
  type: StatusType
}

const props = defineProps<Props>()

interface StatusConfig {
  label: string
  className: string
  pulse?: boolean
}

const serverStatusMap: Record<string, StatusConfig> = {
  [ServerStatus.Connecting]: {
    label: 'Connecting',
    className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200',
    pulse: true,
  },
  [ServerStatus.Active]: {
    label: 'Active',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  [ServerStatus.Disconnected]: {
    label: 'Disconnected',
    className: 'bg-destructive/10 text-destructive',
  },
  [ServerStatus.Maintenance]: {
    label: 'Maintenance',
    className: 'bg-muted text-muted-foreground',
  },
}

const deploymentStatusMap: Record<string, StatusConfig> = {
  [DeploymentStatus.Pending]: {
    label: 'Pending',
    className: 'bg-muted text-muted-foreground',
  },
  [DeploymentStatus.Building]: {
    label: 'Building',
    className: 'bg-primary/10 text-primary dark:bg-primary/20',
    pulse: true,
  },
  [DeploymentStatus.Built]: {
    label: 'Built',
    className: 'bg-secondary text-secondary-foreground',
  },
  [DeploymentStatus.Running]: {
    label: 'Running',
    className: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    pulse: true,
  },
  [DeploymentStatus.Succeeded]: {
    label: 'Succeeded',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  [DeploymentStatus.Failed]: {
    label: 'Failed',
    className: 'bg-destructive/10 text-destructive',
  },
  [DeploymentStatus.Cancelled]: {
    label: 'Cancelled',
    className: 'bg-muted text-muted-foreground',
  },
  [DeploymentStatus.AwaitingApproval]: {
    label: 'Awaiting approval',
    className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200',
    pulse: true,
  },
}

const daemonStatusMap: Record<string, StatusConfig> = {
  [DaemonStatus.Running]: {
    label: 'Running',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    pulse: true,
  },
  [DaemonStatus.Stopped]: {
    label: 'Stopped',
    className: 'bg-muted text-muted-foreground',
  },
  [DaemonStatus.Crashed]: {
    label: 'Crashed',
    className: 'bg-destructive/10 text-destructive',
  },
  error: {
    label: 'Error',
    className: 'bg-destructive/10 text-destructive',
  },
}

const buildRunnerStatusMap: Record<string, StatusConfig> = {
  connecting: {
    label: 'Connecting',
    className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200',
    pulse: true,
  },
  online: {
    label: 'Online',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  offline: {
    label: 'Offline',
    className: 'bg-destructive/10 text-destructive',
  },
  maintenance: {
    label: 'Maintenance',
    className: 'bg-muted text-muted-foreground',
  },
}

const siteStatusMap: Record<string, StatusConfig> = {
  provisioning: {
    label: 'Provisioning',
    className: 'bg-primary/10 text-primary dark:bg-primary/20',
    pulse: true,
  },
  active: {
    label: 'Active',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  discovered: {
    label: 'Discovered',
    className: 'bg-secondary text-secondary-foreground',
  },
  failed: {
    label: 'Failed',
    className: 'bg-destructive/10 text-destructive',
  },
}

const dnsStatusMap: Record<string, StatusConfig> = {
  none: {
    label: 'Not managed',
    className: 'bg-muted text-muted-foreground',
  },
  pending: {
    label: 'Pending',
    className: 'bg-primary/10 text-primary dark:bg-primary/20',
    pulse: true,
  },
  active: {
    label: 'Active',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  failed: {
    label: 'Failed',
    className: 'bg-destructive/10 text-destructive',
  },
}

const sslStatusMap: Record<string, StatusConfig> = {
  none: {
    label: 'Not enabled',
    className: 'bg-muted text-muted-foreground',
  },
  pending: {
    label: 'Pending',
    className: 'bg-primary/10 text-primary dark:bg-primary/20',
    pulse: true,
  },
  active: {
    label: 'Active',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  failed: {
    label: 'Failed',
    className: 'bg-destructive/10 text-destructive',
  },
}

const config = computed<StatusConfig>(() => {
  const maps: Record<StatusType, Record<string, StatusConfig>> = {
    server: serverStatusMap,
    deployment: deploymentStatusMap,
    daemon: daemonStatusMap,
    'build-runner': buildRunnerStatusMap,
    site: siteStatusMap,
    dns: dnsStatusMap,
    ssl: sslStatusMap,
  }

  const map = maps[props.type]

  return map[props.status] ?? {
    label: props.status,
    className: 'bg-muted text-muted-foreground',
  }
})
</script>

<template>
  <Badge
    data-testid="status-badge"
    variant="outline"
    :class="cn('border-transparent capitalize', config.className)"
  >
    <span
      v-if="config.pulse"
      class="mr-1 inline-flex size-1.5 animate-pulse rounded-full bg-current"
    />
    {{ config.label }}
  </Badge>
</template>
