<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue'
import LogViewerControls from '@/components/common/LogViewerControls.vue'
import LogViewerPanel from '@/components/common/LogViewerPanel.vue'
import { useServerLogsChannel } from '@/composables/useServerLogsChannel'
import { useSnapshotLogViewer } from '@/composables/useSnapshotLogViewer'
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

const logType = ref<SiteLogType>('nginx_access')
const lineCount = ref(100)

const lineCountOptions = [50, 100, 200, 500] as const

const supportsApplicationLogs = computed(
  () => props.runtime === 'php' || props.runtime === 'nodejs',
)

const logTypeOptions = computed(() => {
  const options: Array<{ value: SiteLogType; label: string }> = [
    { value: 'nginx_access', label: 'Access log' },
    { value: 'nginx_error', label: 'Error log' },
  ]

  if (supportsApplicationLogs.value) {
    options.push({ value: 'application', label: 'Application log' })
  }

  return options
})

const description = computed((): string => {
  if (logType.value === 'application' && props.runtime === 'php') {
    return 'Snapshot of the most recent Laravel log file, including daily rotated logs. Use Refresh to fetch the latest lines.'
  }

  if (logType.value === 'application' && props.runtime === 'nodejs') {
    return 'Snapshot of the application error log. Use Refresh to fetch the latest lines.'
  }

  return 'Snapshot of the selected log file. Use Refresh to fetch the latest lines from the server.'
})

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
  buildRequestKey: (type, lines) => `${siteId.value}:${type}:${lines}`,
  fetchLogs: params => fetchSiteLogs(siteId.value, params),
  defaultErrorMessage: 'Unable to load site logs.',
})

useServerLogsChannel(serverId, {
  onSiteLogsReady: handleLogsReady,
})

watch([logType, lineCount], () => {
  stopPolling()
  void loadLogs(true)
})

function handleLogTypeUpdate(value: string): void {
  logType.value = value as SiteLogType
}

void loadLogs(false)
</script>

<template>
  <div class="space-y-4">
    <LogViewerControls
      :log-type="logType"
      :line-count="lineCount"
      :log-type-options="logTypeOptions"
      :line-count-options="lineCountOptions"
      :is-loading="isLoading"
      :description="description"
      refresh-test-id="site-logs-refresh"
      @update:log-type="handleLogTypeUpdate"
      @update:line-count="lineCount = $event"
      @refresh="handleRefresh"
    />

    <LogViewerPanel
      :lines="logLines"
      :is-loading="isLoading"
      :error-message="errorMessage"
      :requested-lines="lineCount"
      @retry="handleRefresh"
    />
  </div>
</template>
