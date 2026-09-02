<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { PlusIcon, Trash2Icon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { fetchServers, updateServer } from '@/features/servers/api'
import type { Server } from '@/types'

interface Props {
  projectId: string
  canManage: boolean
}

const props = defineProps<Props>()

const { orgId } = useActiveOrg()

const projectServers = ref<Server[]>([])
const orgServers = ref<Server[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const isAttachOpen = ref(false)
const isAttaching = ref(false)
const selectedAttachServerId = ref<string | undefined>(undefined)
const detachTarget = ref<Server | null>(null)
const isDetaching = ref(false)

const attachableServers = computed(() => {
  return orgServers.value.filter((server) => server.project?.id !== props.projectId)
})

const canAttach = computed(() => attachableServers.value.length > 0)

async function loadServers(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isLoading.value = true
  loadError.value = null

  try {
    projectServers.value = await fetchServers(activeOrgId, { projectId: props.projectId })
  } catch {
    projectServers.value = []
    loadError.value = 'Unable to load servers for this project.'
  } finally {
    isLoading.value = false
  }
}

async function loadOrgServersForAttach(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  try {
    orgServers.value = await fetchServers(activeOrgId)
  } catch {
    orgServers.value = []
    toast.error('Unable to load organization servers.')
  }
}

async function openAttachSheet(): Promise<void> {
  selectedAttachServerId.value = undefined
  isAttachOpen.value = true
  await loadOrgServersForAttach()
}

async function attachServer(): Promise<void> {
  if (selectedAttachServerId.value === undefined) {
    return
  }

  isAttaching.value = true

  try {
    await updateServer(selectedAttachServerId.value, { projectId: props.projectId })
    isAttachOpen.value = false
    await loadServers()
    toast.success('Server assigned to this project.')
  } catch {
    toast.error('Unable to assign server to this project.')
  } finally {
    isAttaching.value = false
  }
}

async function confirmDetach(): Promise<void> {
  if (detachTarget.value === null) {
    return
  }

  isDetaching.value = true

  try {
    await updateServer(detachTarget.value.id, { projectId: null })
    detachTarget.value = null
    await loadServers()
    toast.success('Server removed from this project.')
  } catch {
    toast.error('Unable to remove server from this project.')
  } finally {
    isDetaching.value = false
  }
}

onMounted(() => {
  void loadServers()
})

watch(
  () => props.projectId,
  () => {
    void loadServers()
  },
)
</script>

<template>
  <section class="space-y-4" data-testid="project-servers-section">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold tracking-tight">
          Servers
        </h2>
        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
          Servers assigned to this project are visible to team members scoped to it.
        </p>
      </div>
      <Button
        v-if="canManage && canAttach"
        type="button"
        size="sm"
        data-testid="attach-server-button"
        @click="openAttachSheet"
      >
        <PlusIcon class="mr-2 size-4" aria-hidden="true" />
        Assign server
      </Button>
    </div>

    <div
      v-if="isLoading"
      class="space-y-3"
      data-testid="project-servers-loading"
    >
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel p-6 text-sm text-muted-foreground"
      data-testid="project-servers-error"
    >
      {{ loadError }}
      <Button type="button" variant="link" class="px-0" @click="loadServers">
        Retry
      </Button>
    </div>

    <div
      v-else-if="projectServers.length === 0"
      class="panel p-6 text-sm text-muted-foreground"
      data-testid="project-servers-empty"
    >
      <template v-if="canManage">
        No servers assigned yet.
        <Button
          v-if="canAttach"
          type="button"
          variant="link"
          class="px-1"
          @click="openAttachSheet"
        >
          Assign an existing server
        </Button>
        or
        <RouterLink to="/servers" class="text-primary hover:underline">
          add a new one
        </RouterLink>
        with this project selected.
      </template>
      <template v-else>
        No servers are assigned to this project.
      </template>
    </div>

    <div
      v-else
      class="panel overflow-hidden"
      data-testid="project-servers-table"
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Hostname</TableHead>
            <TableHead class="hidden sm:table-cell">
              IP address
            </TableHead>
            <TableHead>Status</TableHead>
            <TableHead v-if="canManage" class="text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="server in projectServers"
            :key="server.id"
          >
            <TableCell class="font-medium">
              <RouterLink
                :to="`/servers/${server.id}`"
                class="text-foreground hover:text-primary"
              >
                {{ server.hostname }}
              </RouterLink>
            </TableCell>
            <TableCell class="hidden font-mono text-sm text-muted-foreground sm:table-cell">
              {{ server.ipAddress }}
            </TableCell>
            <TableCell>
              <StatusBadge :status="server.status" type="server" />
            </TableCell>
            <TableCell v-if="canManage" class="text-right">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                @click="detachTarget = server"
              >
                <Trash2Icon class="mr-1 size-4" aria-hidden="true" />
                Remove
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isAttachOpen">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Assign server</SheetTitle>
          <SheetDescription>
            Choose an organization server to assign to this project. Servers already on another project can be moved here.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-4">
          <div
            v-if="attachableServers.length === 0"
            class="text-sm text-muted-foreground"
          >
            Every organization server is already assigned to this project.
          </div>
          <div
            v-else
            class="space-y-2"
          >
            <label
              v-for="server in attachableServers"
              :key="server.id"
              class="flex cursor-pointer items-start gap-3 rounded-md border border-transparent px-2 py-2 transition-colors hover:bg-muted/40"
            >
              <input
                v-model="selectedAttachServerId"
                type="radio"
                class="mt-1 size-4"
                :value="server.id"
              >
              <span>
                <span class="block text-sm font-medium">{{ server.hostname }}</span>
                <span class="block font-mono text-xs text-muted-foreground">{{ server.ipAddress }}</span>
                <span
                  v-if="server.project !== null"
                  class="block text-xs text-muted-foreground"
                >
                  Currently on {{ server.project.name }}
                </span>
              </span>
            </label>
          </div>
        </SheetBody>

        <SheetFooter>
          <Button
            type="button"
            :disabled="isAttaching || selectedAttachServerId === undefined"
            @click="attachServer"
          >
            {{ isAttaching ? 'Assigning…' : 'Assign server' }}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <ConfirmDestructiveDialog
      v-if="detachTarget !== null"
      :open="detachTarget !== null"
      title="Remove server from project"
      :description="`${detachTarget.hostname} will stay in the organization but no longer belong to this project. Team members scoped to this project will lose access.`"
      :confirm-text="detachTarget.hostname"
      confirm-button-label="Remove from project"
      :can-confirm="!isDetaching"
      @update:open="(value) => { if (!value) detachTarget = null }"
      @confirm="confirmDetach"
    />
  </section>
</template>
