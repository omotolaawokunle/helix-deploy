<script setup lang="ts">
import { computed, onMounted, ref, toRef } from 'vue'
import { PlusIcon, ServerIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import AddServerModal from '@/features/servers/components/AddServerModal.vue'
import ServerCard from '@/features/servers/components/ServerCard.vue'
import { useServerPolling } from '@/features/servers/composables/useServerPolling'
import { useServersStore } from '@/features/servers/stores/useServersStore'

const serversStore = useServersStore()
const isAddModalOpen = ref(false)

const servers = toRef(serversStore, 'servers')

useServerPolling(servers, () => serversStore.fetch())

onMounted(() => {
  void serversStore.fetch()
})

const isEmpty = computed(
  () => serversStore.hasFetched && !serversStore.isLoading && servers.value.length === 0,
)
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
      v-if="serversStore.isLoading && !serversStore.hasFetched"
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      data-testid="servers-loading"
    >
      <Skeleton v-for="index in 6" :key="index" class="h-40 rounded-lg" />
    </div>

    <EmptyState
      v-else-if="isEmpty"
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

    <AddServerModal v-model:open="isAddModalOpen" />
  </div>
</template>
