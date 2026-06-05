<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, toRef } from 'vue'
import { PlusIcon, ServerIcon, XIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { fetchServers } from '@/features/servers/api'
import ServerCard from '@/features/servers/components/ServerCard.vue'
import { useServerPolling } from '@/features/servers/composables/useServerPolling'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import type { Server } from '@/types'

const AddServerModal = defineAsyncComponent(
  () => import('@/features/servers/components/AddServerModal.vue'),
)

const serversStore = useServersStore()
const { orgId } = useActiveOrg()
const isAddModalOpen = ref(false)
const tagCatalog = ref<Server[]>([])

const servers = toRef(serversStore, 'servers')
const selectedTags = toRef(serversStore, 'activeTagFilters')

useServerPolling(servers, () => serversStore.fetch(selectedTags.value))

onMounted(async () => {
  await serversStore.fetch()
  await loadTagCatalog()
})

async function loadTagCatalog(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    tagCatalog.value = []

    return
  }

  tagCatalog.value = await fetchServers(activeOrgId)
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

  void serversStore.fetch(nextTags)
}

function clearTagFilters(): void {
  void serversStore.fetch([])
}

const isEmpty = computed(
  () => serversStore.hasFetched && !serversStore.isLoading && servers.value.length === 0,
)

const hasActiveTagFilters = computed(() => selectedTags.value.length > 0)
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Servers"
      description="Manage and monitor your infrastructure hosts."
    >
      <template #actions>
        <Button type="button" @click="isAddModalOpen = true">
          <PlusIcon class="mr-2 size-4" />
          Add Server
        </Button>
      </template>
    </PageHeader>

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
        v-if="hasActiveTagFilters"
        type="button"
        variant="ghost"
        size="sm"
        class="h-7 px-2"
        @click="clearTagFilters"
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
      v-else-if="isEmpty && !hasActiveTagFilters"
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
      v-else-if="isEmpty && hasActiveTagFilters"
      class="panel border-dashed p-8 text-center"
      data-testid="servers-filter-empty"
    >
      <p class="text-muted-foreground">
        No servers match the selected tags.
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="clearTagFilters">
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
  </div>
</template>
