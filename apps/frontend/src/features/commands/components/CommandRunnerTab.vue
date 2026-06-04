<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import ConfirmCommandDialog from '@/components/common/ConfirmCommandDialog.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  fetchCommands,
  isCommandConfirmationRequired,
  runCommand,
} from '@/features/commands/api'
import { formatRelativeTime } from '@/lib/format'
import type { CommandRecord } from '@/types'

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
const isRunning = ref(false)
const expandedId = ref<string | null>(null)

const isConfirmOpen = ref(false)
const confirmReason = ref('')
const pendingCommand = ref('')

const warnPattern = /^(rm\s+-rf|reboot|shutdown|halt|poweroff|mkfs|dd\s+)/i

const needsClientConfirmation = computed(
  () => props.isProduction || warnPattern.test(commandInput.value.trim()),
)

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

  try {
    const result = await runCommand(props.serverId, { command, confirmed })

    if (isCommandConfirmationRequired(result)) {
      pendingCommand.value = command
      confirmReason.value = result.reason
      isConfirmOpen.value = true

      return
    }

    commandInput.value = ''
    await loadCommands()
    toast.success('Command queued for execution.')
  } catch (error: unknown) {
    const message = typeof error === 'object'
      && error !== null
      && 'response' in error
      && typeof (error as { response?: { data?: { message?: string } } }).response?.data?.message === 'string'
      ? (error as { response: { data: { message: string } } }).response.data.message
      : 'Unable to run command.'

    toast.error(message)
  } finally {
    isRunning.value = false
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

onMounted(() => {
  void loadCommands()
})
</script>

<template>
  <div class="flex min-h-[32rem] flex-col gap-4">
    <ProductionWarningBanner
      :resource-name="serverId"
      :is-production="isProduction"
      variant="inline"
    />

    <div class="flex-1 space-y-3 overflow-y-auto rounded-lg border bg-muted/20 p-4">
      <div v-if="nextCursor !== null" class="text-center">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="isLoadingMore"
          @click="loadCommands(true)"
        >
          Load more
        </Button>
      </div>

      <p v-if="isLoading" class="text-sm text-muted-foreground">
        Loading command history…
      </p>

      <div
        v-for="entry in commands"
        :key="entry.id"
        class="rounded-lg border bg-background p-3"
      >
        <button
          type="button"
          class="flex w-full flex-wrap items-center gap-2 text-left"
          @click="toggleExpanded(entry.id)"
        >
          <Badge variant="outline">
            {{ entry.executedAt !== null ? formatRelativeTime(entry.executedAt) : 'Queued' }}
          </Badge>
          <span class="text-sm text-muted-foreground">
            {{ entry.user?.name ?? 'Unknown' }}
          </span>
          <code class="flex-1 font-mono text-sm font-semibold">{{ entry.command }}</code>
          <Badge :variant="entry.exitCode === 0 ? 'default' : 'destructive'">
            exit {{ entry.exitCode ?? '—' }}
          </Badge>
        </button>
        <pre
          v-if="expandedId === entry.id"
          class="log-panel mt-3 max-h-64 overflow-auto whitespace-pre-wrap p-3"
        >{{ entry.output ?? 'No output captured yet.' }}</pre>
      </div>
    </div>

    <div class="log-panel-input sticky bottom-0 p-4">
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
          :disabled="isRunning"
          data-testid="command-run-button"
          @click="requestRun"
        >
          Run
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
