<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue'
import { ChevronDownIcon, TerminalIcon } from '@lucide/vue'
import { useBatchedUpdates } from '@/composables/useBatchedUpdates'
import { toast } from 'vue-sonner'
import ConfirmCommandDialog from '@/components/common/ConfirmCommandDialog.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import {
  cancelCommand,
  fetchCommands,
  isCommandConfirmationRequired,
  isCommandQueued,
  runCommand,
} from '@/features/commands/api'
import { useCommandStream } from '@/features/commands/composables/useCommandStream'
import { formatRelativeTime } from '@/lib/format'
import type { CommandRecord, CommandStatus } from '@/types'

interface CommandStatusConfig {
  label: string
  className: string
  pulse?: boolean
}

const commandStatusMap: Record<CommandStatus, CommandStatusConfig> = {
  pending: {
    label: 'Pending',
    className: 'badge-op-muted',
  },
  running: {
    label: 'Running',
    className: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    pulse: true,
  },
  completed: {
    label: 'Completed',
    className: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  },
  cancelled: {
    label: 'Cancelled',
    className: 'badge-op-muted',
  },
  failed: {
    label: 'Failed',
    className: 'badge-op-destructive',
  },
}

interface Props {
  serverId: string
  isProduction: boolean
}

const props = defineProps<Props>()

const commands = ref<CommandRecord[]>([])
const nextCursor = ref<string | null>(null)
const isLoading = ref(true)
const isLoadingMore = ref(false)
const commandInput = ref('')
const commandTimeout = ref(60)
const isRunning = ref(false)
const isCancelling = ref(false)
const expandedId = ref<string | null>(null)
const activeCommandId = ref<string | null>(null)
const MAX_LIVE_LINES = 5_000

const liveLines = shallowRef<string[]>([])
const liveOutput = computed(() => liveLines.value.join('\n'))
const activeCommandStatus = ref<CommandStatus | null>(null)
const liveOutputRef = ref<HTMLElement | null>(null)

const isConfirmOpen = ref(false)
const confirmReason = ref('')
const pendingCommand = ref('')

let streamTeardown: (() => void) | null = null

const { push: pushLiveLine, clear: clearLiveLines } = useBatchedUpdates<string>((lines) => {
  const next = liveLines.value.concat(lines)

  liveLines.value = next.length > MAX_LIVE_LINES
    ? next.slice(next.length - MAX_LIVE_LINES)
    : next
})

const warnPattern = /^(rm\s+-rf|reboot|shutdown|halt|poweroff|mkfs|dd\s+)/i

const needsClientConfirmation = computed(
  () => props.isProduction || warnPattern.test(commandInput.value.trim()),
)

const isActiveCommandRunning = computed(
  () => activeCommandStatus.value === 'pending' || activeCommandStatus.value === 'running',
)

const canRunCommand = computed(
  () => commandInput.value.trim() !== '' && !isRunning.value,
)

const activeStatusConfig = computed(() => resolveCommandStatus(activeCommandStatus.value))

function resolveCommandStatus(status: CommandStatus | null): CommandStatusConfig {
  if (status === null) {
    return commandStatusMap.running
  }

  return commandStatusMap[status] ?? {
    label: status,
    className: 'badge-op-muted',
  }
}

function isTerminalStatus(status: CommandStatus): boolean {
  return status === 'completed' || status === 'cancelled' || status === 'failed'
}

function stopStream(): void {
  if (streamTeardown !== null) {
    streamTeardown()
    streamTeardown = null
  }
}

function startStream(commandId: string): void {
  stopStream()

  const { teardown } = useCommandStream(commandId, {
    onLogLine: (line: string) => {
      pushLiveLine(line)
    },
    onComplete: (payload) => {
      activeCommandStatus.value = payload.status as CommandStatus
      isRunning.value = false
      void loadCommands()
    },
  })

  streamTeardown = teardown
}

async function loadCommands(append = false): Promise<void> {
  if (append) {
    isLoadingMore.value = true
  } else {
    isLoading.value = true
  }

  try {
    const response = await fetchCommands(props.serverId, {
      cursor: append ? nextCursor.value ?? undefined : undefined,
      per_page: 20,
    })

    commands.value = append
      ? [...response.data, ...commands.value]
      : response.data
    nextCursor.value = response.meta.next_cursor
  } catch {
    if (!append) {
      toast.error('Unable to load command history.')
    }
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

function toggleExpanded(commandId: string): void {
  expandedId.value = expandedId.value === commandId ? null : commandId
}

async function executeCommand(command: string, confirmed = false): Promise<void> {
  isRunning.value = true
  clearLiveLines()
  liveLines.value = []
  activeCommandStatus.value = 'pending'

  try {
    const result = await runCommand(props.serverId, {
      command,
      confirmed,
      timeout: commandTimeout.value,
    })

    if (isCommandConfirmationRequired(result)) {
      pendingCommand.value = command
      confirmReason.value = result.reason
      isConfirmOpen.value = true
      isRunning.value = false
      activeCommandStatus.value = null

      return
    }

    if (!isCommandQueued(result)) {
      return
    }

    commandInput.value = ''
    activeCommandId.value = result.data.id
    activeCommandStatus.value = result.data.status
    commands.value = [result.data, ...commands.value.filter(entry => entry.id !== result.data.id)]
    startStream(result.data.id)
    toast.success('Command queued for execution.')
  } catch (error: unknown) {
    const message = typeof error === 'object'
      && error !== null
      && 'response' in error
      && typeof (error as { response?: { data?: { message?: string } } }).response?.data?.message === 'string'
      ? (error as { response: { data: { message: string } } }).response.data.message
      : 'Unable to run command.'

    toast.error(message)
    isRunning.value = false
    activeCommandStatus.value = null
  }
}

async function handleCancelActiveCommand(): Promise<void> {
  if (activeCommandId.value === null) {
    return
  }

  isCancelling.value = true

  try {
    const cancelled = await cancelCommand(activeCommandId.value)
    activeCommandStatus.value = cancelled.status
    isRunning.value = false
    stopStream()
    await loadCommands()
    toast.success('Command cancelled.')
  } catch {
    toast.error('Unable to cancel command.')
  } finally {
    isCancelling.value = false
  }
}

function requestRun(): void {
  const command = commandInput.value.trim()

  if (command === '') {
    return
  }

  if (needsClientConfirmation.value) {
    pendingCommand.value = command
    confirmReason.value = props.isProduction
      ? 'You are running a command on a production server.'
      : 'This command may be destructive or disruptive.'
    isConfirmOpen.value = true

    return
  }

  void executeCommand(command)
}

function handleConfirmRun(): void {
  void executeCommand(pendingCommand.value, true)
}

function handleInputKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter') {
    event.preventDefault()
    requestRun()
  }
}

watch(
  () => liveLines.value.length,
  async () => {
    await nextTick()

    const element = liveOutputRef.value

    if (element !== null) {
      element.scrollTop = element.scrollHeight
    }
  },
)

watch(() => props.serverId, () => {
  stopStream()
  clearLiveLines()
  activeCommandId.value = null
  liveLines.value = []
  activeCommandStatus.value = null
  void loadCommands()
})

onMounted(() => {
  void loadCommands()
})

onUnmounted(() => {
  stopStream()
})
</script>

<template>
  <div class="flex min-h-[32rem] flex-col gap-4">
    <ProductionWarningBanner
      :resource-name="serverId"
      :is-production="isProduction"
      variant="inline"
    />

    <div
      v-if="activeCommandId !== null"
      class="overflow-hidden rounded-lg border border-zinc-800"
    >
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-800 bg-zinc-900/60 px-4 py-2.5">
        <p class="text-sm font-medium text-zinc-100">
          Live output
        </p>
        <div class="flex items-center gap-2">
          <Badge
            variant="outline"
            :class="cn('border-transparent capitalize', activeStatusConfig.className)"
          >
            <span
              v-if="activeStatusConfig.pulse"
              class="mr-1 inline-flex size-1.5 animate-pulse rounded-full bg-current motion-reduce:animate-none"
            />
            {{ activeStatusConfig.label }}
          </Badge>
          <Button
            v-if="isActiveCommandRunning"
            type="button"
            variant="destructive"
            size="sm"
            :disabled="isCancelling"
            data-testid="command-cancel-button"
            @click="handleCancelActiveCommand"
          >
            {{ isCancelling ? 'Cancelling…' : 'Cancel' }}
          </Button>
        </div>
      </div>
      <pre
        ref="liveOutputRef"
        class="log-panel max-h-64 overflow-auto whitespace-pre-wrap p-4"
        aria-live="polite"
        data-testid="command-live-output"
      >{{ liveOutput || 'Waiting for output…' }}</pre>
    </div>

    <div class="flex-1 space-y-3 overflow-y-auto rounded-lg border bg-muted/20 p-4">
      <div v-if="nextCursor !== null" class="text-center">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="isLoadingMore"
          @click="loadCommands(true)"
        >
          {{ isLoadingMore ? 'Loading…' : 'Load more' }}
        </Button>
      </div>

      <p v-if="isLoading" class="text-sm text-muted-foreground">
        Loading command history…
      </p>

      <div
        v-else-if="commands.length === 0"
        class="flex flex-col items-center gap-2 py-12 text-center"
        data-testid="command-history-empty"
      >
        <TerminalIcon class="size-8 text-muted-foreground" aria-hidden="true" />
        <p class="text-sm font-medium">
          No commands yet
        </p>
        <p class="max-w-sm text-sm text-muted-foreground">
          Run a command below to execute it on this server. History appears here once complete.
        </p>
      </div>

      <template v-else>
        <div
          v-for="entry in commands"
          :key="entry.id"
          class="overflow-hidden rounded-lg border bg-background"
        >
        <button
          type="button"
          class="flex w-full flex-wrap items-center gap-2 px-3 py-3 text-left transition-colors hover:bg-muted/40"
          :aria-expanded="expandedId === entry.id"
          @click="toggleExpanded(entry.id)"
        >
          <ChevronDownIcon
            class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none"
            :class="expandedId === entry.id ? 'rotate-180' : ''"
            aria-hidden="true"
          />
          <Badge variant="outline" class="shrink-0">
            {{ entry.executedAt !== null ? formatRelativeTime(entry.executedAt) : 'Queued' }}
          </Badge>
          <Badge
            variant="outline"
            :class="cn('shrink-0 border-transparent capitalize', resolveCommandStatus(entry.status).className)"
          >
            <span
              v-if="resolveCommandStatus(entry.status).pulse"
              class="mr-1 inline-flex size-1.5 animate-pulse rounded-full bg-current motion-reduce:animate-none"
            />
            {{ resolveCommandStatus(entry.status).label }}
          </Badge>
          <span class="shrink-0 text-sm text-muted-foreground">
            {{ entry.user?.name ?? 'Unknown' }}
          </span>
          <code class="min-w-0 flex-1 truncate font-mono text-sm font-medium">{{ entry.command }}</code>
          <Badge
            v-if="isTerminalStatus(entry.status)"
            variant="outline"
            :class="cn(
              'shrink-0 border-transparent',
              entry.exitCode === 0 ? 'badge-op-primary' : 'badge-op-destructive',
            )"
          >
            exit {{ entry.exitCode ?? '—' }}
          </Badge>
        </button>
        <pre
          v-if="expandedId === entry.id"
          class="log-panel max-h-64 overflow-auto whitespace-pre-wrap border-t border-zinc-800 p-4"
        >{{ entry.output ?? 'No output captured yet.' }}</pre>
        </div>
      </template>
    </div>

    <div class="log-panel-input sticky bottom-0 p-4">
      <div class="mb-2 flex items-center gap-2 text-sm text-muted-foreground">
        <label for="command-timeout">Timeout (seconds)</label>
        <Input
          id="command-timeout"
          v-model.number="commandTimeout"
          type="number"
          min="5"
          max="300"
          class="w-24"
        />
      </div>
      <div class="flex gap-2">
        <Input
          v-model="commandInput"
          class="border-zinc-700 bg-zinc-900 font-mono text-zinc-100 placeholder:text-zinc-500 focus-visible:ring-ring"
          placeholder="Enter command…"
          data-testid="command-input"
          @keydown="handleInputKeydown"
        />
        <Button
          type="button"
          :disabled="!canRunCommand"
          data-testid="command-run-button"
          @click="requestRun"
        >
          {{ isRunning ? 'Running…' : 'Run' }}
        </Button>
      </div>
    </div>

    <ConfirmCommandDialog
      v-model:open="isConfirmOpen"
      :command="pendingCommand"
      :description="confirmReason"
      @confirm="handleConfirmRun"
    />
  </div>
</template>
