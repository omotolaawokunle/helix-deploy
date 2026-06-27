<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { TerminalIcon } from '@lucide/vue'
import LogViewerControls from '@/components/common/LogViewerControls.vue'
import LogViewerPanel from '@/components/common/LogViewerPanel.vue'
import { Button } from '@/components/ui/button'
import { useServerLogsChannel } from '@/composables/useServerLogsChannel'
import { useSnapshotLogViewer } from '@/composables/useSnapshotLogViewer'
import { LOG_LINE_COUNT_OPTIONS } from '@/features/logs/constants'
import { fetchSiteLogs } from '@/features/sites/api'
import type { SiteLogType } from '@/features/logs/types'

interface Props {
  siteId: string
  serverId: string
  runtime: string
}

const props = defineProps<Props>()

const siteId = toRef(props, 'siteId')
const serverId = toRef(props, 'serverId')

const logType = ref<SiteLogType>('application')
const lineCount = ref(100)

const supportsApplicationLogs = computed(
  () => props.runtime === 'php' || props.runtime === 'nodejs',
)

const description = computed((): string => {
  if (props.runtime === 'php') {
    return 'Snapshot of the most recent Laravel log file, including daily rotated logs. Use Refresh to fetch the latest lines.'
  }

  if (props.runtime === 'nodejs') {
    return 'Snapshot of the application error log. Use Refresh to fetch the latest lines.'
  }

  return ''
})

const serverLogsRoute = computed(() => ({
  name: 'server-detail' as const,
  params: { id: serverId.value },
  query: { tab: 'logs' },
}))

const {
  logLines,
  isLoading,
  errorMessage,
  loadLogs,
  handleRefresh,
  handleLogsReady,
  stopPolling,
} = useSnapshotLogViewer<SiteLogType>({
  logType,
  lineCount,
  buildRequestKey: (_type, lines) => `${siteId.value}:application:${lines}`,
  fetchLogs: ({ lines, refresh }) => fetchSiteLogs(siteId.value, { lines, refresh }),
  defaultErrorMessage: 'Unable to load application logs.',
})

useServerLogsChannel(serverId, {
  onSiteLogsReady: handleLogsReady,
})

watch(lineCount, () => {
  if (!supportsApplicationLogs.value) {
    return
  }

  stopPolling()
  void loadLogs(true)
})

if (supportsApplicationLogs.value) {
  void loadLogs(false)
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="!supportsApplicationLogs"
      class="panel animate-panel-in flex flex-col items-center justify-center gap-4 border-dashed px-6 py-10 text-center motion-reduce:animate-none"
      data-testid="site-logs-unavailable"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full border bg-muted/50"
        aria-hidden="true"
      >
        <TerminalIcon class="size-5 text-muted-foreground" />
      </div>
      <div class="max-w-md space-y-1">
        <p class="text-sm font-medium text-foreground">
          No application logs for this runtime
        </p>
        <p class="text-sm leading-relaxed text-muted-foreground">
          Static sites do not write application log files. View nginx access and error logs on the
          server instead.
        </p>
      </div>
      <Button
        variant="outline"
        size="sm"
        as-child
      >
        <RouterLink :to="serverLogsRoute">
          View server logs
        </RouterLink>
      </Button>
    </div>

    <template v-else>
      <LogViewerControls
        controls-id="site-logs"
        :log-type="logType"
        :line-count="lineCount"
        :log-type-options="[]"
        :line-count-options="LOG_LINE_COUNT_OPTIONS"
        :is-loading="isLoading"
        :description="description"
        refresh-test-id="site-logs-refresh"
        @update:line-count="lineCount = $event"
        @refresh="handleRefresh"
      />

      <LogViewerPanel
        :lines="logLines"
        :is-loading="isLoading"
        :error-message="errorMessage"
        :requested-lines="lineCount"
        empty-message="No application log lines in this snapshot."
        @retry="handleRefresh"
      />
    </template>
  </div>
</template>
