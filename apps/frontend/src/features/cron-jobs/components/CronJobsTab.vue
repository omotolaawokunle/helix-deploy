<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ClockIcon } from '@lucide/vue'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
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
  createCronJob,
  describeCronExpression,
  fetchCronJobs,
  toggleCronJob,
  updateCronJob,
} from '@/features/cron-jobs/api'
import type { CronJobRecord } from '@/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

const cronJobs = ref<CronJobRecord[]>([])
const isLoading = ref(true)
const isCreateOpen = ref(false)

const createExpression = ref('* * * * *')
const createDescription = ref('')
const createCommand = ref('')
const createUser = ref('www-data')

const editingRows = ref<Record<string, { expression: string; command: string }>>({})

async function loadCronJobs(): Promise<void> {
  isLoading.value = true

  try {
    cronJobs.value = await fetchCronJobs(props.serverId)
    editingRows.value = Object.fromEntries(
      cronJobs.value.map(job => [job.id, { expression: job.expression, command: job.command }]),
    )
  } catch {
    toast.error('Unable to load cron jobs.')
  } finally {
    isLoading.value = false
  }
}

const refreshDescription = useDebounceFn(async (expression: string, target: 'create'): Promise<void> => {
  if (expression.trim() === '') {
    if (target === 'create') {
      createDescription.value = ''
    }

    return
  }

  try {
    const description = await describeCronExpression(expression)

    if (target === 'create') {
      createDescription.value = description
    }
  } catch {
    if (target === 'create') {
      createDescription.value = 'Invalid cron expression'
    }
  }
}, 300)

watch(createExpression, (value) => {
  void refreshDescription(value, 'create')
}, { immediate: true })

async function handleCreate(): Promise<void> {
  try {
    await createCronJob(props.serverId, {
      expression: createExpression.value,
      command: createCommand.value,
      user: createUser.value,
      active: true,
    })
    isCreateOpen.value = false
    createCommand.value = ''
    await loadCronJobs()
    toast.success('Cron job created.')
  } catch {
    toast.error('Unable to create cron job.')
  }
}

async function handleToggle(job: CronJobRecord): Promise<void> {
  try {
    await toggleCronJob(props.serverId, job.id)
    await loadCronJobs()
  } catch {
    toast.error('Unable to toggle cron job.')
  }
}

async function saveInline(jobId: string): Promise<void> {
  const row = editingRows.value[jobId]

  if (row === undefined) {
    return
  }

  try {
    await updateCronJob(props.serverId, jobId, {
      expression: row.expression,
      command: row.command,
    })
    await loadCronJobs()
    toast.success('Cron job updated.')
  } catch {
    toast.error('Unable to update cron job.')
  }
}

const isEmpty = computed(() => !isLoading.value && cronJobs.value.length === 0)

void loadCronJobs()
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button type="button" @click="isCreateOpen = true">
        Add cron job
      </Button>
    </div>

    <EmptyState
      v-if="isEmpty"
      title="No cron jobs"
      description="Schedule recurring commands on this server."
      :icon="ClockIcon"
      @action="isCreateOpen = true"
    >
      Add cron job
    </EmptyState>

    <div v-else class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Expression</TableHead>
            <TableHead>Description</TableHead>
            <TableHead>Command</TableHead>
            <TableHead>User</TableHead>
            <TableHead>Status</TableHead>
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
          <TableRow v-for="job in cronJobs" :key="job.id">
            <TableCell>
              <Input
                v-model="editingRows[job.id].expression"
                class="font-mono text-xs"
                data-testid="cron-expression-input"
              />
            </TableCell>
            <TableCell class="text-sm text-muted-foreground">
              {{ job.description }}
            </TableCell>
            <TableCell>
              <Input v-model="editingRows[job.id].command" class="font-mono text-xs" />
            </TableCell>
            <TableCell>{{ job.user }}</TableCell>
            <TableCell>
              <Button
                type="button"
                size="sm"
                :variant="job.active ? 'default' : 'outline'"
                @click="handleToggle(job)"
              >
                {{ job.active ? 'Active' : 'Inactive' }}
              </Button>
            </TableCell>
            <TableCell class="text-right">
              <Button type="button" size="sm" variant="ghost" @click="saveInline(job.id)">
                Save
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isCreateOpen">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-md">
        <SheetHeader>
          <SheetTitle>New cron job</SheetTitle>
          <SheetDescription>
            Schedule a command on this server.
          </SheetDescription>
        </SheetHeader>
        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label for="cron-expression">Expression</Label>
            <Input
              id="cron-expression"
              v-model="createExpression"
              class="font-mono"
              data-testid="cron-create-expression-input"
            />
            <p class="text-sm text-muted-foreground" data-testid="cron-expression-description">
              {{ createDescription }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="cron-command">Command</Label>
            <Input id="cron-command" v-model="createCommand" class="font-mono" />
          </div>
          <div class="space-y-2">
            <Label>Unix user</Label>
            <Select v-model="createUser">
              <SelectTrigger>
                <SelectValue placeholder="Select user" />
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
  </div>
</template>
