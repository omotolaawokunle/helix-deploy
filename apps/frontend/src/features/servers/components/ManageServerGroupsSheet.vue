<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Loader2Icon, Trash2Icon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
import { Textarea } from '@/components/ui/textarea'
import { useActiveOrg } from '@/composables/useActiveOrg'
import {
  createServerGroup,
  deleteServerGroup,
  fetchServerGroup,
  fetchServerGroups,
  fetchServers,
  syncServerGroupServers,
  updateServerGroup,
} from '@/features/servers/api'
import type { Server, ServerGroup } from '@/types'

interface Props {
  open: boolean
}

interface Emits {
  (event: 'update:open', value: boolean): void
  (event: 'groups-changed'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { orgId } = useActiveOrg()

const groups = ref<ServerGroup[]>([])
const allServers = ref<Server[]>([])
const isLoading = ref(false)
const selectedGroupId = ref<string | null>(null)
const selectedServerIds = ref<string[]>([])
const isSavingServers = ref(false)
const isCreating = ref(false)
const isDeleting = ref(false)
const deleteTarget = ref<ServerGroup | null>(null)
const isDeleteOpen = ref(false)

const newName = ref('')
const newDescription = ref('')
const editName = ref('')
const editDescription = ref('')
const isSavingGroup = ref(false)

const selectedGroup = computed(
  (): ServerGroup | null => groups.value.find(group => group.id === selectedGroupId.value) ?? null,
)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      void load()
    }
  },
)

async function load(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isLoading.value = true

  try {
    const [groupList, serverList] = await Promise.all([
      fetchServerGroups(activeOrgId),
      fetchServers(activeOrgId),
    ])

    groups.value = groupList
    allServers.value = serverList
  } catch {
    toast.error('Unable to load server groups.')
  } finally {
    isLoading.value = false
  }
}

async function selectGroup(groupId: string): Promise<void> {
  selectedGroupId.value = groupId

  try {
    const group = await fetchServerGroup(groupId)
    editName.value = group.name
    editDescription.value = group.description ?? ''
    selectedServerIds.value = group.serverIds ?? []
  } catch {
    toast.error('Unable to load group details.')
  }
}

async function createGroup(): Promise<void> {
  const activeOrgId = orgId.value
  const name = newName.value.trim()

  if (activeOrgId === null || name === '') {
    return
  }

  isCreating.value = true

  try {
    const created = await createServerGroup(activeOrgId, {
      name,
      description: newDescription.value.trim() || null,
    })

    groups.value = [...groups.value, created]
    newName.value = ''
    newDescription.value = ''
    emit('groups-changed')
    toast.success('Server group created.')
    await selectGroup(created.id)
  } catch {
    toast.error('Unable to create server group.')
  } finally {
    isCreating.value = false
  }
}

async function saveGroupDetails(): Promise<void> {
  if (selectedGroupId.value === null) {
    return
  }

  isSavingGroup.value = true

  try {
    const updated = await updateServerGroup(selectedGroupId.value, {
      name: editName.value.trim(),
      description: editDescription.value.trim() || null,
    })

    groups.value = groups.value.map(group =>
      group.id === updated.id ? { ...group, ...updated } : group,
    )
    emit('groups-changed')
    toast.success('Server group updated.')
  } catch {
    toast.error('Unable to update server group.')
  } finally {
    isSavingGroup.value = false
  }
}

async function saveGroupServers(): Promise<void> {
  if (selectedGroupId.value === null) {
    return
  }

  isSavingServers.value = true

  try {
    const updated = await syncServerGroupServers(selectedGroupId.value, selectedServerIds.value)

    groups.value = groups.value.map(group =>
      group.id === updated.id ? { ...group, ...updated } : group,
    )
    emit('groups-changed')
    toast.success('Group servers updated.')
  } catch {
    toast.error('Unable to update group servers.')
  } finally {
    isSavingServers.value = false
  }
}

function toggleServer(serverId: string, checked: boolean): void {
  if (checked) {
    selectedServerIds.value = [...selectedServerIds.value, serverId]

    return
  }

  selectedServerIds.value = selectedServerIds.value.filter(id => id !== serverId)
}

function requestDelete(group: ServerGroup): void {
  deleteTarget.value = group
  isDeleteOpen.value = true
}

async function confirmDelete(): Promise<void> {
  if (deleteTarget.value === null) {
    return
  }

  isDeleting.value = true

  try {
    await deleteServerGroup(deleteTarget.value.id)
    groups.value = groups.value.filter(group => group.id !== deleteTarget.value?.id)

    if (selectedGroupId.value === deleteTarget.value.id) {
      selectedGroupId.value = null
      selectedServerIds.value = []
    }

    emit('groups-changed')
    toast.success('Server group deleted.')
  } catch {
    toast.error('Unable to delete server group.')
  } finally {
    isDeleting.value = false
    isDeleteOpen.value = false
    deleteTarget.value = null
  }
}

function handleOpenChange(value: boolean): void {
  emit('update:open', value)
}
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent class="flex w-full flex-col sm:max-w-lg" side="right">
      <SheetHeader>
        <SheetTitle>Server groups</SheetTitle>
        <SheetDescription>
          Organize servers into groups and filter the servers list by group.
        </SheetDescription>
      </SheetHeader>

      <SheetBody class="flex-1 space-y-6 overflow-y-auto">
        <form class="space-y-3 rounded-lg border p-4" @submit.prevent="createGroup">
          <h3 class="text-sm font-medium">
            New group
          </h3>
          <div class="space-y-2">
            <Label for="new-group-name">Name</Label>
            <Input
              id="new-group-name"
              v-model="newName"
              placeholder="Production pool"
              required
            />
          </div>
          <div class="space-y-2">
            <Label for="new-group-description">Description (optional)</Label>
            <Textarea
              id="new-group-description"
              v-model="newDescription"
              rows="2"
              placeholder="Primary application hosts"
            />
          </div>
          <Button type="submit" size="sm" :disabled="isCreating || newName.trim() === ''">
            <Loader2Icon v-if="isCreating" class="mr-2 size-4 animate-spin" />
            Create group
          </Button>
        </form>

        <div v-if="isLoading" class="space-y-2">
          <Skeleton v-for="index in 3" :key="index" class="h-12 rounded-lg" />
        </div>

        <div v-else-if="groups.length === 0" class="panel border-dashed p-6 text-center text-sm text-muted-foreground">
          No server groups yet. Create one above.
        </div>

        <div v-else class="space-y-2">
          <button
            v-for="group in groups"
            :key="group.id"
            type="button"
            class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left transition-colors hover:bg-muted/50"
            :class="selectedGroupId === group.id ? 'border-primary bg-muted/30' : ''"
            @click="selectGroup(group.id)"
          >
            <div>
              <p class="font-medium">
                {{ group.name }}
              </p>
              <p v-if="group.description" class="text-xs text-muted-foreground">
                {{ group.description }}
              </p>
            </div>
            <span class="text-xs text-muted-foreground">
              {{ group.serversCount ?? 0 }} servers
            </span>
          </button>
        </div>

        <div v-if="selectedGroup !== null" class="space-y-4 rounded-lg border p-4">
          <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-medium">
              Edit {{ selectedGroup.name }}
            </h3>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="size-8 text-destructive"
              @click="requestDelete(selectedGroup)"
            >
              <Trash2Icon class="size-4" />
            </Button>
          </div>

          <div class="space-y-2">
            <Label :for="`edit-name-${selectedGroup.id}`">Name</Label>
            <Input :id="`edit-name-${selectedGroup.id}`" v-model="editName" />
          </div>
          <div class="space-y-2">
            <Label :for="`edit-desc-${selectedGroup.id}`">Description</Label>
            <Textarea :id="`edit-desc-${selectedGroup.id}`" v-model="editDescription" rows="2" />
          </div>
          <Button
            type="button"
            size="sm"
            variant="outline"
            :disabled="isSavingGroup"
            @click="saveGroupDetails"
          >
            <Loader2Icon v-if="isSavingGroup" class="mr-2 size-4 animate-spin" />
            Save details
          </Button>

          <div class="space-y-2 border-t pt-4">
            <p class="text-sm font-medium">
              Servers in group
            </p>
            <div
              v-if="allServers.length === 0"
              class="text-sm text-muted-foreground"
            >
              No servers available.
            </div>
            <label
              v-for="server in allServers"
              :key="server.id"
              class="flex items-center gap-2 text-sm"
            >
              <input
                type="checkbox"
                class="rounded border-input"
                :checked="selectedServerIds.includes(server.id)"
                @change="toggleServer(server.id, ($event.target as HTMLInputElement).checked)"
              >
              <span>{{ server.hostname }}</span>
            </label>
            <Button
              type="button"
              size="sm"
              :disabled="isSavingServers"
              @click="saveGroupServers"
            >
              <Loader2Icon v-if="isSavingServers" class="mr-2 size-4 animate-spin" />
              Save servers
            </Button>
          </div>
        </div>
      </SheetBody>

      <SheetFooter>
        <Button type="button" variant="outline" @click="handleOpenChange(false)">
          Close
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>

  <ConfirmDestructiveDialog
    v-if="deleteTarget !== null"
    v-model:open="isDeleteOpen"
    title="Delete server group"
    :description="`Delete “${deleteTarget.name}”? Servers are not removed — only the group assignment.`"
    :confirm-text="deleteTarget.name"
    confirm-button-label="Delete group"
    :can-confirm="!isDeleting"
    @confirm="confirmDelete"
  />
</template>
