<script setup lang="ts">
import { computed, ref, toRef } from 'vue'
import { ActivityIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { useDaemonChannel } from '@/composables/useDaemonChannel'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  createDaemon,
  deleteDaemon,
  fetchDaemonLogs,
  fetchDaemons,
  restartDaemon,
  startDaemon,
  stopDaemon,
} from '@/features/daemons/api'
import type { DaemonChangedPayload, DaemonLogsReadyPayload } from '@/features/daemons/types'
import type { DaemonRecord } from '@/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

const serverId = toRef(props, 'serverId')

const daemons = ref<DaemonRecord[]>([])
const isLoading = ref(true)
const isCreateOpen = ref(false)

const createName = ref('')
const createCommand = ref('')
const createDirectory = ref('/var/www')
const createUser = ref('www-data')
const createProcesses = ref(1)

const logsDaemon = ref<DaemonRecord | null>(null)
const logLines = ref<string[]>([])
const isLogsOpen = ref(false)
const isLogsLoading = ref(false)
const awaitingLogsDaemonId = ref<string | null>(null)
const pendingDaemonIds = ref<string[]>([])

function isDaemonPending(daemonId: string): boolean {
  return pendingDaemonIds.value.includes(daemonId)
}

function markDaemonPending(daemonId: string): void {
  if (pendingDaemonIds.value.includes(daemonId)) {
    return
  }

  pendingDaemonIds.value = [...pendingDaemonIds.value, daemonId]
}

function clearDaemonPending(daemonId: string): void {
  pendingDaemonIds.value = pendingDaemonIds.value.filter(id => id !== daemonId)
}

function truncateCommand(command: string): string {
  if (command.length <= 60) {
    return command
  }

  return `${command.slice(0, 57)}…`
}

function mapDaemonSnapshot(
  snapshot: DaemonChangedPayload['daemon'],
): DaemonRecord | null {
  if (snapshot === null) {
    return null
  }

  return {
    id: snapshot.id,
    serverId: snapshot.serverId,
    organizationId: snapshot.organizationId,
    name: snapshot.name,
    command: snapshot.command,
    directory: snapshot.directory,
    user: snapshot.user,
    processes: snapshot.processes,
    status: snapshot.status,
    createdAt: snapshot.createdAt ?? '',
    updatedAt: snapshot.updatedAt ?? '',
  }
}

function applyDaemonChanged(payload: DaemonChangedPayload): void {
  clearDaemonPending(payload.daemonId)

  if (payload.action === 'deleted') {
    daemons.value = daemons.value.filter(daemon => daemon.id !== payload.daemonId)

    return
  }

  const mapped = mapDaemonSnapshot(payload.daemon)

  if (mapped === null) {
    return
  }

  const index = daemons.value.findIndex(daemon => daemon.id === mapped.id)

  if (index === -1) {
    daemons.value = [...daemons.value, mapped]

    return
  }

  daemons.value[index] = mapped
}

function handleLogsReady(payload: DaemonLogsReadyPayload): void {
  if (awaitingLogsDaemonId.value !== payload.daemonId) {
    return
  }

  logLines.value = payload.lines
  isLogsLoading.value = false
  awaitingLogsDaemonId.value = null

  if (payload.status === 'failed' && payload.message !== undefined && payload.message !== null) {
    toast.error(payload.message)
  }
}

useDaemonChannel(serverId, {
  onDaemonChanged: applyDaemonChanged,
  onLogsReady: handleLogsReady,
})

async function loadDaemons(): Promise<void> {
  try {
    daemons.value = await fetchDaemons(props.serverId)
  } catch {
    toast.error('Unable to load daemons.')
  } finally {
    isLoading.value = false
  }
}

const isEmpty = computed(() => !isLoading.value && daemons.value.length === 0)

async function handleCreate(): Promise<void> {
  try {
    await createDaemon(props.serverId, {
      name: createName.value,
      command: createCommand.value,
      directory: createDirectory.value,
      user: createUser.value,
      processes: createProcesses.value,
    })
    isCreateOpen.value = false
    createName.value = ''
    createCommand.value = ''
    toast.success('Daemon creation queued.', {
      description: 'The list will update when Supervisor finishes provisioning.',
    })
  } catch {
    toast.error('Unable to create daemon.')
  }
}

async function runAction(
  action: 'start' | 'stop' | 'restart' | 'delete',
  daemon: DaemonRecord,
): Promise<void> {
  markDaemonPending(daemon.id)

  try {
    if (action === 'start') {
      await startDaemon(props.serverId, daemon.id)
    } else if (action === 'stop') {
      await stopDaemon(props.serverId, daemon.id)
    } else if (action === 'restart') {
      await restartDaemon(props.serverId, daemon.id)
    } else {
      await deleteDaemon(props.serverId, daemon.id)
    }

    toast.success(`Daemon ${action} queued.`, {
      description: 'Status will update when the operation completes on the server.',
    })
  } catch {
    clearDaemonPending(daemon.id)
    toast.error(`Unable to ${action} daemon.`)
  }
}

async function openLogs(daemon: DaemonRecord): Promise<void> {
  logsDaemon.value = daemon
  logLines.value = []
  isLogsOpen.value = true
  isLogsLoading.value = true
  awaitingLogsDaemonId.value = daemon.id

  try {
    const response = await fetchDaemonLogs(props.serverId, daemon.id)

    if (response.status === 'ready' || response.status === 'failed') {
      logLines.value = response.lines
      isLogsLoading.value = false
      awaitingLogsDaemonId.value = null

      if (response.status === 'failed' && response.message !== undefined && response.message !== null) {
        toast.error(response.message)
      }
    }
  } catch {
    isLogsLoading.value = false
    awaitingLogsDaemonId.value = null
    toast.error('Unable to load daemon logs.')
  }
}

function closeLogs(): void {
  isLogsOpen.value = false
  awaitingLogsDaemonId.value = null
  isLogsLoading.value = false
}

void loadDaemons()
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button type="button" @click="isCreateOpen = true">
        Add daemon
      </Button>
    </div>

    <EmptyState
      v-if="isEmpty"
      title="No daemons"
      description="Run supervised background processes with Supervisor."
      :icon="ActivityIcon"
      @action="isCreateOpen = true"
    >
      Add daemon
    </EmptyState>

    <div v-else class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Command</TableHead>
            <TableHead>Processes</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>User</TableHead>
            <TableHead class="text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="6" class="text-muted-foreground">
              Loading…
            </TableCell>
          </TableRow>
          <TableRow
            v-for="daemon in daemons"
            :key="daemon.id"
            :class="isDaemonPending(daemon.id) ? 'bg-muted/30' : undefined"
          >
            <TableCell class="font-medium">
              <span class="inline-flex items-center gap-2">
                {{ daemon.name }}
                <span
                  v-if="isDaemonPending(daemon.id)"
                  class="inline-flex size-1.5 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
                  aria-hidden="true"
                />
              </span>
            </TableCell>
            <TableCell class="font-mono text-xs text-muted-foreground">
              {{ truncateCommand(daemon.command) }}
            </TableCell>
            <TableCell>{{ daemon.processes }}</TableCell>
            <TableCell>
              <StatusBadge :status="daemon.status" type="daemon" />
            </TableCell>
            <TableCell>{{ daemon.user }}</TableCell>
            <TableCell>
              <div class="flex justify-end gap-1">
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  :disabled="isDaemonPending(daemon.id)"
                  @click="runAction('start', daemon)"
                >
                  Start
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  :disabled="isDaemonPending(daemon.id)"
                  @click="runAction('stop', daemon)"
                >
                  Stop
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  :disabled="isDaemonPending(daemon.id)"
                  @click="runAction('restart', daemon)"
                >
                  Restart
                </Button>
                <Button type="button" size="sm" variant="ghost" @click="openLogs(daemon)">
                  Logs
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  :disabled="isDaemonPending(daemon.id)"
                  @click="runAction('delete', daemon)"
                >
                  Delete
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isCreateOpen">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-md">
        <SheetHeader>
          <SheetTitle>New daemon</SheetTitle>
          <SheetDescription>
            Create a supervised process on this server.
          </SheetDescription>
        </SheetHeader>
        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label for="daemon-name">Name</Label>
            <Input id="daemon-name" v-model="createName" />
          </div>
          <div class="space-y-2">
            <Label for="daemon-command">Command</Label>
            <Input id="daemon-command" v-model="createCommand" class="font-mono" />
          </div>
          <div class="space-y-2">
            <Label for="daemon-directory">Directory</Label>
            <Input id="daemon-directory" v-model="createDirectory" />
          </div>
          <div class="space-y-2">
            <Label>User</Label>
            <Select v-model="createUser">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="www-data">
                  www-data
                </SelectItem>
                <SelectItem value="deploy">
                  deploy
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="daemon-processes">Processes ({{ createProcesses }})</Label>
            <input
              id="daemon-processes"
              v-model.number="createProcesses"
              type="range"
              min="1"
              max="10"
              class="w-full"
            >
          </div>
        </SheetBody>
        <SheetFooter>
          <Button type="button" variant="outline" @click="isCreateOpen = false">
            Cancel
          </Button>
          <Button type="button" @click="handleCreate">
            Create
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <Sheet v-model:open="isLogsOpen" @update:open="(open) => !open && closeLogs()">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-2xl">
        <SheetHeader>
          <SheetTitle>{{ logsDaemon?.name }} logs</SheetTitle>
          <SheetDescription>
            Last 50 lines from the supervisor log file.
          </SheetDescription>
        </SheetHeader>
        <SheetBody class="px-0 py-0">
          <div
            v-if="isLogsLoading"
            class="log-panel max-h-[70vh] space-y-3 overflow-auto p-4"
            role="status"
            aria-live="polite"
            data-testid="daemon-logs-loading"
          >
            <div class="flex items-center gap-2 text-zinc-400">
              <span
                class="inline-flex size-1.5 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
                aria-hidden="true"
              />
              Fetching logs from supervisor…
            </div>
            <div class="space-y-2" aria-hidden="true">
              <div class="h-3 w-full animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none" />
              <div class="h-3 w-10/12 animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none" />
              <div class="h-3 w-4/5 animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none" />
              <div class="h-3 w-full animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none" />
            </div>
          </div>
          <pre
            v-else
            class="log-panel max-h-[70vh] overflow-auto whitespace-pre-wrap p-4"
          >{{ logLines.join('\n') || 'No log lines yet.' }}</pre>
        </SheetBody>
      </SheetContent>
    </Sheet>
  </div>
</template>
