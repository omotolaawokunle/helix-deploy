<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue'
import LogViewerControls from '@/components/common/LogViewerControls.vue'
import LogViewerPanel from '@/components/common/LogViewerPanel.vue'
import { useServerLogsChannel } from '@/composables/useServerLogsChannel'
import { useSnapshotLogViewer } from '@/composables/useSnapshotLogViewer'
import { LOG_LINE_COUNT_OPTIONS } from '@/features/logs/constants'
import { fetchServerLogs } from '@/features/servers/api'
import type { ServerLogType } from '@/features/logs/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

const serverId = toRef(props, 'serverId')

const logType = ref<ServerLogType>('nginx_access')
const lineCount = ref(100)

const logTypeOptions: Array<{ value: ServerLogType; label: string }> = [
  { value: 'nginx_access', label: 'Access log' },
  { value: 'nginx_error', label: 'Error log' },
]

const description = computed((): string => {
  if (logType.value === 'nginx_access') {
    return 'Snapshot of the nginx access log. Use Refresh to fetch the latest lines from the server.'
  }

  return 'Snapshot of the nginx error log. Use Refresh to fetch the latest lines from the server.'
})

const {
  logLines,
  isLoading,
  errorMessage,
  loadLogs,
  handleRefresh,
  handleLogsReady,
  stopPolling,
} = useSnapshotLogViewer<ServerLogType>({
  logType,
  lineCount,
  buildRequestKey: (type, lines) => `${serverId.value}:${type}:${lines}`,
  fetchLogs: params => fetchServerLogs(serverId.value, params),
  defaultErrorMessage: 'Unable to load server logs.',
})

useServerLogsChannel(serverId, {
  onServerLogsReady: handleLogsReady,
})

watch([logType, lineCount], () => {
  stopPolling()
  void loadLogs(true)
})

function handleLogTypeUpdate(value: string): void {
  logType.value = value as ServerLogType
}

void loadLogs(false)
</script>

<template>
  <div class="space-y-4 animate-page-in motion-reduce:animate-none">
    <LogViewerControls
      controls-id="server-logs"
      :log-type="logType"
      :line-count="lineCount"
      :log-type-options="logTypeOptions"
      :line-count-options="LOG_LINE_COUNT_OPTIONS"
      :is-loading="isLoading"
      :description="description"
      refresh-test-id="server-logs-refresh"
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
