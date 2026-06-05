<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  CheckIcon,
  PlugIcon,
  ServerCogIcon,
} from '@lucide/vue'
import { toast } from 'vue-sonner'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BackLink from '@/components/layout/BackLink.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import ProviderIcon from '@/features/servers/components/ProviderIcon.vue'

import { testServerConnection } from '@/features/servers/api'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import { ManagementMode, type Server } from '@/types'

const ServerSitesTab = defineAsyncComponent(
  () => import('@/features/servers/components/ServerSitesTab.vue'),
)
const CronJobsTab = defineAsyncComponent(
  () => import('@/features/cron-jobs/components/CronJobsTab.vue'),
)
const DaemonsTab = defineAsyncComponent(
  () => import('@/features/daemons/components/DaemonsTab.vue'),
)
const CommandRunnerTab = defineAsyncComponent(
  () => import('@/features/commands/components/CommandRunnerTab.vue'),
)
const ProvisionServerDrawer = defineAsyncComponent(
  () => import('@/features/servers/components/ProvisionServerDrawer.vue'),
)

const route = useRoute()
const serversStore = useServersStore()

const server = ref<Server | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isProvisionDrawerOpen = ref(false)
const isTestingConnection = ref(false)
const activeTab = ref('overview')

const serverId = computed(() => String(route.params.id))

const environmentName = computed(
  () => server.value?.environment?.label ?? server.value?.environment?.name ?? 'development',
)

const isProduction = computed(() => server.value?.environment?.isProduction ?? false)

const installedServiceNames = computed((): string[] => {
  const services = server.value?.installedServices

  if (services === undefined || services === null) {
    return []
  }

  if (Array.isArray(services)) {
    return services
  }

  return Object.entries(services as Record<string, { installed?: boolean }>)
    .filter(([, value]) => value?.installed === true)
    .map(([name]) => name)
})

const diskLabel = computed((): string | null => {
  const health = server.value?.healthStatus

  if (health?.diskUsedPercent === undefined) {
    return null
  }

  const total = health.diskTotalGb !== undefined ? ` / ${health.diskTotalGb} GB` : ''

  return `${health.diskUsedPercent}% used${total}`
})

const fingerprintLabel = computed((): string => {
  const health = server.value?.healthStatus

  if (health?.fingerprintVerified === true) {
    return 'Verified'
  }

  return 'Not verified'
})

const lastCheckedLabel = computed((): string | null => {
  const checkedAt = server.value?.healthStatus?.lastCheckedAt

  if (checkedAt === undefined) {
    return null
  }

  return new Date(checkedAt).toLocaleString()
})

const managementModeLabel = computed((): string => {
  if (server.value?.managementMode === ManagementMode.Observe) {
    return 'Observe'
  }

  return 'Managed'
})

interface OverviewRow {
  label: string
  value: string
}

const overviewRows = computed((): OverviewRow[] => {
  if (server.value === null) {
    return []
  }

  const rows: OverviewRow[] = [
    { label: 'Operating system', value: server.value.os ?? 'Unknown' },
    { label: 'PHP version', value: server.value.phpVersion ?? '—' },
    { label: 'Node version', value: server.value.nodeVersion !== null ? String(server.value.nodeVersion) : '—' },
    { label: 'Management mode', value: managementModeLabel.value },
    { label: 'SSH user', value: server.value.sshUser },
  ]

  if (diskLabel.value !== null) {
    rows.push({ label: 'Disk usage', value: diskLabel.value })
  }

  return rows
})

async function loadServer(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    const result = await serversStore.getById(serverId.value)
    server.value = result ?? null

    if (result === undefined) {
      loadError.value = 'Server not found.'
    }
  } catch {
    loadError.value = 'Unable to load server.'
  } finally {
    isLoading.value = false
  }
}

async function handleTestConnection(): Promise<void> {
  isTestingConnection.value = true

  try {
    await testServerConnection(serverId.value)
    toast.success('Connection test started.')
    await loadServer()
  } catch {
    toast.error('Connection test failed.')
  } finally {
    isTestingConnection.value = false
  }
}

onMounted(() => {
  void loadServer()
})
</script>

<template>
  <div class="space-y-8">
    <BackLink to="/servers" label="Back to servers" />

    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-10 w-64" />
      <Skeleton class="h-6 w-40" />
      <Skeleton class="h-48 w-full" />
    </div>

    <div v-else-if="loadError !== null || server === null" class="panel border-dashed p-8 text-center">
      <p class="text-muted-foreground">
        {{ loadError ?? 'Server not found.' }}
      </p>
    </div>

    <template v-else>
      <div class="-mx-4 lg:-mx-8">
        <ProductionWarningBanner
          :resource-name="server.hostname"
          :is-production="isProduction"
        />
      </div>

      <div class="flex flex-col gap-6 border-b pb-8 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">
              {{ server.hostname }}
            </h1>
            <StatusBadge :status="server.status" type="server" />
          </div>

          <p class="font-mono text-sm text-muted-foreground">
            {{ server.ipAddress }}:{{ server.sshPort }}
          </p>

          <div class="flex flex-wrap items-center gap-2">
            <EnvironmentBadge
              :environment="environmentName"
              :is-production="isProduction"
            />
            <Badge variant="outline" class="gap-2 capitalize">
              <ProviderIcon :provider="server.provider" class="size-6" />
              {{ server.provider }}
            </Badge>
            <Badge v-if="server.project" variant="secondary">
              {{ server.project.name }}
            </Badge>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            :disabled="isTestingConnection"
            @click="handleTestConnection"
          >
            <PlugIcon class="mr-2 size-4" />
            {{ isTestingConnection ? 'Testing…' : 'Test Connection' }}
          </Button>
          <Button type="button" @click="isProvisionDrawerOpen = true">
            <ServerCogIcon class="mr-2 size-4" />
            Provision Server
          </Button>
        </div>
      </div>

      <Tabs v-model="activeTab">
        <TabsList>
          <TabsTrigger value="overview">
            Overview
          </TabsTrigger>
          <TabsTrigger value="sites">
            Sites
          </TabsTrigger>
          <TabsTrigger value="cron">
            Cron Jobs
          </TabsTrigger>
          <TabsTrigger value="daemons">
            Daemons
          </TabsTrigger>
          <TabsTrigger value="commands">
            Commands
          </TabsTrigger>
          <TabsTrigger value="audit">
            Audit
          </TabsTrigger>
        </TabsList>

        <TabsContent value="overview" class="mt-6 space-y-8">
          <section>
            <h2 class="section-label">
              Server details
            </h2>
            <dl class="panel divide-y">
              <div
                v-for="row in overviewRows"
                :key="row.label"
                class="flex items-center justify-between gap-4 px-4 py-3"
              >
                <dt class="text-sm text-muted-foreground">
                  {{ row.label }}
                </dt>
                <dd class="text-sm font-medium">
                  {{ row.value }}
                </dd>
              </div>
            </dl>
          </section>

          <section>
            <h2 class="section-label">
              Installed services
            </h2>
            <div class="panel px-4 py-3">
              <ul v-if="installedServiceNames.length > 0" class="space-y-2">
                <li
                  v-for="service in installedServiceNames"
                  :key="service"
                  class="flex items-center gap-2 text-sm capitalize"
                >
                  <CheckIcon class="size-4 text-primary" />
                  {{ service }}
                </li>
              </ul>
              <p v-else class="text-sm text-muted-foreground">
                No services installed yet. Use Provision Server to install stack components.
              </p>
            </div>
          </section>

          <section>
            <h2 class="section-label">
              Connection fingerprint
            </h2>
            <div class="panel px-4 py-3">
              <p class="text-sm">
                Status: <span class="font-medium">{{ fingerprintLabel }}</span>
              </p>
              <p v-if="lastCheckedLabel !== null" class="mt-1 text-sm text-muted-foreground">
                Last checked: {{ lastCheckedLabel }}
              </p>
            </div>
          </section>
        </TabsContent>

        <TabsContent value="sites" class="mt-6">
          <ServerSitesTab v-if="activeTab === 'sites'" :server-id="server.id" />
        </TabsContent>

        <TabsContent value="cron" class="mt-6">
          <CronJobsTab v-if="activeTab === 'cron'" :server-id="server.id" />
        </TabsContent>

        <TabsContent value="daemons" class="mt-6">
          <DaemonsTab v-if="activeTab === 'daemons'" :server-id="server.id" />
        </TabsContent>

        <TabsContent value="commands" class="mt-6">
          <CommandRunnerTab
            v-if="activeTab === 'commands'"
            :server-id="server.id"
            :is-production="isProduction"
          />
        </TabsContent>

        <TabsContent value="audit" class="mt-6">
          <p class="text-sm text-muted-foreground">
            <RouterLink to="/audit" class="text-primary hover:underline">
              View full organization audit log
            </RouterLink>
          </p>
        </TabsContent>
      </Tabs>

      <ProvisionServerDrawer
        v-if="isProvisionDrawerOpen"
        v-model:open="isProvisionDrawerOpen"
        :server-id="server.id"
      />
    </template>
  </div>
</template>
