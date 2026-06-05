<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { ActivityIcon } from '@lucide/vue'
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { useReducedMotionPolling } from '@/composables/useReducedMotionPolling'
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
import type { DaemonRecord } from '@/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

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

const documentVisibility = useDocumentVisibility()

const { pause: pauseLogsPolling, resume: resumeLogsPolling } = useIntervalFn(
  () => {
    void pollLogs()
  },
  2000,
  { immediate: false },
)

function truncateCommand(command: string): string {
  if (command.length <= 60) {
    return command
  }

  return `${command.slice(0, 57)}…`
}

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

useReducedMotionPolling(loadDaemons, 15_000)

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
    await loadDaemons()
    toast.success('Daemon creation queued.')
  } catch {
    toast.error('Unable to create daemon.')
  }
}

async function runAction(
  action: 'start' | 'stop' | 'restart' | 'delete',
  daemon: DaemonRecord,
): Promise<void> {
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

    await loadDaemons()
    toast.success(`Daemon ${action} queued.`)
  } catch {
    toast.error(`Unable to ${action} daemon.`)
  }
}

function stopLogsPolling(): void {
  pauseLogsPolling()
}

async function pollLogs(): Promise<void> {
  if (logsDaemon.value === null) {
    return
  }

  const response = await fetchDaemonLogs(props.serverId, logsDaemon.value.id)
  logLines.value = response.lines

  if (response.status === 'ready' || response.status === 'failed') {
    stopLogsPolling()
    isLogsLoading.value = false
  }
}

async function openLogs(daemon: DaemonRecord): Promise<void> {
  logsDaemon.value = daemon
  logLines.value = []
  isLogsOpen.value = true
  isLogsLoading.value = true

  stopLogsPolling()
  await pollLogs()

  if (isLogsLoading.value) {
    resumeLogsPolling()
  }
}

function closeLogs(): void {
  isLogsOpen.value = false
  stopLogsPolling()
}

watch(
  [isLogsOpen, documentVisibility],
  ([open, visibility]) => {
    if (!open || visibility !== 'visible' || !isLogsLoading.value) {
      pauseLogsPolling()
      return
    }

    resumeLogsPolling()
  },
)

void loadDaemons()

onUnmounted(() => {
  stopLogsPolling()
})
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
          <TableRow v-for="daemon in daemons" :key="daemon.id">
            <TableCell class="font-medium">
              {{ daemon.name }}
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
                <Button type="button" size="sm" variant="ghost" @click="runAction('start', daemon)">
                  Start
                </Button>
                <Button type="button" size="sm" variant="ghost" @click="runAction('stop', daemon)">
                  Stop
                </Button>
                <Button type="button" size="sm" variant="ghost" @click="runAction('restart', daemon)">
                  Restart
                </Button>
                <Button type="button" size="sm" variant="ghost" @click="openLogs(daemon)">
                  Logs
                </Button>
                <Button type="button" size="sm" variant="ghost" @click="runAction('delete', daemon)">
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
          <pre class="log-panel max-h-[70vh] overflow-auto whitespace-pre-wrap p-4">{{ isLogsLoading ? 'Loading logs…' : logLines.join('\n') || 'No log lines yet.' }}</pre>
        </SheetBody>
      </SheetContent>
    </Sheet>
  </div>
</template>
