<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BackLink from '@/components/layout/BackLink.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchCurrentMemberRole } from '@/features/organizations/api'
import { fetchServer } from '@/features/servers/api'
import { fetchSite } from '@/features/sites/api'
import { TeamRole, type Server, type Site } from '@/types'

const DeploymentsTab = defineAsyncComponent(
  () => import('@/features/sites/components/DeploymentsTab.vue'),
)
const EnvVarsTab = defineAsyncComponent(
  () => import('@/features/sites/components/EnvVarsTab.vue'),
)
const NginxConfigTab = defineAsyncComponent(
  () => import('@/features/sites/components/NginxConfigTab.vue'),
)
const SiteSettingsTab = defineAsyncComponent(
  () => import('@/features/sites/components/SiteSettingsTab.vue'),
)
const SiteDnsSslTab = defineAsyncComponent(
  () => import('@/features/sites/components/SiteDnsSslTab.vue'),
)

const route = useRoute()
const authStore = useAuthStore()

const site = ref<Site | null>(null)
const server = ref<Server | null>(null)
const memberRole = ref<TeamRole | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const activeTab = ref('deployments')

const serverId = computed(() => String(route.params.id))
const siteId = computed(() => String(route.params.siteId))

const environmentName = computed(
  () => server.value?.environment?.label ?? server.value?.environment?.name ?? 'development',
)

const isProduction = computed(() => server.value?.environment?.isProduction ?? false)

const showDnsBadge = computed(
  () => site.value !== null
    && site.value.autoCreateDns
    && site.value.dnsStatus !== null
    && site.value.dnsStatus !== 'none',
)

const showSslBadge = computed(
  () => site.value !== null
    && site.value.enableSsl
    && site.value.sslStatus !== null
    && site.value.sslStatus !== 'none',
)

async function loadPage(): Promise<void> {
  isLoading.value = true
  loadError.value = null

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
  } catch {
    site.value = null
    loadError.value = 'Unable to load site.'
  } finally {
    isLoading.value = false
  }
}

function handleSiteUpdated(updated: Site): void {
  site.value = updated
}

onMounted(() => {
  void loadPage()
})
</script>

<template>
  <div class="space-y-8">
    <BackLink :to="`/servers/${serverId}`" label="Back to server" />

    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-8 w-64" />
      <Skeleton class="h-64 w-full" />
    </div>

    <div v-else-if="loadError !== null || site === null" class="panel border-dashed p-8 text-center">
      <p class="text-muted-foreground">
        {{ loadError ?? 'Site not found.' }}
      </p>
    </div>

    <template v-else>
      <div class="-mx-4 lg:-mx-8">
        <ProductionWarningBanner
          :resource-name="site.domain"
          :is-production="isProduction"
        />
      </div>

      <div class="flex flex-col gap-4 border-b pb-8 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">
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
          <div v-if="showDnsBadge || showSslBadge" class="flex flex-wrap gap-2 pt-1">
            <StatusBadge
              v-if="showDnsBadge"
              :status="site.dnsStatus ?? 'none'"
              type="dns"
            />
            <StatusBadge
              v-if="showSslBadge"
              :status="site.sslStatus ?? 'none'"
              type="ssl"
            />
          </div>
        </div>
      </div>

      <Tabs v-model="activeTab">
        <TabsList>
          <TabsTrigger value="deployments">
            Deployments
          </TabsTrigger>
          <TabsTrigger value="env-vars">
            Environment Variables
          </TabsTrigger>
          <TabsTrigger value="nginx">
            Nginx Config
          </TabsTrigger>
          <TabsTrigger value="dns-ssl">
            DNS &amp; SSL
          </TabsTrigger>
          <TabsTrigger value="settings">
            Settings
          </TabsTrigger>
        </TabsList>
        <TabsContent value="deployments" class="mt-6">
          <DeploymentsTab
            v-if="activeTab === 'deployments'"
            :site="site"
            :is-production="isProduction"
            :member-role="memberRole"
          />
        </TabsContent>
        <TabsContent value="env-vars" class="mt-6">
          <EnvVarsTab v-if="activeTab === 'env-vars'" :site-id="site.id" />
        </TabsContent>
        <TabsContent value="nginx" class="mt-6">
          <NginxConfigTab v-if="activeTab === 'nginx'" :site-id="site.id" />
        </TabsContent>
        <TabsContent value="dns-ssl" class="mt-6">
          <SiteDnsSslTab
            v-if="activeTab === 'dns-ssl'"
            :site="site"
            @updated="handleSiteUpdated"
          />
        </TabsContent>
        <TabsContent value="settings" class="mt-6">
          <SiteSettingsTab
            v-if="activeTab === 'settings'"
            :site="site"
            @updated="handleSiteUpdated"
          />
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
