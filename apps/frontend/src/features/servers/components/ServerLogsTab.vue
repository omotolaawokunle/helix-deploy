<script setup lang="ts">
import { ref, toRef, watch } from 'vue'
import LogViewerControls from '@/components/common/LogViewerControls.vue'
import LogViewerPanel from '@/components/common/LogViewerPanel.vue'
import { useServerLogsChannel } from '@/composables/useServerLogsChannel'
import { useSnapshotLogViewer } from '@/composables/useSnapshotLogViewer'
import { fetchServerLogs } from '@/features/servers/api'
import type { ServerLogType } from '@/features/logs/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

const serverId = toRef(props, 'serverId')

const logType = ref<ServerLogType>('nginx_access')
const lineCount = ref(100)

const lineCountOptions = [50, 100, 200, 500] as const

const logTypeOptions: Array<{ value: ServerLogType; label: string }> = [
  { value: 'nginx_access', label: 'Access log' },
  { value: 'nginx_error', label: 'Error log' },
]

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
  <div class="space-y-4">
    <LogViewerControls
      :log-type="logType"
      :line-count="lineCount"
      :log-type-options="logTypeOptions"
      :line-count-options="lineCountOptions"
      :is-loading="isLoading"
      description="Snapshot of the selected nginx log file. Use Refresh to fetch the latest lines from the server."
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
