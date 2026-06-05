<script setup lang="ts">
import { computed } from 'vue'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { useRealtimeStore, type RealtimeConnectionStatus } from '@/stores/useRealtimeStore'

const realtimeStore = useRealtimeStore()

const isVisible = computed(
  () => realtimeStore.connectionStatus !== 'unconfigured',
)

interface StatusConfig {
  label: string
  detail: string
  pulse: boolean
  className: string
}

const statusConfig = computed((): StatusConfig | null => {
  const configs: Record<Exclude<RealtimeConnectionStatus, 'unconfigured'>, StatusConfig> = {
    connecting: {
      label: 'Connecting',
      detail: 'Establishing live updates channel.',
      pulse: true,
      className: 'text-muted-foreground',
    },
    connected: {
      label: 'Live',
      detail: 'Status and deployment changes update in real time.',
      pulse: true,
      className: 'text-primary',
    },
    disconnected: {
      label: 'Offline',
      detail: 'Live updates paused. Data refreshes when you return to this tab.',
      pulse: false,
      className: 'text-muted-foreground',
    },
    unavailable: {
      label: 'Offline',
      detail: 'Could not reach the updates server. Data refreshes when you return to this tab.',
      pulse: false,
      className: 'text-muted-foreground',
    },
  }

  if (realtimeStore.connectionStatus === 'unconfigured') {
    return null
  }

  return configs[realtimeStore.connectionStatus]
})
</script>

<template>
  <TooltipProvider v-if="isVisible && statusConfig !== null">
    <Tooltip>
      <TooltipTrigger as-child>
        <div
          class="hidden items-center gap-1.5 rounded-md border border-transparent px-2 py-1 text-xs font-medium transition-colors duration-200 sm:flex"
          :class="statusConfig.className"
          data-testid="live-updates-indicator"
        >
          <span
            class="inline-flex size-1.5 rounded-full bg-current motion-reduce:animate-none"
            :class="cn(statusConfig.pulse && 'animate-pulse')"
            aria-hidden="true"
          />
          <span>{{ statusConfig.label }}</span>
        </div>
      </TooltipTrigger>
      <TooltipContent side="bottom" class="max-w-xs text-xs">
        {{ statusConfig.detail }}
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
</template>
