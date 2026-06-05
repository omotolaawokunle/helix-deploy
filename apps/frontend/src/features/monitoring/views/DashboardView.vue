<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useReducedMotionPolling } from '@/composables/useReducedMotionPolling'
import { toast } from 'vue-sonner'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
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
import { fetchOrgSites } from '@/features/sites/api'
import { formatDurationSeconds, formatRelativeTime } from '@/lib/format'
import type { DeploymentListItem } from '@/features/deployments/types'
import type { DashboardStats } from '@/features/monitoring/api'
import type { Server, Site } from '@/types'

const authStore = useAuthStore()

const stats = ref<DashboardStats | null>(null)
const servers = ref<Server[]>([])
const deployments = ref<DeploymentListItem[]>([])
const sites = ref<Site[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const quickDeploySiteId = ref('')

defineExpose({ quickDeploySiteId })
const quickDeployBranch = ref('')
const isDeploying = ref(false)

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
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  try {
    const [data, orgSites] = await Promise.all([
      loadDashboardData(orgId),
      fetchOrgSites(orgId),
    ])
    stats.value = data.stats
    servers.value = data.servers
    deployments.value = data.deployments
    sites.value = orgSites
    loadError.value = null
  } catch {
    loadError.value = 'Unable to load dashboard.'
    toast.error('Unable to load dashboard.')
  }
}

async function loadInitial(): Promise<void> {
  isLoading.value = true
  await refreshDashboard()
  isLoading.value = false
}

useReducedMotionPolling(refreshDashboard, 30_000)

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
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Dashboard"
      description="Organization overview — servers, deployments, and quick actions."
    />

    <p v-if="loadError !== null" class="text-sm text-destructive">
      {{ loadError }}
    </p>

    <section>
      <h2 class="section-label">
        Overview
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
          <dd class="text-sm font-semibold tabular-nums text-foreground">
            {{ row.value }}
          </dd>
        </div>
        <div v-if="isLoading && overviewRows.length === 0" class="px-4 py-3 text-sm text-muted-foreground">
          Loading overview…
        </div>
      </dl>
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
              <TableRow v-if="isLoading">
                <TableCell colspan="6" class="text-muted-foreground">
                  Loading…
                </TableCell>
              </TableRow>
              <TableRow v-else-if="deployments.length === 0">
                <TableCell colspan="6" class="text-muted-foreground">
                  No deployments yet.
                </TableCell>
              </TableRow>
              <TableRow
                v-for="deployment in deployments"
                :key="deployment.id"
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
            <li v-if="isLoading" class="px-4 py-3 text-sm text-muted-foreground">
              Loading…
            </li>
            <li v-else-if="servers.length === 0" class="px-4 py-3 text-sm text-muted-foreground">
              No servers registered.
            </li>
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
            Deploy
          </Button>
        </div>
      </div>
    </section>
  </div>
</template>
