<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useDocumentVisibility } from '@vueuse/core'
import { RocketIcon, ServerIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import GettingStartedPanel from '@/features/onboarding/components/GettingStartedPanel.vue'
import { useOnboardingProgress } from '@/features/onboarding/composables/useOnboardingProgress'
import type { OnboardingInput } from '@/features/onboarding/types'
import LoadErrorPanel from '@/components/common/LoadErrorPanel.vue'
import OverviewStatSkeleton from '@/components/common/OverviewStatSkeleton.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TableSkeleton from '@/components/common/TableSkeleton.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { triggerDeployment } from '@/features/deployments/api'
import { loadDashboardData } from '@/features/monitoring/api'
import { patchServerMetricsInList } from '@/features/monitoring/lib/patchServerMetricsInList'
import { fetchProjects } from '@/features/projects/api'
import { fetchDnsProviderConnections } from '@/features/integrations/api'
import { fetchOrgSites } from '@/features/sites/api'
import { formatDurationSeconds, formatMetricPercent, formatRelativeTime, metricUsageClass } from '@/lib/format'
import { useRealtimeStore } from '@/stores/useRealtimeStore'
import type { DeploymentListItem } from '@/features/deployments/types'
import type { DashboardStats } from '@/features/monitoring/api'
import type { Server, Site } from '@/types'

const authStore = useAuthStore()
const realtimeStore = useRealtimeStore()
const documentVisibility = useDocumentVisibility()

const stats = ref<DashboardStats | null>(null)
const servers = ref<Server[]>([])
const deployments = ref<DeploymentListItem[]>([])
const sites = ref<Site[]>([])
const projectCount = ref(0)
const hasDnsIntegration = ref(false)
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const orgId = computed(() => authStore.currentOrg?.id)

const onboardingInput = computed((): OnboardingInput => {
  const firstServer = servers.value[0] ?? null
  const firstSite = sites.value[0] ?? null

  return {
    serverCount: servers.value.length,
    hasProvisionedServer: servers.value.some(
      server => (server.installedServices ?? []).length > 0,
    ),
    projectCount: projectCount.value,
    siteCount: sites.value.length,
    deploymentCount: deployments.value.length,
    hasDnsIntegration: hasDnsIntegration.value,
    firstServerId: firstServer?.id ?? null,
    firstSiteId: firstSite?.id ?? null,
    firstSiteServerId: firstSite?.serverId ?? null,
  }
})

const {
  steps: onboardingSteps,
  completedCount: onboardingCompletedCount,
  currentStep: onboardingCurrentStep,
  showPanel: showGettingStarted,
  dismiss: dismissOnboarding,
} = useOnboardingProgress({
  orgId,
  input: onboardingInput,
})

const quickDeploySiteId = ref('')

defineExpose({ quickDeploySiteId })
const quickDeployBranch = ref('')
const isDeploying = ref(false)
const highlightedMetricServerIds = ref<Set<string>>(new Set())
let metricHighlightTimer: ReturnType<typeof setTimeout> | undefined
let refreshPromise: Promise<void> | null = null

const hasDashboardData = computed(
  () => stats.value !== null || servers.value.length > 0 || deployments.value.length > 0,
)

const selectedSite = computed(() =>
  sites.value.find(site => site.id === quickDeploySiteId.value) ?? null,
)

const selectedSiteServer = computed(() => {
  if (selectedSite.value === null) {
    return null
  }

  return servers.value.find(server => server.id === selectedSite.value?.serverId) ?? null
})

const isQuickDeployProduction = computed(
  () => selectedSiteServer.value?.environment?.isProduction ?? false,
)

const successRatioLabel = computed((): string => {
  if (stats.value === null) {
    return '—'
  }

  const total = stats.value.successfulToday + stats.value.failedToday

  if (total === 0) {
    return '0 / 0'
  }

  return `${stats.value.successfulToday} / ${stats.value.failedToday}`
})

interface OverviewStatRow {
  label: string
  value: string
}

interface ServerMetricRow {
  id: string
  hostname: string
  cpuPercent: number | null
  memoryPercent: number | null
  diskPercent: number | null
}

const serverMetricRows = computed((): ServerMetricRow[] =>
  servers.value
    .filter(server => server.healthStatus?.lastCheckedAt !== undefined)
    .map(server => ({
      id: server.id,
      hostname: server.hostname,
      cpuPercent: server.healthStatus?.cpuPercent ?? null,
      memoryPercent: server.healthStatus?.memoryUsedPercent ?? null,
      diskPercent: server.healthStatus?.diskUsedPercent ?? null,
    }))
    .sort((left, right) => (right.diskPercent ?? 0) - (left.diskPercent ?? 0))
    .slice(0, 6),
)

const showMetricsSection = computed(
  () => !isLoading.value && servers.value.length > 0,
)

const metricsFootnote = computed((): string | null => {
  const timestamps = serverMetricRows.value
    .map(row => servers.value.find(server => server.id === row.id)?.healthStatus?.lastCheckedAt)
    .filter((value): value is string => value !== undefined && value !== '')

  if (timestamps.length === 0) {
    return null
  }

  const latest = timestamps.reduce((left, right) =>
    new Date(right).getTime() > new Date(left).getTime() ? right : left,
  )

  return `Last updated ${formatRelativeTime(latest)}`
})

const overviewRows = computed((): OverviewStatRow[] => {
  if (stats.value === null) {
    return []
  }

  return [
    { label: 'Active servers', value: String(stats.value.activeServers) },
    { label: 'Deployments today', value: String(stats.value.deploymentsToday) },
    { label: 'Successful / failed today', value: successRatioLabel.value },
    { label: 'Servers with issues', value: String(stats.value.serversWithIssues) },
  ]
})

async function refreshDashboard(): Promise<void> {
  if (refreshPromise !== null) {
    await refreshPromise

    return
  }

  refreshPromise = (async (): Promise<void> => {
    const orgId = authStore.currentOrg?.id

    if (orgId === undefined) {
      return
    }

    try {
      const [data, orgSites, projects, dnsProviders] = await Promise.all([
        loadDashboardData(orgId),
        fetchOrgSites(orgId),
        fetchProjects(orgId),
        fetchDnsProviderConnections(orgId),
      ])
      stats.value = data.stats
      servers.value = data.servers
      deployments.value = data.deployments
      sites.value = orgSites
      projectCount.value = projects.length
      hasDnsIntegration.value = dnsProviders.cloudflare.connected
        || dnsProviders.digitalocean.connected
      loadError.value = null
    } catch {
      loadError.value = 'Unable to load dashboard.'
    }
  })()

  try {
    await refreshPromise
  } finally {
    refreshPromise = null
  }
}

async function loadInitial(): Promise<void> {
  isLoading.value = true
  await refreshDashboard()
  isLoading.value = false
}

function applyServerMetricsPatch(): void {
  const patch = realtimeStore.serverMetricsPatch

  if (patch === null) {
    return
  }

  const result = patchServerMetricsInList(servers.value, patch)

  if (result === 'missing') {
    return
  }

  servers.value = result
  highlightedMetricServerIds.value = new Set([patch.serverId])

  if (metricHighlightTimer !== undefined) {
    clearTimeout(metricHighlightTimer)
  }

  metricHighlightTimer = setTimeout(() => {
    highlightedMetricServerIds.value = new Set()
    metricHighlightTimer = undefined
  }, 700)
}

watch(
  () => realtimeStore.serverMetricsPatchSeq,
  () => {
    applyServerMetricsPatch()
  },
)

watch(
  () => realtimeStore.dashboardRefreshToken,
  () => {
    void refreshDashboard()
  },
)

watch(documentVisibility, (visibility, previousVisibility) => {
  if (
    visibility === 'visible'
    && previousVisibility === 'hidden'
    && realtimeStore.connectionStatus !== 'connected'
  ) {
    void refreshDashboard()
  }
})

async function handleQuickDeploy(): Promise<void> {
  if (quickDeploySiteId.value === '' || quickDeployBranch.value.trim() === '') {
    return
  }

  isDeploying.value = true

  try {
    await triggerDeployment(quickDeploySiteId.value, {
      branch: quickDeployBranch.value.trim(),
    })
    toast.success('Deployment queued.')
    await refreshDashboard()
  } catch {
    toast.error('Unable to start deployment.')
  } finally {
    isDeploying.value = false
  }
}

watch(quickDeploySiteId, (siteId) => {
  const site = sites.value.find(entry => entry.id === siteId)

  if (site !== undefined) {
    quickDeployBranch.value = site.deployBranch
  }
})

onMounted(() => {
  void loadInitial()
})

onUnmounted(() => {
  if (metricHighlightTimer !== undefined) {
    clearTimeout(metricHighlightTimer)
  }
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Dashboard"
      description="Organization overview — servers, deployments, and quick actions."
      :loading="isLoading && !hasDashboardData"
    />

    <LoadErrorPanel
      v-if="!isLoading && loadError !== null && !hasDashboardData"
      :message="loadError"
      data-testid="dashboard-error"
      @retry="loadInitial"
    />

    <template v-else>
      <LoadErrorPanel
        v-if="loadError !== null && hasDashboardData"
        :message="loadError"
        class="py-6"
        @retry="refreshDashboard"
      />

      <GettingStartedPanel
        v-if="!isLoading && showGettingStarted"
        :steps="onboardingSteps"
        :completed-count="onboardingCompletedCount"
        :current-step="onboardingCurrentStep"
        @dismiss="dismissOnboarding"
      />

      <section>
        <h2 class="section-label">
          Overview
        </h2>
        <OverviewStatSkeleton v-if="isLoading && overviewRows.length === 0" />
        <dl v-else class="panel divide-y">
          <div
            v-for="row in overviewRows"
            :key="row.label"
            class="flex items-center justify-between gap-4 px-4 py-3 transition-colors duration-200"
          >
            <dt class="text-sm text-muted-foreground">
              {{ row.label }}
            </dt>
            <dd class="text-sm font-semibold tabular-nums text-foreground">
              {{ row.value }}
            </dd>
          </div>
        </dl>
      </section>

    <section v-if="showMetricsSection">
      <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <h2 class="section-label mb-0">
          Server metrics
        </h2>
        <p
          v-if="metricsFootnote !== null"
          class="text-xs text-muted-foreground"
        >
          {{ metricsFootnote }}
        </p>
      </div>
      <div class="panel overflow-hidden">
        <Table v-if="serverMetricRows.length > 0">
          <TableHeader>
            <TableRow>
              <TableHead>Server</TableHead>
              <TableHead>CPU</TableHead>
              <TableHead>Memory</TableHead>
              <TableHead>Disk</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="row in serverMetricRows"
              :key="row.id"
              class="transition-colors duration-200"
              :class="{ 'bg-primary/5': highlightedMetricServerIds.has(row.id) }"
            >
              <TableCell>
                <RouterLink :to="`/servers/${row.id}`" class="font-medium hover:underline">
                  {{ row.hostname }}
                </RouterLink>
              </TableCell>
              <TableCell class="tabular-nums" :class="metricUsageClass(row.cpuPercent)">
                {{ formatMetricPercent(row.cpuPercent) }}
              </TableCell>
              <TableCell class="tabular-nums" :class="metricUsageClass(row.memoryPercent)">
                {{ formatMetricPercent(row.memoryPercent) }}
              </TableCell>
              <TableCell class="tabular-nums" :class="metricUsageClass(row.diskPercent)">
                {{ formatMetricPercent(row.diskPercent) }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
        <p
          v-else
          class="px-4 py-6 text-sm text-muted-foreground"
          data-testid="dashboard-metrics-empty"
        >
          Metrics appear after the first collection cycle. Active servers are checked every few minutes.
        </p>
      </div>
    </section>

    <section class="grid gap-8 lg:grid-cols-3">
      <div class="space-y-4 lg:col-span-2">
        <h2 class="section-label">
          Recent Deployments
        </h2>
        <div class="panel overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Site</TableHead>
                <TableHead>Branch</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Triggered by</TableHead>
                <TableHead>Duration</TableHead>
                <TableHead>Time</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableSkeleton v-if="isLoading" :columns="6" :rows="5" />
              <TableRow v-else-if="deployments.length === 0">
                <TableCell colspan="6">
                  <EmptyState
                    :icon="RocketIcon"
                    title="No deployments yet"
                    description="Trigger a deployment from a site or use Quick Deploy when sites are configured."
                    class="border-0 bg-transparent px-0 py-6 shadow-none hover:shadow-none"
                  />
                </TableCell>
              </TableRow>
              <template v-else>
                <TableRow
                  v-for="deployment in deployments"
                  :key="deployment.id"
                  class="transition-colors duration-200 hover:bg-muted/40"
                >
                <TableCell>
                  <RouterLink
                    :to="`/deployments/${deployment.id}`"
                    class="font-medium hover:underline"
                  >
                    {{ deployment.site?.domain ?? deployment.siteId }}
                  </RouterLink>
                </TableCell>
                <TableCell>{{ deployment.branch ?? '—' }}</TableCell>
                <TableCell>
                  <StatusBadge :status="deployment.status" type="deployment" />
                </TableCell>
                <TableCell>{{ deployment.triggeredBy?.name ?? '—' }}</TableCell>
                <TableCell>{{ formatDurationSeconds(deployment.duration) }}</TableCell>
                <TableCell>{{ formatRelativeTime(deployment.createdAt) }}</TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>
        </div>
      </div>

      <div class="space-y-6">
        <div>
          <h2 class="section-label">
            Server Status
          </h2>
          <ul class="panel divide-y">
            <template v-if="isLoading">
              <li
                v-for="row in 4"
                :key="row"
                class="flex items-center justify-between gap-3 px-4 py-3"
              >
                <div class="min-w-0 flex-1 space-y-2">
                  <div class="h-4 w-32 animate-pulse rounded bg-muted" />
                  <div class="h-5 w-20 animate-pulse rounded bg-muted" />
                </div>
                <div class="h-5 w-16 animate-pulse rounded-full bg-muted" />
              </li>
            </template>
            <li v-else-if="servers.length === 0 && !showGettingStarted">
              <EmptyState
                :icon="ServerIcon"
                title="No servers registered"
                description="Add a server to start provisioning sites and collecting metrics."
                class="border-0 bg-transparent shadow-none hover:shadow-none"
              />
            </li>
            <template v-else>
              <li v-for="server in servers" :key="server.id">
              <RouterLink
                :to="`/servers/${server.id}`"
                class="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
              >
                <div>
                  <p class="font-medium">
                    {{ server.hostname }}
                  </p>
                  <EnvironmentBadge
                    :environment="server.environment?.label ?? server.environment?.name ?? '—'"
                    :is-production="server.environment?.isProduction ?? false"
                    class="mt-1"
                  />
                </div>
                <StatusBadge :status="server.status" type="server" />
              </RouterLink>
              </li>
            </template>
          </ul>
        </div>

        <div class="panel space-y-4 p-4">
          <h2 class="section-label mb-0">
            Quick Deploy
          </h2>
          <p v-if="!isLoading && sites.length === 0" class="text-sm text-muted-foreground">
            Add a site to a server to enable quick deploy.
          </p>
          <ProductionWarningBanner
            variant="inline"
            :resource-name="selectedSite?.domain ?? ''"
            :is-production="isQuickDeployProduction"
            data-testid="quick-deploy-production-warning"
          />
          <div class="space-y-2">
            <Label>Site</Label>
            <Select v-model="quickDeploySiteId">
              <SelectTrigger data-testid="quick-deploy-site-select">
                <SelectValue placeholder="Select site" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="site in sites"
                  :key="site.id"
                  :value="site.id"
                >
                  {{ site.domain }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="quick-deploy-branch">Branch</Label>
            <Input id="quick-deploy-branch" v-model="quickDeployBranch" />
          </div>
          <Button
            type="button"
            class="w-full"
            :disabled="isDeploying || quickDeploySiteId === ''"
            @click="handleQuickDeploy"
          >
            {{ isDeploying ? 'Deploying…' : 'Deploy' }}
          </Button>
        </div>
      </div>
    </section>
    </template>
  </div>
</template>
