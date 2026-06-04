<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PlusIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { fetchServerSites } from '@/features/sites/api'
import type { Site } from '@/types'

interface Props {
  serverId: string
}

const props = defineProps<Props>()

const sites = ref<Site[]>([])
const isLoading = ref(true)

async function loadSites(): Promise<void> {
  isLoading.value = true

  try {
    sites.value = await fetchServerSites(props.serverId)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadSites()
})
</script>

<template>
  <div class="space-y-4">
    <EmptyState
      v-if="!isLoading && sites.length === 0"
      title="No sites on this server"
      description="Add a site to start deploying applications."
      :icon="PlusIcon"
    />

    <div v-else class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Domain</TableHead>
            <TableHead>Branch</TableHead>
            <TableHead>Runtime</TableHead>
            <TableHead>Status</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="4" class="text-muted-foreground">
              Loading…
            </TableCell>
          </TableRow>
          <TableRow v-for="site in sites" :key="site.id">
            <TableCell>
              <RouterLink
                :to="`/servers/${serverId}/sites/${site.id}`"
                class="font-medium text-primary hover:underline"
              >
                {{ site.domain }}
              </RouterLink>
            </TableCell>
            <TableCell>{{ site.deployBranch }}</TableCell>
            <TableCell class="capitalize">
              {{ site.runtime }}
            </TableCell>
            <TableCell class="capitalize">
              {{ site.status }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
