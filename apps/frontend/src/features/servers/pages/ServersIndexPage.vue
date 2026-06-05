<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, toRef, watch } from 'vue'
import { FolderIcon, PlusIcon, ServerIcon, XIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchServerGroups } from '@/features/servers/api'
import ServerCard from '@/features/servers/components/ServerCard.vue'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import type { Server, ServerGroup } from '@/types'

const AddServerModal = defineAsyncComponent(
  () => import('@/features/servers/components/AddServerModal.vue'),
)

const ManageServerGroupsSheet = defineAsyncComponent(
  () => import('@/features/servers/components/ManageServerGroupsSheet.vue'),
)

const serversStore = useServersStore()
const authStore = useAuthStore()
const { orgId } = useActiveOrg()
const isAddModalOpen = ref(false)
const isGroupsSheetOpen = ref(false)
const tagCatalog = ref<Server[]>([])
const serverGroups = ref<ServerGroup[]>([])

const servers = toRef(serversStore, 'servers')
const selectedTags = toRef(serversStore, 'activeTagFilters')
const selectedGroupId = toRef(serversStore, 'activeGroupFilter')

const hasActiveFilters = computed(
  () => selectedTags.value.length > 0 || selectedGroupId.value !== null,
)

onMounted(async () => {
  await serversStore.fetch()
  tagCatalog.value = [...serversStore.servers]
  await loadServerGroups()
})

watch(
  servers,
  (list) => {
    if (!hasActiveFilters.value) {
      tagCatalog.value = [...list]
    }
  },
  { deep: true },
)

async function loadServerGroups(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    serverGroups.value = []

    return
  }

  try {
    serverGroups.value = await fetchServerGroups(activeOrgId)
  } catch {
    serverGroups.value = []
  }
}

const availableTags = computed((): string[] => {
  const tags = new Set<string>()

  for (const server of tagCatalog.value) {
    for (const tag of server.tags) {
      tags.add(tag)
    }
  }

  return [...tags].sort((left, right) => left.localeCompare(right))
})

function toggleTagFilter(tag: string): void {
  const nextTags = selectedTags.value.includes(tag)
    ? selectedTags.value.filter(entry => entry !== tag)
    : [...selectedTags.value, tag]

  void serversStore.fetch({ tags: nextTags })
}

function toggleGroupFilter(groupId: string): void {
  const nextGroupId = selectedGroupId.value === groupId ? null : groupId

  void serversStore.fetch({ serverGroupId: nextGroupId })
}

function clearFilters(): void {
  void serversStore.fetch({ tags: [], serverGroupId: null })
}

const isEmpty = computed(
  () => serversStore.hasFetched && !serversStore.isLoading && servers.value.length === 0,
)

async function handleGroupsChanged(): Promise<void> {
  await loadServerGroups()
}
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Servers"
      description="Manage and monitor your infrastructure hosts."
    >
      <template #actions>
        <Button
          v-if="authStore.isAdmin"
          type="button"
          variant="outline"
          @click="isGroupsSheetOpen = true"
        >
          <FolderIcon class="mr-2 size-4" />
          Manage groups
        </Button>
        <Button type="button" @click="isAddModalOpen = true">
          <PlusIcon class="mr-2 size-4" />
          Add Server
        </Button>
      </template>
    </PageHeader>

    <div
      v-if="serverGroups.length > 0"
      class="flex flex-wrap items-center gap-2"
      data-testid="server-group-filters"
    >
      <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        Groups
      </span>
      <button
        v-for="group in serverGroups"
        :key="group.id"
        type="button"
        class="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        @click="toggleGroupFilter(group.id)"
      >
        <Badge
          :variant="selectedGroupId === group.id ? 'default' : 'outline'"
          class="cursor-pointer"
        >
          {{ group.name }}
          <span class="ml-1 text-[10px] opacity-70">
            {{ group.serversCount ?? 0 }}
          </span>
        </Badge>
      </button>
    </div>

    <div
      v-if="availableTags.length > 0"
      class="flex flex-wrap items-center gap-2"
      data-testid="server-tag-filters"
    >
      <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        Tags
      </span>
      <button
        v-for="tag in availableTags"
        :key="tag"
        type="button"
        class="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        @click="toggleTagFilter(tag)"
      >
        <Badge
          :variant="selectedTags.includes(tag) ? 'default' : 'outline'"
          class="cursor-pointer capitalize"
        >
          {{ tag }}
        </Badge>
      </button>
      <Button
        v-if="hasActiveFilters"
        type="button"
        variant="ghost"
        size="sm"
        class="h-7 px-2"
        @click="clearFilters"
      >
        <XIcon class="mr-1 size-3" />
        Clear
      </Button>
    </div>

    <div
      v-if="serversStore.isLoading && !serversStore.hasFetched"
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      data-testid="servers-loading"
    >
      <Skeleton v-for="index in 6" :key="index" class="h-40 rounded-lg" />
    </div>

    <EmptyState
      v-else-if="isEmpty && !hasActiveFilters"
      title="No servers yet"
      description="Register your first server to start deploying applications."
      :icon="ServerIcon"
      data-testid="servers-empty"
      @action="isAddModalOpen = true"
    >
      <PlusIcon class="mr-2 size-4" />
      Add Server
    </EmptyState>

    <div
      v-else-if="isEmpty && hasActiveFilters"
      class="panel border-dashed p-8 text-center"
      data-testid="servers-filter-empty"
    >
      <p class="text-muted-foreground">
        No servers match the selected filters.
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="clearFilters">
        Clear filters
      </Button>
    </div>

    <div
      v-else-if="serversStore.fetchError !== null"
      class="panel border-dashed p-8 text-center"
      data-testid="servers-error"
    >
      <p class="text-muted-foreground">
        {{ serversStore.fetchError }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="serversStore.fetch()">
        Try again
      </Button>
    </div>

    <div
      v-else
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      data-testid="servers-grid"
    >
      <ServerCard
        v-for="server in servers"
        :key="server.id"
        :server="server"
      />
    </div>

    <AddServerModal v-if="isAddModalOpen" v-model:open="isAddModalOpen" />

    <ManageServerGroupsSheet
      v-if="isGroupsSheetOpen"
      v-model:open="isGroupsSheetOpen"
      @groups-changed="handleGroupsChanged"
    />
  </div>
</template>
