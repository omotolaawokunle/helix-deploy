<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import BackLink from '@/components/layout/BackLink.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchCurrentMemberRole } from '@/features/organizations/api'
import { fetchServer } from '@/features/servers/api'
import DeploymentsTab from '@/features/sites/components/DeploymentsTab.vue'
import { fetchSite } from '@/features/sites/api'
import { TeamRole, type Server, type Site } from '@/types'

const route = useRoute()
const authStore = useAuthStore()

const site = ref<Site | null>(null)
const server = ref<Server | null>(null)
const memberRole = ref<TeamRole | null>(null)
const isLoading = ref(true)
const activeTab = ref('deployments')

const serverId = computed(() => String(route.params.id))
const siteId = computed(() => String(route.params.siteId))

const environmentName = computed(
  () => server.value?.environment?.label ?? server.value?.environment?.name ?? 'development',
)

const isProduction = computed(() => server.value?.environment?.isProduction ?? false)

async function loadPage(): Promise<void> {
  isLoading.value = true

  try {
    const [siteData, serverData] = await Promise.all([
      fetchSite(siteId.value),
      fetchServer(serverId.value),
    ])

    site.value = siteData
    server.value = serverData

    const orgId = authStore.currentOrg?.id
    const userId = authStore.user?.id

    if (orgId !== undefined && userId !== undefined) {
      memberRole.value = await fetchCurrentMemberRole(orgId, userId)
    }
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadPage()
})
</script>

<template>
  <div class="space-y-6">
    <BackLink :to="`/servers/${serverId}`" label="Back to server" />

    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-8 w-64" />
      <Skeleton class="h-64 w-full" />
    </div>

    <template v-else-if="site !== null">
      <div class="-mx-4 lg:-mx-8">
        <ProductionWarningBanner
          :resource-name="site.domain"
          :is-production="isProduction"
        />
      </div>

      <div class="flex flex-col gap-3 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">
              {{ site.domain }}
            </h1>
            <EnvironmentBadge
              :environment="environmentName"
              :is-production="isProduction"
            />
          </div>
          <p class="text-sm text-muted-foreground">
            {{ site.deployBranch }} · {{ site.runtime }}
          </p>
        </div>
      </div>

      <Tabs v-model="activeTab">
        <TabsList>
          <TabsTrigger value="deployments">
            Deployments
          </TabsTrigger>
          <TabsTrigger value="overview">
            Overview
          </TabsTrigger>
        </TabsList>
        <TabsContent value="deployments" class="mt-6">
          <DeploymentsTab
            :site="site"
            :is-production="isProduction"
            :member-role="memberRole"
          />
        </TabsContent>
        <TabsContent value="overview" class="mt-6">
          <p class="text-sm text-muted-foreground">
            Site overview settings will appear here.
          </p>
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
