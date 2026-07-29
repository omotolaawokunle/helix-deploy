<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronDownIcon, PlusIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadErrorPanel from '@/components/common/LoadErrorPanel.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TableSkeleton from '@/components/common/TableSkeleton.vue'
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
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useDnsProviderConnections } from '@/features/integrations/composables/useDnsProviderConnections'
import {
  buildHostnameFromPrefix,
  fetchProjectDnsZones,
} from '@/features/integrations/api'
import { DNS_PROVIDER_LABELS } from '@/features/integrations/types'
import type { ProjectDnsZone } from '@/features/integrations/types'
import { createSite, fetchServerSites, type CreateSitePayload } from '@/features/sites/api'
import { buildCreateSitePayload } from '@/features/sites/buildCreateSitePayload'
import {
  patchSiteDnsSslFromBroadcast,
  useSiteProvisioningChannel,
} from '@/features/sites/composables/useSiteProvisioningChannel'
import { fetchProjects } from '@/features/servers/api'
import type { ProjectOption } from '@/features/servers/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'
import { useRealtimeStore } from '@/stores/useRealtimeStore'
import type { DockerBuildMode, Site, SiteDeployMode } from '@/types'

interface Props {
  serverId: string
  projectId?: string | null
}

const props = defineProps<Props>()

const { orgId } = useActiveOrg()
const realtimeStore = useRealtimeStore()
const { ensureLoaded: ensureDnsConnectionsLoaded, connectionFlags: dnsProviderConnected } =
  useDnsProviderConnections(orgId)

const sites = ref<Site[]>([])
const projects = ref<ProjectOption[]>([])
const selectedProjectId = ref<string | undefined>(undefined)
const projectDnsZones = ref<ProjectDnsZone[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isAddOpen = ref(false)
const isSubmitting = ref(false)
const apiError = ref<string | null>(null)

const domainInputMode = ref<'prefix' | 'fqdn'>('fqdn')
const domain = ref('')
const projectDnsZoneId = ref<string | undefined>(undefined)
const subdomainPrefix = ref('staging')
const autoCreateDns = ref(false)
const enableSsl = ref(false)
const includeWwwAlias = ref(false)
const sslChallenge = ref<'http-01' | 'dns-01'>('http-01')
const showAdvancedSsl = ref(false)

const deployMode = ref<SiteDeployMode>('git')
const runtime = ref('php')
const phpVersion = ref('8.3')
const appPort = ref('3000')
const deployBranch = ref('main')
const repositoryUrl = ref('')
const dockerBuildMode = ref<DockerBuildMode>('build')
const dockerImage = ref('')
const dockerRegistry = ref('')
const dockerComposePath = ref('docker-compose.yml')

const gitRuntimeOptions = [
  { value: 'php', label: 'PHP' },
  { value: 'nodejs', label: 'Node.js' },
  { value: 'python', label: 'Python' },
  { value: 'go', label: 'Go' },
  { value: 'static', label: 'Static' },
]

const isDockerDeployMode = computed(() => deployMode.value === 'docker')

const requiresAppPort = computed(() =>
  isDockerDeployMode.value || ['nodejs', 'python', 'go'].includes(runtime.value),
)

const requiresPhpVersion = computed(() => runtime.value === 'php')

const hasProjectZones = computed(() => projectDnsZones.value.length > 0)

const effectiveProjectId = computed(
  () => props.projectId ?? selectedProjectId.value ?? undefined,
)

const autoDnsDisabled = computed(() => !hasProjectZones.value)

const selectedZone = computed(
  () => projectDnsZones.value.find((zone) => zone.id === projectDnsZoneId.value) ?? null,
)

const isApexPrefix = computed(() => subdomainPrefix.value.trim() === '@')

const resolvedDomain = computed(() => {
  if (domainInputMode.value === 'prefix' && selectedZone.value !== null) {
    return buildHostnameFromPrefix(subdomainPrefix.value, selectedZone.value.baseDomain)
  }

  return domain.value.trim()
})

const canUseDns01 = computed(
  () => dnsProviderConnected.value.cloudflare || dnsProviderConnected.value.digitalocean,
)

const dns01Label = computed(() => {
  if (selectedZone.value !== null) {
    return `DNS-01 (${DNS_PROVIDER_LABELS[selectedZone.value.dnsProvider]})`
  }

  if (dnsProviderConnected.value.digitalocean && !dnsProviderConnected.value.cloudflare) {
    return 'DNS-01 (DigitalOcean)'
  }

  if (dnsProviderConnected.value.cloudflare && !dnsProviderConnected.value.digitalocean) {
    return 'DNS-01 (Cloudflare)'
  }

  return 'DNS-01 (DNS provider)'
})

const canSubmit = computed(() => {
  if (resolvedDomain.value === '') {
    return false
  }

  if (domainInputMode.value === 'prefix' && projectDnsZoneId.value === undefined) {
    return false
  }

  return true
})

async function loadProjectDnsContext(): Promise<void> {
  projectDnsZones.value = []

  const projectId = effectiveProjectId.value

  if (projectId === undefined || projectId === '') {
    domainInputMode.value = 'fqdn'
    return
  }

  try {
    const [zones] = await Promise.all([
      fetchProjectDnsZones(projectId),
      orgId.value !== null ? ensureDnsConnectionsLoaded() : Promise.resolve(null),
    ])

    projectDnsZones.value = zones
    projectDnsZoneId.value = zones[0]?.id

    if (zones.length > 0) {
      domainInputMode.value = 'prefix'
    }
  } catch {
    projectDnsZones.value = []
  }
}

async function loadProjects(): Promise<void> {
  if (props.projectId !== null && props.projectId !== undefined && props.projectId !== '') {
    return
  }

  if (orgId.value === null) {
    return
  }

  try {
    projects.value = await fetchProjects(orgId.value)
    selectedProjectId.value = projects.value[0]?.id
  } catch {
    projects.value = []
  }
}

async function loadSites(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    sites.value = await fetchServerSites(props.serverId)
  } catch {
    sites.value = []
    loadError.value = 'Unable to load sites for this server.'
  } finally {
    isLoading.value = false
  }
}

function resetForm(): void {
  domain.value = ''
  subdomainPrefix.value = 'staging'
  projectDnsZoneId.value = projectDnsZones.value[0]?.id
  domainInputMode.value = hasProjectZones.value ? 'prefix' : 'fqdn'
  autoCreateDns.value = false
  enableSsl.value = false
  includeWwwAlias.value = false
  sslChallenge.value = 'http-01'
  showAdvancedSsl.value = false
  deployMode.value = 'git'
  runtime.value = 'php'
  phpVersion.value = '8.3'
  appPort.value = '3000'
  deployBranch.value = 'main'
  repositoryUrl.value = ''
  dockerBuildMode.value = 'build'
  dockerImage.value = ''
  dockerRegistry.value = ''
  dockerComposePath.value = 'docker-compose.yml'
  apiError.value = null
}

function openAddSite(): void {
  resetForm()
  isAddOpen.value = true
}

async function handleCreate(): Promise<void> {
  isSubmitting.value = true
  apiError.value = null

  const payload: CreateSitePayload = buildCreateSitePayload({
    deployMode: deployMode.value,
    runtime: runtime.value,
    deployBranch: deployBranch.value,
    autoCreateDns: autoCreateDns.value,
    enableSsl: enableSsl.value,
    phpVersion: phpVersion.value,
    appPort: appPort.value,
    repositoryUrl: repositoryUrl.value,
    dockerBuildMode: dockerBuildMode.value,
    dockerImage: dockerImage.value,
    dockerRegistry: dockerRegistry.value,
    dockerComposePath: dockerComposePath.value,
    projectId: effectiveProjectId.value,
    sslChallenge: enableSsl.value && showAdvancedSsl.value && sslChallenge.value === 'dns-01'
      ? 'dns-01'
      : undefined,
    ...(domainInputMode.value === 'prefix'
      ? {
          subdomainPrefix: subdomainPrefix.value.trim(),
          projectDnsZoneId: projectDnsZoneId.value,
          includeWwwAlias: isApexPrefix.value && includeWwwAlias.value ? true : undefined,
        }
      : {
          domain: domain.value.trim(),
        }),
  })

  try {
    await createSite(props.serverId, payload)
    isAddOpen.value = false
    toast.success('Site provisioning started.')
    await loadSites()
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      apiError.value = Object.values(fieldErrors).flat().join(' ')
        || firstFieldError(fieldErrors, 'domain')
        || 'Unable to create site.'
    } else {
      apiError.value = 'Unable to create site.'
    }
  } finally {
    isSubmitting.value = false
  }
}

watch(isAddOpen, (open) => {
  if (open) {
    void loadProjects()
    void loadProjectDnsContext()
  } else {
    resetForm()
  }
})

watch(
  () => [props.projectId, selectedProjectId.value],
  () => {
    if (isAddOpen.value) {
      void loadProjectDnsContext()
    }
  },
)

watch(
  () => realtimeStore.serverInventoryRefreshId,
  (serverId) => {
    if (serverId === props.serverId) {
      void loadSites()
      realtimeStore.consumeServerInventoryRefresh(props.serverId)
    }
  },
)

watch(autoCreateDns, (enabled) => {
  if (!enabled) {
    includeWwwAlias.value = false
    return
  }

  if (autoDnsDisabled.value) {
    autoCreateDns.value = false
  }
})

watch(enableSsl, (enabled) => {
  if (!enabled) {
    showAdvancedSsl.value = false
    sslChallenge.value = 'http-01'
  }
})

watch(canUseDns01, (enabled) => {
  if (!enabled && sslChallenge.value === 'dns-01') {
    sslChallenge.value = 'http-01'
  }
})

onMounted(() => {
  void loadSites()

  useSiteProvisioningChannel(props.serverId, {
    onDnsSslStatusChanged: (payload) => {
      const site = sites.value.find((entry) => entry.id === payload.siteId)

      if (site !== undefined) {
        patchSiteDnsSslFromBroadcast(site, payload)
      }
    },
    onCreated: () => {
      void loadSites()
    },
  })
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <p class="text-sm text-muted-foreground">
        Sites hosted on this server.
      </p>
      <Button type="button" size="sm" @click="openAddSite">
        <PlusIcon class="mr-2 size-4" />
        Add site
      </Button>
    </div>

    <LoadErrorPanel
      v-if="!isLoading && loadError !== null"
      :message="loadError"
      @retry="loadSites"
    />

    <EmptyState
      v-else-if="!isLoading && sites.length === 0"
      title="No sites on this server"
      description="HelixDeploy scans nginx after SSH connects. Add a site manually if nothing was detected."
      :icon="PlusIcon"
      @action="openAddSite"
    >
      <PlusIcon class="mr-2 size-4" />
      Add site
    </EmptyState>

    <div v-else-if="isLoading || sites.length > 0" class="overflow-hidden panel">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Domain</TableHead>
            <TableHead>Branch</TableHead>
            <TableHead>Runtime</TableHead>
            <TableHead>DNS</TableHead>
            <TableHead>SSL</TableHead>
            <TableHead>Status</TableHead>
          </TableRow>
        </TableHeader>
        <TableSkeleton v-if="isLoading" :columns="6" :rows="4" />
        <TableBody v-else>
          <TableRow
            v-for="site in sites"
            :key="site.id"
            class="transition-colors duration-200 hover:bg-muted/40"
          >
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
            <TableCell>
              <StatusBadge
                v-if="site.autoCreateDns && site.dnsStatus"
                :status="site.dnsStatus"
                type="dns"
              />
              <span v-else class="text-xs text-muted-foreground">—</span>
            </TableCell>
            <TableCell>
              <StatusBadge
                v-if="site.enableSsl && site.sslStatus"
                :status="site.sslStatus"
                type="ssl"
              />
              <span v-else class="text-xs text-muted-foreground">—</span>
            </TableCell>
            <TableCell>
              <StatusBadge :status="site.status" type="site" />
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isAddOpen">
      <SheetContent side="right" class="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Add site</SheetTitle>
          <SheetDescription>
            Configure a new site. Provisioning runs in the background after you submit.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-5">
          <div
            v-if="props.projectId === null || props.projectId === undefined || props.projectId === ''"
            class="space-y-2"
          >
            <Label>Project</Label>
            <Select v-model="selectedProjectId">
              <SelectTrigger>
                <SelectValue placeholder="Select project for DNS zones" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="project in projects"
                  :key="project.id"
                  :value="project.id"
                >
                  {{ project.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
              Link a project to use assigned DNS zones when creating sites.
            </p>
          </div>

          <div v-if="hasProjectZones" class="space-y-3">
            <Label>Domain input</Label>
            <div class="flex gap-2">
              <Button
                type="button"
                size="sm"
                :variant="domainInputMode === 'prefix' ? 'default' : 'outline'"
                @click="domainInputMode = 'prefix'"
              >
                Prefix
              </Button>
              <Button
                type="button"
                size="sm"
                :variant="domainInputMode === 'fqdn' ? 'default' : 'outline'"
                @click="domainInputMode = 'fqdn'"
              >
                Full domain
              </Button>
            </div>

            <template v-if="domainInputMode === 'prefix'">
              <div class="space-y-2">
                <Label>Zone</Label>
                <Select v-model="projectDnsZoneId">
                  <SelectTrigger>
                    <SelectValue placeholder="Select zone" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="zone in projectDnsZones"
                      :key="zone.id"
                      :value="zone.id"
                    >
                      {{ zone.baseDomain }}
                      ({{ DNS_PROVIDER_LABELS[zone.dnsProvider] }})
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="space-y-2">
                <Label for="site-subdomain-prefix">Prefix</Label>
                <Input
                  id="site-subdomain-prefix"
                  v-model="subdomainPrefix"
                  placeholder="staging or @ for root"
                  autocomplete="off"
                />
                <p class="text-xs text-muted-foreground">
                  Use <span class="font-mono">@</span> for the apex domain (e.g. example.com).
                </p>
              </div>

              <p v-if="resolvedDomain !== ''" class="px-3 py-2 text-sm rounded-md bg-muted">
                <span class="text-muted-foreground">Hostname:</span>
                <span class="ml-2 font-medium">{{ resolvedDomain }}</span>
              </p>
            </template>
          </div>

          <div v-if="!hasProjectZones || domainInputMode === 'fqdn'" class="space-y-2">
            <Label for="site-domain">Domain</Label>
            <Input
              id="site-domain"
              v-model="domain"
              placeholder="app.example.com"
              autocomplete="off"
            />
          </div>

          <div class="p-4 space-y-3 border rounded-lg">
            <div class="flex items-start gap-3">
              <input
                id="site-auto-dns"
                v-model="autoCreateDns"
                type="checkbox"
                class="mt-1 rounded border-input"
                :disabled="autoDnsDisabled"
              >
              <div class="space-y-1">
                <Label for="site-auto-dns" class="font-medium cursor-pointer">
                  Auto-create DNS record
                </Label>
                <p class="text-xs text-muted-foreground">
                  Creates an A record in your DNS provider pointing to this server.
                </p>
                <p v-if="autoDnsDisabled" class="text-xs text-muted-foreground">
                  Assign a DNS zone to the selected project before enabling auto-create DNS.
                </p>
                <p v-else-if="selectedZone" class="text-xs text-muted-foreground">
                  Provider: {{ DNS_PROVIDER_LABELS[selectedZone.dnsProvider] }}
                </p>
              </div>
            </div>

            <div
              v-if="isApexPrefix && autoCreateDns && domainInputMode === 'prefix'"
              class="flex items-start gap-3 pl-4 ml-6 border-l"
            >
              <input
                id="site-www-alias"
                v-model="includeWwwAlias"
                type="checkbox"
                class="mt-1 rounded border-input"
              >
              <div class="space-y-1">
                <Label for="site-www-alias" class="cursor-pointer">
                  Also add www alias
                </Label>
                <p class="text-xs text-muted-foreground">
                  Adds www.{{ selectedZone?.baseDomain }} as an Nginx alias and DNS record.
                </p>
              </div>
            </div>
          </div>

          <div class="p-4 space-y-3 border rounded-lg">
            <div class="flex items-start gap-3">
              <input
                id="site-enable-ssl"
                v-model="enableSsl"
                type="checkbox"
                class="mt-1 rounded border-input"
              >
              <div class="space-y-1">
                <Label for="site-enable-ssl" class="font-medium cursor-pointer">
                  Enable SSL (Let's Encrypt)
                </Label>
                <p class="text-xs text-muted-foreground">
                  Issues a certificate after provisioning. HTTP redirects to HTTPS when active.
                </p>
              </div>
            </div>

            <div v-if="enableSsl" class="pl-4 ml-6 space-y-3 border-l">
              <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-medium transition-colors duration-200 text-primary hover:underline"
                @click="showAdvancedSsl = !showAdvancedSsl"
              >
                <ChevronDownIcon
                  class="size-3.5 transition-transform duration-200"
                  :class="{ 'rotate-180': showAdvancedSsl }"
                />
                {{ showAdvancedSsl ? 'Hide advanced' : 'Advanced validation' }}
              </button>

              <Transition name="collapse-down">
                <div v-if="showAdvancedSsl" class="space-y-2">
                  <Label>Challenge type</Label>
                  <Select v-model="sslChallenge">
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="http-01">
                        HTTP-01 (webroot)
                      </SelectItem>
                      <SelectItem value="dns-01" :disabled="!canUseDns01">
                        {{ dns01Label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <p v-if="!canUseDns01" class="text-xs text-muted-foreground">
                    Connect Cloudflare or DigitalOcean under
                    <RouterLink to="/settings/integrations" class="text-primary hover:underline">
                      Integrations
                    </RouterLink>
                    to use DNS-01 validation.
                  </p>
                </div>
              </Transition>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="site-deploy-mode">Deploy mode</Label>
            <Select
              :model-value="deployMode"
              @update:model-value="(value) => { deployMode = value as SiteDeployMode }"
            >
              <SelectTrigger id="site-deploy-mode" data-testid="deploy-mode-select">
                <SelectValue placeholder="Select deploy mode" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="git">
                  Git release
                </SelectItem>
                <SelectItem value="docker">
                  Docker
                </SelectItem>
              </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
              Git release deploys from a repository. Docker runs containers via compose on the server.
            </p>
          </div>

          <div v-if="!isDockerDeployMode" class="space-y-2" data-testid="git-runtime-section">
            <Label>Runtime</Label>
            <Select v-model="runtime">
              <SelectTrigger>
                <SelectValue placeholder="Select runtime" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in gitRuntimeOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <template v-if="isDockerDeployMode">
            <div class="space-y-4" data-testid="docker-deploy-section">
            <div class="space-y-2">
              <Label for="site-docker-build-mode">Docker build mode</Label>
              <Select
                :model-value="dockerBuildMode"
                @update:model-value="(value) => { dockerBuildMode = value as DockerBuildMode }"
              >
                <SelectTrigger id="site-docker-build-mode" data-testid="docker-build-mode-select">
                  <SelectValue placeholder="Select build mode" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="build">
                    Build from repository
                  </SelectItem>
                  <SelectItem value="pull">
                    Pull prebuilt image
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="site-docker-compose">Compose path</Label>
              <Input
                id="site-docker-compose"
                v-model="dockerComposePath"
                placeholder="docker-compose.yml"
              />
            </div>

            <template v-if="dockerBuildMode === 'pull'">
              <div class="space-y-2">
                <Label for="site-docker-image">Image</Label>
                <Input
                  id="site-docker-image"
                  v-model="dockerImage"
                  placeholder="ghcr.io/org/app:latest"
                />
              </div>

              <div class="space-y-2">
                <Label for="site-docker-registry">Registry</Label>
                <Input
                  id="site-docker-registry"
                  v-model="dockerRegistry"
                  placeholder="ghcr.io"
                />
              </div>
            </template>
            </div>
          </template>

          <div v-if="requiresPhpVersion" class="space-y-2">
            <Label for="site-php-version">PHP version</Label>
            <Input id="site-php-version" v-model="phpVersion" placeholder="8.3" />
          </div>

          <div v-if="requiresAppPort" class="space-y-2">
            <Label for="site-app-port">Application port</Label>
            <Input id="site-app-port" v-model="appPort" type="number" min="1" max="65535" />
          </div>

          <div v-if="!isDockerDeployMode || dockerBuildMode === 'build'" class="space-y-2">
            <Label for="site-deploy-branch">Deploy branch</Label>
            <Input id="site-deploy-branch" v-model="deployBranch" placeholder="main" />
          </div>

          <div v-if="!isDockerDeployMode || dockerBuildMode === 'build'" class="space-y-2">
            <Label for="site-repository-url">Repository URL</Label>
            <Input
              id="site-repository-url"
              v-model="repositoryUrl"
              placeholder="https://github.com/org/repo.git"
            />
          </div>

          <p v-if="apiError" class="text-sm text-destructive" role="alert">
            {{ apiError }}
          </p>
        </SheetBody>

        <SheetFooter>
          <Button
            type="button"
            class="w-full"
            :disabled="isSubmitting || !canSubmit"
            @click="handleCreate"
          >
            {{ isSubmitting ? 'Creating…' : 'Create site' }}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
