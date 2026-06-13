<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  LoaderCircleIcon,
  PlugIcon,
  ServerCogIcon,
  Trash2Icon,
} from '@lucide/vue'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import FirstVisitHint from '@/features/onboarding/components/FirstVisitHint.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BackLink from '@/components/layout/BackLink.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import ProviderIcon from '@/features/servers/components/ProviderIcon.vue'
import InstalledServicesPanel from '@/features/servers/components/InstalledServicesPanel.vue'
import ServerObserveModeAlert from '@/features/servers/components/ServerObserveModeAlert.vue'

import { deleteServer, testServerConnection } from '@/features/servers/api'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import { useRealtimeStore } from '@/stores/useRealtimeStore'
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
const ServerLogsTab = defineAsyncComponent(
  () => import('@/features/servers/components/ServerLogsTab.vue'),
)
const ProvisionServerDrawer = defineAsyncComponent(
  () => import('@/features/servers/components/ProvisionServerDrawer.vue'),
)

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const serversStore = useServersStore()
const realtimeStore = useRealtimeStore()

const server = ref<Server | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isProvisionDrawerOpen = ref(false)
const isTestingConnection = ref(false)
const isDeleteDialogOpen = ref(false)
const isDeleting = ref(false)
const isAwaitingDeletion = ref(false)
const activeTab = ref('overview')

const serverId = computed(() => String(route.params.id))

const environmentName = computed(
  () => server.value?.environment?.label ?? server.value?.environment?.name ?? 'development',
)

const isProduction = computed(() => server.value?.environment?.isProduction ?? false)

const canDeleteServer = computed(() => authStore.isOwner)

const canManageServices = computed(() => authStore.isAdmin)

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

const installedServicesPanelRef = ref<InstanceType<typeof InstalledServicesPanel> | null>(null)

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

const isObserveMode = computed(
  (): boolean => server.value?.managementMode === ManagementMode.Observe,
)

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

function handleServerDeleted(deletedId: string): void {
  if (!isAwaitingDeletion.value || deletedId !== serverId.value) {
    return
  }

  isAwaitingDeletion.value = false
  serversStore.invalidateCache()
  toast.success('Server deleted.')
  void router.push('/servers')
}

watch(
  () => realtimeStore.serverServiceStatusUpdateSeq,
  () => {
    const update = realtimeStore.serverServiceStatusUpdate

    if (update === null || update.serverId !== serverId.value) {
      return
    }

    installedServicesPanelRef.value?.applyServicesUpdate(update.services)
  },
)

watch(
  () => realtimeStore.serverInventoryRefreshId,
  (refreshedServerId) => {
    if (refreshedServerId === serverId.value) {
      void loadServer()
      realtimeStore.consumeServerInventoryRefresh(serverId.value)
    }
  },
)

watch(
  () => realtimeStore.deletedServerId,
  (deletedId) => {
    if (deletedId === null) {
      return
    }

    handleServerDeleted(deletedId)
  },
)

watch(
  () => realtimeStore.serverMetricsPatchSeq,
  () => {
    if (server.value === null) {
      return
    }

    const cached = serversStore.servers.find(entry => entry.id === serverId.value)

    if (cached?.healthStatus !== undefined) {
      server.value = {
        ...server.value,
        healthStatus: cached.healthStatus,
      }
    }
  },
)

async function handleDeleteServer(): Promise<void> {
  if (server.value === null) {
    return
  }

  isDeleting.value = true

  try {
    await deleteServer(serverId.value)
    isDeleteDialogOpen.value = false
    isAwaitingDeletion.value = true
    toast.message('Deletion scheduled.', {
      description: 'Live updates will redirect you when cleanup completes.',
    })
  } catch {
    toast.error('Unable to delete server.')
  } finally {
    isDeleting.value = false
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
            <Badge
              variant="outline"
              :class="isObserveMode ? 'border-primary/30 text-primary' : ''"
            >
              {{ managementModeLabel }}
            </Badge>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            :disabled="isTestingConnection || isAwaitingDeletion"
            @click="handleTestConnection"
          >
            <PlugIcon class="mr-2 size-4" />
            {{ isTestingConnection ? 'Testing…' : 'Test Connection' }}
          </Button>
          <TooltipProvider v-if="isObserveMode">
            <Tooltip>
              <TooltipTrigger as-child>
                <span class="inline-flex">
                  <Button
                    type="button"
                    disabled
                  >
                    <ServerCogIcon class="mr-2 size-4" />
                    Provision Server
                  </Button>
                </span>
              </TooltipTrigger>
              <TooltipContent side="bottom" class="max-w-xs text-xs">
                Switch this server to Managed mode before provisioning. Observe mode keeps your existing stack unchanged.
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
          <Button
            v-else
            type="button"
            :disabled="isAwaitingDeletion"
            @click="isProvisionDrawerOpen = true"
          >
            <ServerCogIcon class="mr-2 size-4" />
            Provision Server
          </Button>
          <Button
            v-if="canDeleteServer"
            type="button"
            variant="destructive"
            :disabled="isAwaitingDeletion || isDeleting"
            @click="isDeleteDialogOpen = true"
          >
            <Trash2Icon class="mr-2 size-4" />
            Delete Server
          </Button>
        </div>
      </div>

      <div
        v-if="isAwaitingDeletion"
        class="flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3"
        role="status"
        aria-live="polite"
        data-testid="server-deletion-wait-banner"
      >
        <LoaderCircleIcon
          class="mt-0.5 size-4 shrink-0 animate-spin text-destructive motion-reduce:animate-none"
          aria-hidden="true"
        />
        <div class="space-y-1 text-sm">
          <p class="font-medium text-destructive">
            Deletion in progress
          </p>
          <p class="text-destructive/90">
            Credentials and server records are being removed. You will be redirected when cleanup finishes.
          </p>
        </div>
      </div>

      <ServerObserveModeAlert v-if="isObserveMode" />

      <FirstVisitHint
        hint-id="server-detail"
        title="Agentless SSH"
        description="HelixDeploy connects over SSH — no agent is installed on your server. Use Provision Server to install Nginx, PHP, Node.js, and other services. Logs stream live during provisioning."
      />

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
          <TabsTrigger value="logs">
            Logs
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

          <InstalledServicesPanel
            v-if="server !== null"
            ref="installedServicesPanelRef"
            :server-id="server.id"
            :management-mode="server.managementMode"
            :is-production="isProduction"
            :can-manage="canManageServices"
          />

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
          <ServerSitesTab
            v-if="activeTab === 'sites'"
            :server-id="server.id"
            :project-id="server.project?.id ?? null"
          />
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

        <TabsContent value="logs" class="mt-6">
          <ServerLogsTab v-if="activeTab === 'logs'" :server-id="server.id" />
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
        :detected-services="installedServiceNames"
      />

      <ConfirmDestructiveDialog
        v-model:open="isDeleteDialogOpen"
        title="Delete server"
        :description="`This permanently removes ${server.hostname} and its stored credentials after a 30-second grace period.`"
        :confirm-text="server.hostname"
        confirm-button-label="Delete server"
        :can-confirm="!isDeleting"
        @confirm="handleDeleteServer"
      />
    </template>
  </div>
</template>
