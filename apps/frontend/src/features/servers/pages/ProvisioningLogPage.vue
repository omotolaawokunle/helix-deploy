<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import BackLink from '@/components/layout/BackLink.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Skeleton } from '@/components/ui/skeleton'
import ProvisioningLogViewer from '@/features/servers/components/ProvisioningLogViewer.vue'
import { useProvisioningChannel } from '@/features/servers/composables/useProvisioningChannel'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import type { Server } from '@/types'

const route = useRoute()
const serversStore = useServersStore()

const server = ref<Server | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const logLines = ref<string[]>([])
const isComplete = ref(false)

const serverId = computed(() => String(route.params.id))
const runId = computed(() => {
  const value = route.query.runId

  return typeof value === 'string' ? value : null
})

const pageDescription = computed(() => {
  const base = 'Live output from the provisioning run.'

  if (runId.value === null) {
    return base
  }

  return `${base} Run ID: ${runId.value}`
})

async function loadServer(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    server.value = (await serversStore.getById(serverId.value)) ?? null

    if (server.value === null) {
      loadError.value = 'Server not found.'
    }
  } catch {
    server.value = null
    loadError.value = 'Unable to load server.'
  } finally {
    isLoading.value = false
  }
}

useProvisioningChannel(serverId.value, {
  onLogLine: (payload) => {
    if (runId.value !== null && payload.runId !== runId.value) {
      return
    }

    logLines.value.push(payload.line)
  },
  onCompleted: (payload) => {
    if (runId.value !== null && payload.runId !== runId.value) {
      return
    }

    isComplete.value = true
    void loadServer()
  },
})

onMounted(() => {
  void loadServer()
})
</script>

<template>
  <div class="space-y-8">
    <BackLink :to="`/servers/${serverId}`" label="Back to server" />

    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-8 w-72" />
      <Skeleton class="h-96 w-full rounded-lg" />
    </div>

    <div v-else-if="loadError !== null" class="panel border-dashed p-8 text-center">
      <p class="text-muted-foreground">
        {{ loadError }}
      </p>
    </div>

    <template v-else>
      <PageHeader
        :title="`Provisioning ${server?.hostname ?? 'server'}`"
        :description="pageDescription"
      />

      <ProvisioningLogViewer
        :lines="logLines"
        title="Provisioning output"
        :is-complete="isComplete"
      />
    </template>
  </div>
</template>
