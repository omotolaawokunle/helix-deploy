<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Badge } from '@/components/ui/badge'
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
import { Textarea } from '@/components/ui/textarea'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchBuildRunners } from '@/features/build-runners/api'
import BuildRunnerRuntimeWarningAlert from '@/features/build-runners/components/BuildRunnerRuntimeWarningAlert.vue'
import { evaluateBuildRunnerRuntimeCompatibility } from '@/features/build-runners/lib/buildRunnerRuntimeCompatibility'
import type { BuildRunner } from '@/features/build-runners/types'
import { fetchPipelines } from '@/features/pipelines/api'
import type { PipelineRecord } from '@/features/pipelines/types'
import {
  deleteGitProviderToken,
  deleteSite,
  fetchGitBranches,
  fetchGitProviders,
  fetchGitRepositories,
  storeGitProviderToken,
  rotateSiteWebhookSecret,
  setupLaravelWorkers,
  updateSite,
} from '@/features/sites/api'
import type { LaravelWorkerType } from '@/features/sites/api'
import {
  EXTERNAL_BUILD_STRATEGY_LABEL,
  EXTERNAL_BUILD_STRATEGY_V2_MESSAGE,
  SELECTABLE_SITE_BUILD_STRATEGY_OPTIONS,
} from '@/features/sites/constants'
import { PHP_VERSIONS } from '@/features/servers/types'
import type { DockerBuildMode, GitProviderType, Site, SiteBuildStrategy, SiteDeployMode } from '@/types'

interface Props {
  site: Site
  isProduction?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isProduction: false,
})

const emit = defineEmits<{
  updated: [site: Site]
}>()

const router = useRouter()
const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const deployBranch = ref('')
const repositoryUrl = ref('')
const repositoryProvider = ref<GitProviderType | 'none'>('none')
const providerToken = ref('')
const pipelines = ref<PipelineRecord[]>([])
const gitRepositories = ref<Array<{ id: string; fullName: string; cloneUrl: string; defaultBranch: string }>>([])
const gitBranches = ref<string[]>([])
const isLoadingPipelines = ref(false)
const hasLoadedPipelines = ref(false)
const isLoadingRepositories = ref(false)
const isLoadingBranches = ref(false)
const isSavingProviderToken = ref(false)
const gitCredentialConfigured = ref(false)
const pipelineId = ref<string | null>(null)
const preDeployScript = ref('')
const postDeployScript = ref('')
const preBuildScript = ref('')
const buildStrategy = ref<SiteBuildStrategy>('on_server')
const buildRunnerId = ref<string | null>(null)
const buildRunners = ref<BuildRunner[]>([])
const isLoadingBuildRunners = ref(false)
const hasLoadedBuildRunners = ref(false)
const runMigrations = ref(false)
const dockerImage = ref('')
const dockerRegistry = ref('')
const dockerComposePath = ref('')
const composeProjectName = ref('')
const deployMode = ref<SiteDeployMode>('git')
const dockerBuildMode = ref<DockerBuildMode>('build')
const phpVersion = ref('8.3')
const isSaving = ref(false)
const isDeleteDialogOpen = ref(false)
const autoDeployEnabled = ref(false)
const webhookUrl = ref<string | null>(null)
const revealedWebhookSecret = ref<string | null>(null)
const productionAutoDeployConfirmed = ref(false)
const isRotatingWebhookSecret = ref(false)
const isRotateSecretDialogOpen = ref(false)
const isWorkersSetupOpen = ref(false)
const isSettingUpWorkers = ref(false)
const workerType = ref<LaravelWorkerType>('horizon')

const providerOptions: Array<{ value: GitProviderType; label: string }> = [
  { value: 'github', label: 'GitHub' },
  { value: 'gitlab', label: 'GitLab' },
  { value: 'bitbucket', label: 'Bitbucket' },
]

const isExternalBuildStrategy = computed(() => props.site.buildStrategy === 'external')

const isAutoDeployEligible = computed((): boolean => {
  const hasRepo = props.site.repositoryUrl !== null && props.site.repositoryUrl !== ''
  const hasBranch = deployBranch.value.trim() !== ''

  if (deployMode.value === 'git') {
    return hasRepo
  }

  if (deployMode.value === 'docker' && dockerBuildMode.value === 'build') {
    return hasRepo && hasBranch
  }

  return false
})

const isDockerRuntimeSite = computed(() => props.site.runtime === 'docker')
const isPhpRuntimeSite = computed(() => (props.site.runtime as string) === 'php')

const canEnableAutoDeploy = computed(() => {
  if (!autoDeployEnabled.value) {
    return true
  }

  if (props.isProduction) {
    return productionAutoDeployConfirmed.value
  }

  return true
})

const autoDeployProviderHint = computed((): string => {
  const provider = repositoryProvider.value

  if (provider === 'gitlab') {
    return 'In GitLab, add a webhook with push events and paste the secret into the token field.'
  }

  if (provider === 'bitbucket') {
    return 'In Bitbucket, add a webhook for repository push events and use the secret below.'
  }

  return 'In GitHub, add a webhook with push events and paste the secret below.'
})

const providerTokenHint = computed((): string => {
  const provider = repositoryProvider.value

  if (provider === 'gitlab') {
    return 'GitLab PAT needs read_api and read_repository so private projects appear in the list.'
  }

  if (provider === 'bitbucket') {
    return 'Bitbucket app password needs Repositories: Read so private repos appear in the list.'
  }

  if (provider === 'github') {
    return 'GitHub classic PAT needs the repo scope (or a fine-grained token with access to your private repos).'
  }

  return 'Paste a personal access token with read access to private repositories.'
})

const deleteSiteDescription = computed(() => {
  const parts = [`This will permanently delete ${props.site.domain}. This cannot be undone.`]

  if (props.site.autoCreateDns && props.site.dnsRecordIds.length > 0) {
    parts.push('Managed Cloudflare DNS records for this site will also be removed.')
  }

  return parts.join(' ')
})

const selectedRepository = computed((): string | null => {
  const match = gitRepositories.value.find(repo => repo.cloneUrl === repositoryUrl.value)

  return match?.fullName ?? null
})

const buildRunnerRuntimeWarning = computed(() => evaluateBuildRunnerRuntimeCompatibility({
  siteRuntime: props.site.runtime,
  siteProjectId: props.site.projectId,
  buildStrategy: buildStrategy.value,
  buildRunnerId: buildRunnerId.value,
  buildRunners: buildRunners.value,
}))

watch(
  () => props.site,
  (site) => {
    deployBranch.value = site.deployBranch
    preDeployScript.value = site.preDeployScript ?? ''
    postDeployScript.value = site.postDeployScript ?? ''
    preBuildScript.value = site.preBuildScript ?? ''
    buildStrategy.value = site.buildStrategy ?? 'on_server'
    buildRunnerId.value = site.buildRunnerId
    runMigrations.value = site.runMigrations
    dockerImage.value = site.dockerImage ?? ''
    dockerRegistry.value = site.dockerRegistry ?? ''
    dockerComposePath.value = site.dockerComposePath ?? ''
    composeProjectName.value = site.composeProjectName ?? ''
    deployMode.value = site.deployMode
    dockerBuildMode.value = site.dockerBuildMode ?? 'build'
    phpVersion.value = site.phpVersion ?? '8.3'
    pipelineId.value = site.pipelineId
    repositoryUrl.value = site.repositoryUrl ?? ''
    repositoryProvider.value = site.repositoryProvider ?? 'none'
    gitCredentialConfigured.value = site.gitCredentialConfigured
    autoDeployEnabled.value = site.autoDeployEnabled
    webhookUrl.value = site.webhookUrl
  },
  { immediate: true },
)

watch(repositoryProvider, () => {
  void refreshGitMetadata()
})

watch(
  buildStrategy,
  (strategy) => {
    if (strategy === 'runner') {
      void loadBuildRunners()
    }
  },
  { immediate: true },
)

onMounted(() => {
  void Promise.all([
    loadPipelines(),
    refreshGitMetadata(),
  ])
})

async function loadBuildRunners(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || hasLoadedBuildRunners.value || isLoadingBuildRunners.value) {
    return
  }

  isLoadingBuildRunners.value = true

  try {
    buildRunners.value = await fetchBuildRunners(activeOrgId)
    hasLoadedBuildRunners.value = true
  } catch {
    buildRunners.value = []
  } finally {
    isLoadingBuildRunners.value = false
  }
}

async function loadPipelines(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || hasLoadedPipelines.value || isLoadingPipelines.value) {
    return
  }

  isLoadingPipelines.value = true

  try {
    pipelines.value = await fetchPipelines(activeOrgId)
    hasLoadedPipelines.value = true
  } catch {
    pipelines.value = []
  } finally {
    isLoadingPipelines.value = false
  }
}

async function refreshGitMetadata(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || repositoryProvider.value === 'none') {
    gitRepositories.value = []
    gitBranches.value = []

    return
  }

  try {
    const providers = await fetchGitProviders(activeOrgId)
    gitCredentialConfigured.value = providers.some(entry => entry.provider === repositoryProvider.value)
  } catch {
    gitCredentialConfigured.value = false
  }

  if (!gitCredentialConfigured.value) {
    gitRepositories.value = []
    gitBranches.value = []

    return
  }

  isLoadingRepositories.value = true

  try {
    gitRepositories.value = await fetchGitRepositories(activeOrgId, repositoryProvider.value)
    await loadBranchesForCurrentRepository()
  } catch {
    gitRepositories.value = []
    gitBranches.value = []
  } finally {
    isLoadingRepositories.value = false
  }
}

async function loadBranchesForCurrentRepository(): Promise<void> {
  const activeOrgId = orgId.value
  const fullName = selectedRepository.value

  if (
    activeOrgId === null
    || repositoryProvider.value === 'none'
    || fullName === null
    || !fullName.includes('/')
  ) {
    gitBranches.value = []

    return
  }

  const [owner, repo] = fullName.split('/', 2)
  isLoadingBranches.value = true

  try {
    const branches = await fetchGitBranches(activeOrgId, repositoryProvider.value, owner, repo)
    gitBranches.value = branches.map(branch => branch.name)
  } catch {
    gitBranches.value = []
  } finally {
    isLoadingBranches.value = false
  }
}

async function handleSaveProviderToken(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || repositoryProvider.value === 'none' || providerToken.value.trim() === '') {
    return
  }

  isSavingProviderToken.value = true

  try {
    await storeGitProviderToken(activeOrgId, {
      provider: repositoryProvider.value,
      token: providerToken.value.trim(),
    })
    providerToken.value = ''
    gitCredentialConfigured.value = true
    toast.success('Git provider token saved.')
    await refreshGitMetadata()
  } catch {
    toast.error('Unable to save provider token.')
  } finally {
    isSavingProviderToken.value = false
  }
}

async function handleRevokeProviderToken(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || repositoryProvider.value === 'none') {
    return
  }

  try {
    await deleteGitProviderToken(activeOrgId, repositoryProvider.value)
    gitCredentialConfigured.value = false
    gitRepositories.value = []
    gitBranches.value = []
    toast.success('Git provider token removed.')
  } catch {
    toast.error('Unable to remove provider token.')
  }
}

function handleRepositoryChange(fullName: string): void {
  const repository = gitRepositories.value.find(repo => repo.fullName === fullName)

  if (repository === undefined) {
    return
  }

  repositoryUrl.value = repository.cloneUrl

  if (deployBranch.value.trim() === '') {
    deployBranch.value = repository.defaultBranch
  }

  void loadBranchesForCurrentRepository()
}

async function handleSetupLaravelWorkers(): Promise<void> {
  isSettingUpWorkers.value = true

  try {
    const response = await setupLaravelWorkers(props.site.id, workerType.value)
    isWorkersSetupOpen.value = false
    toast.success(response.message, {
      description: 'Check the server Daemons and Cron tabs for status.',
    })
  } catch {
    toast.error('Unable to set up Laravel workers.')
  } finally {
    isSettingUpWorkers.value = false
  }
}

async function handleSave(): Promise<void> {
  if (autoDeployEnabled.value && !canEnableAutoDeploy.value) {
    toast.error('Confirm production auto deploy before saving.')

    return
  }

  isSaving.value = true

  try {
    const { site: updated, webhookSecret } = await updateSite(props.site.id, {
      deployBranch: deployBranch.value,
      autoDeployEnabled: autoDeployEnabled.value,
      preDeployScript: preDeployScript.value,
      postDeployScript: postDeployScript.value,
      preBuildScript: preBuildScript.value,
      buildStrategy: buildStrategy.value,
      buildRunnerId: buildStrategy.value === 'runner' ? buildRunnerId.value : null,
      runMigrations: runMigrations.value,
      dockerImage: dockerImage.value || null,
      dockerRegistry: dockerRegistry.value || null,
      dockerComposePath: dockerComposePath.value || null,
      composeProjectName: composeProjectName.value.trim() || null,
      ...(isDockerRuntimeSite.value
        ? {
            deployMode: deployMode.value,
            dockerBuildMode: deployMode.value === 'docker' ? dockerBuildMode.value : null,
          }
        : {}),
      ...(isPhpRuntimeSite.value
        ? {
            phpVersion: phpVersion.value,
          }
        : {}),
      pipelineId: pipelineId.value,
      repositoryUrl: repositoryUrl.value || null,
      repositoryProvider: repositoryProvider.value === 'none' ? null : repositoryProvider.value,
    })

    if (webhookSecret !== undefined) {
      revealedWebhookSecret.value = webhookSecret
    }

    webhookUrl.value = updated.webhookUrl
    emit('updated', updated)
    toast.success('Site settings saved.')
  } catch {
    toast.error('Unable to save site settings.')
  } finally {
    isSaving.value = false
  }
}

async function copyWebhookUrl(): Promise<void> {
  if (webhookUrl.value === null) {
    return
  }

  try {
    await navigator.clipboard.writeText(webhookUrl.value)
    toast.success('Webhook URL copied.')
  } catch {
    toast.error('Unable to copy webhook URL.')
  }
}

async function copyWebhookSecret(): Promise<void> {
  if (revealedWebhookSecret.value === null) {
    return
  }

  try {
    await navigator.clipboard.writeText(revealedWebhookSecret.value)
    toast.success('Webhook secret copied.')
  } catch {
    toast.error('Unable to copy webhook secret.')
  }
}

async function handleRotateWebhookSecret(): Promise<void> {
  isRotatingWebhookSecret.value = true

  try {
    const result = await rotateSiteWebhookSecret(props.site.id)
    revealedWebhookSecret.value = result.webhookSecret
    webhookUrl.value = result.webhookUrl
    toast.success('Webhook secret rotated. Update your git provider webhook configuration.')
  } catch {
    toast.error('Unable to rotate webhook secret.')
  } finally {
    isRotatingWebhookSecret.value = false
    isRotateSecretDialogOpen.value = false
  }
}

async function handleDelete(): Promise<void> {
  try {
    await deleteSite(props.site.id)
    toast.success('Site deleted.')
    await router.push(`/servers/${props.site.serverId}`)
  } catch {
    toast.error('Unable to delete site.')
  }
}
</script>

<template>
  <div class="space-y-8">
    <form class="panel space-y-4 p-6" @submit.prevent="handleSave">
      <h2 class="section-label">
        Repository
      </h2>

      <div class="space-y-2">
        <Label for="repository-provider">Git provider</Label>
        <Select
          :model-value="repositoryProvider"
          @update:model-value="(value) => { repositoryProvider = value as GitProviderType | 'none' }"
        >
          <SelectTrigger id="repository-provider">
            <SelectValue placeholder="Select provider" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">
              None / public URL only
            </SelectItem>
            <SelectItem
              v-for="option in providerOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div
        v-if="repositoryProvider !== 'none' && authStore.isAdmin"
        class="space-y-3 rounded-lg border p-4"
        data-testid="git-provider-token-panel"
      >
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium">Provider access token</span>
          <Badge :variant="gitCredentialConfigured ? 'default' : 'outline'">
            {{ gitCredentialConfigured ? 'Configured' : 'Not configured' }}
          </Badge>
        </div>
        <div class="space-y-2">
          <Label for="provider-token">Personal access token</Label>
          <Input
            id="provider-token"
            v-model="providerToken"
            type="password"
            autocomplete="off"
            placeholder="Paste PAT (never shown again)"
          />
          <p class="text-xs text-muted-foreground">
            {{ providerTokenHint }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button
            type="button"
            size="sm"
            :disabled="isSavingProviderToken || providerToken.trim() === ''"
            @click="handleSaveProviderToken"
          >
            {{ isSavingProviderToken ? 'Saving…' : 'Save token' }}
          </Button>
          <Button
            v-if="gitCredentialConfigured"
            type="button"
            size="sm"
            variant="outline"
            @click="handleRevokeProviderToken"
          >
            Remove token
          </Button>
        </div>
      </div>

      <div v-if="gitCredentialConfigured && gitRepositories.length > 0" class="space-y-2">
        <Label for="repository-picker">Repository</Label>
        <Select
          :model-value="selectedRepository ?? undefined"
          :disabled="isLoadingRepositories"
          @update:model-value="(value) => handleRepositoryChange(String(value))"
        >
          <SelectTrigger id="repository-picker">
            <SelectValue placeholder="Select repository" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="repository in gitRepositories"
              :key="repository.id"
              :value="repository.fullName"
            >
              {{ repository.fullName }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="space-y-2">
        <Label for="repository-url">Repository URL</Label>
        <Input
          id="repository-url"
          v-model="repositoryUrl"
          placeholder="https://github.com/org/repo.git"
        />
      </div>

      <div class="space-y-2">
        <Label for="deploy-branch">Deploy branch</Label>
        <Select
          v-if="gitBranches.length > 0"
          :model-value="deployBranch"
          :disabled="isLoadingBranches"
          @update:model-value="(value) => { deployBranch = String(value) }"
        >
          <SelectTrigger id="deploy-branch">
            <SelectValue placeholder="Select branch" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="branch in gitBranches"
              :key="branch"
              :value="branch"
            >
              {{ branch }}
            </SelectItem>
          </SelectContent>
        </Select>
        <Input
          v-else
          id="deploy-branch"
          v-model="deployBranch"
        />
      </div>

      <div
        v-if="isAutoDeployEligible"
        class="space-y-4 border-t pt-6"
        data-testid="auto-deploy-section"
      >
        <h2 class="section-label">
          Auto deploy
        </h2>

        <ProductionWarningBanner
          v-if="isProduction && autoDeployEnabled"
          :resource-name="site.domain"
          :is-production="true"
          variant="inline"
          message="Auto deploy on production will trigger deployments on every matching push."
        />

        <div class="flex items-center gap-3">
          <input
            id="auto-deploy-enabled"
            v-model="autoDeployEnabled"
            type="checkbox"
            class="rounded border-input"
            data-testid="auto-deploy-toggle"
          >
          <Label for="auto-deploy-enabled">Enable auto deploy on push</Label>
        </div>

        <div
          v-if="isProduction && autoDeployEnabled"
          class="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4"
          data-testid="production-auto-deploy-confirm"
        >
          <input
            id="production-auto-deploy-confirmed"
            v-model="productionAutoDeployConfirmed"
            type="checkbox"
            class="mt-0.5 rounded border-input"
            data-testid="production-auto-deploy-checkbox"
          >
          <Label for="production-auto-deploy-confirmed" class="font-normal leading-relaxed">
            I understand that pushes to the deploy branch will automatically deploy to production.
          </Label>
        </div>

        <div v-if="autoDeployEnabled && webhookUrl !== null" class="space-y-3">
          <div class="space-y-2">
            <Label for="webhook-url">Webhook URL</Label>
            <div class="flex gap-2">
              <Input
                id="webhook-url"
                :model-value="webhookUrl"
                readonly
                data-testid="webhook-url-input"
              />
              <Button type="button" variant="outline" @click="copyWebhookUrl">
                Copy
              </Button>
            </div>
          </div>

          <div v-if="revealedWebhookSecret !== null" class="space-y-2">
            <Label for="webhook-secret">Webhook secret</Label>
            <p class="text-sm text-muted-foreground">
              Copy this secret now. It will not be shown again.
            </p>
            <div class="flex gap-2">
              <Input
                id="webhook-secret"
                :model-value="revealedWebhookSecret"
                readonly
                data-testid="webhook-secret-input"
              />
              <Button type="button" variant="outline" @click="copyWebhookSecret">
                Copy
              </Button>
            </div>
          </div>

          <p class="text-sm text-muted-foreground">
            {{ autoDeployProviderHint }}
          </p>

          <Button
            v-if="site.hasWebhookSecret || webhookUrl !== null"
            type="button"
            variant="outline"
            data-testid="rotate-webhook-secret-button"
            @click="isRotateSecretDialogOpen = true"
          >
            Regenerate secret
          </Button>
        </div>
      </div>

      <h2 class="section-label pt-4">
        Build strategy
      </h2>
      <div
        v-if="isExternalBuildStrategy"
        class="space-y-3 rounded-lg border border-border bg-muted/30 p-4"
        data-testid="external-build-strategy-locked"
      >
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium">Where to build</span>
          <Badge variant="secondary">
            {{ EXTERNAL_BUILD_STRATEGY_LABEL }}
          </Badge>
        </div>
        <p class="text-sm text-muted-foreground">
          {{ EXTERNAL_BUILD_STRATEGY_V2_MESSAGE }}
        </p>
      </div>
      <div v-else class="space-y-2">
        <Label for="build-strategy">Where to build</Label>
        <Select
          :model-value="buildStrategy"
          @update:model-value="(value) => { buildStrategy = value as SiteBuildStrategy }"
        >
          <SelectTrigger id="build-strategy">
            <SelectValue placeholder="Select build strategy" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in SELECTABLE_SITE_BUILD_STRATEGY_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p class="text-sm text-muted-foreground">
          {{ SELECTABLE_SITE_BUILD_STRATEGY_OPTIONS.find(option => option.value === buildStrategy)?.description }}
        </p>
      </div>

      <div v-if="buildStrategy === 'runner'" class="space-y-2">
        <Label for="build-runner">Preferred build runner</Label>
        <div
          v-if="!isLoadingBuildRunners && buildRunners.length === 0"
          class="rounded-lg border border-dashed bg-muted/30 p-4 text-sm text-muted-foreground"
          data-testid="build-runners-empty-hint"
        >
          No build runners registered yet.
          <RouterLink to="/build-runners" class="text-primary hover:underline">
            Add a build runner
          </RouterLink>
          to use this strategy.
        </div>
        <Select
          v-else
          :model-value="buildRunnerId ?? 'auto'"
          :disabled="isLoadingBuildRunners"
          @update:model-value="(value) => { buildRunnerId = value === 'auto' ? null : String(value) }"
        >
          <SelectTrigger id="build-runner">
            <SelectValue placeholder="Auto-select from pool" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="auto">
              Auto-select from pool
            </SelectItem>
            <SelectItem
              v-for="runner in buildRunners"
              :key="runner.id"
              :value="runner.id"
            >
              {{ runner.name }} ({{ runner.availableSlots }} slots free)
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="buildRunners.length > 0" class="text-sm text-muted-foreground">
          <RouterLink to="/build-runners" class="text-primary hover:underline">
            Manage build runners
          </RouterLink>
        </p>
      </div>

      <BuildRunnerRuntimeWarningAlert
        v-if="buildRunnerRuntimeWarning !== null"
        :warning="buildRunnerRuntimeWarning"
      />

      <div v-if="buildStrategy === 'runner'" class="space-y-2">
        <Label for="pre-build-script">Pre-build script</Label>
        <p class="text-sm text-muted-foreground">
          Runs on the build runner after dependencies install and before the artifact is created.
        </p>
        <Textarea id="pre-build-script" v-model="preBuildScript" rows="6" class="font-mono text-sm" />
      </div>

      <h2 class="section-label pt-4">
        Deployment
      </h2>
      <div class="space-y-2">
        <Label for="pre-deploy-script">Pre-deploy script</Label>
        <p class="text-sm text-muted-foreground">
          Runs after the release is built and before it goes live.
        </p>
        <Textarea id="pre-deploy-script" v-model="preDeployScript" rows="6" class="font-mono text-sm" />
      </div>
      <div class="space-y-2">
        <Label for="post-deploy-script">Post-deploy script</Label>
        <p class="text-sm text-muted-foreground">
          Runs after the release is activated and services are reloaded.
        </p>
        <Textarea id="post-deploy-script" v-model="postDeployScript" rows="6" class="font-mono text-sm" />
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="runMigrations" type="checkbox" class="rounded border-input">
        Run migrations on deploy
      </label>

      <div class="space-y-2 pt-2">
        <Label for="site-pipeline">Pipeline</Label>
        <Select
          :model-value="pipelineId ?? 'none'"
          :disabled="isLoadingPipelines"
          @update:model-value="(value) => { pipelineId = value === 'none' ? null : String(value) }"
        >
          <SelectTrigger id="site-pipeline">
            <SelectValue placeholder="No pipeline" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">
              No pipeline
            </SelectItem>
            <SelectItem
              v-for="pipeline in pipelines"
              :key="pipeline.id"
              :value="pipeline.id"
            >
              {{ pipeline.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p class="text-sm text-muted-foreground">
          Optional custom pipeline for this site.
          <RouterLink to="/pipelines" class="text-primary hover:underline">
            Manage pipelines
          </RouterLink>
        </p>
      </div>

      <template v-if="isPhpRuntimeSite">
        <h2 class="section-label pt-4">
          PHP
        </h2>

        <div class="space-y-2" data-testid="php-version-section">
          <Label for="php-version">PHP version</Label>
          <Select
            :model-value="phpVersion"
            @update:model-value="(value) => { phpVersion = String(value) }"
          >
            <SelectTrigger id="php-version" data-testid="php-version-select">
              <SelectValue placeholder="Select PHP version" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="version in PHP_VERSIONS"
                :key="version"
                :value="version"
              >
                PHP {{ version }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p class="text-sm text-muted-foreground">
            Must match PHP-FPM on the server (e.g. php8.4-fpm). Re-apply the nginx config after changing so the PHP socket updates.
          </p>
        </div>

        <div class="space-y-3 rounded-lg border border-border p-4" data-testid="laravel-workers-section">
          <div class="space-y-1">
            <h3 class="text-sm font-medium">
              Laravel workers
            </h3>
            <p class="text-sm text-muted-foreground">
              Create a supervised queue process and the scheduler cron for this site on its server.
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <Button
              type="button"
              variant="outline"
              data-testid="laravel-workers-setup-button"
              @click="isWorkersSetupOpen = true"
            >
              Set up Laravel workers
            </Button>
            <RouterLink
              :to="{ path: `/servers/${props.site.serverId}`, query: { tab: 'daemons' } }"
              class="text-sm text-primary hover:underline"
            >
              View daemons
            </RouterLink>
            <RouterLink
              :to="{ path: `/servers/${props.site.serverId}`, query: { tab: 'cron' } }"
              class="text-sm text-primary hover:underline"
            >
              View cron jobs
            </RouterLink>
          </div>
        </div>
      </template>

      <h2 class="section-label pt-4">
        Docker
      </h2>

      <div
        v-if="isDockerRuntimeSite"
        class="grid gap-4 sm:grid-cols-2"
        data-testid="deploy-mode-section"
      >
        <div class="space-y-2 sm:col-span-2">
          <Label for="deploy-mode">Deploy mode</Label>
          <Select
            :model-value="deployMode"
            @update:model-value="(value) => { deployMode = value as SiteDeployMode }"
          >
            <SelectTrigger id="deploy-mode" data-testid="deploy-mode-select">
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
          <p class="text-sm text-muted-foreground">
            Imported Docker sites default to Docker deploy mode. Switch to Git release if this site uses a traditional release directory layout.
          </p>
        </div>

        <div v-if="deployMode === 'docker'" class="space-y-2 sm:col-span-2">
          <Label for="docker-build-mode">Docker build mode</Label>
          <Select
            :model-value="dockerBuildMode"
            @update:model-value="(value) => { dockerBuildMode = value as DockerBuildMode }"
          >
            <SelectTrigger id="docker-build-mode" data-testid="docker-build-mode-select">
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
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="docker-image">Image</Label>
          <Input id="docker-image" v-model="dockerImage" />
        </div>
        <div class="space-y-2">
          <Label for="docker-registry">Registry</Label>
          <Input id="docker-registry" v-model="dockerRegistry" />
        </div>
        <div class="space-y-2 sm:col-span-2">
          <Label for="docker-compose">Compose path</Label>
          <Input id="docker-compose" v-model="dockerComposePath" />
        </div>
        <div class="space-y-2 sm:col-span-2">
          <Label for="compose-project-name">Compose project name</Label>
          <Input
            id="compose-project-name"
            v-model="composeProjectName"
            placeholder="defaults to domain slug"
            data-testid="compose-project-name-input"
          />
          <p class="text-sm text-muted-foreground">
            Stable Docker Compose project (<code class="text-xs">-p</code>) so redeploys replace the same stack.
            Leave blank to use
            <code class="text-xs">{{ props.site.resolvedComposeProjectName }}</code>.
          </p>
        </div>
      </div>

      <Button type="submit" :disabled="isSaving">
        Save settings
      </Button>
    </form>

    <section class="rounded-lg border border-destructive/40 bg-destructive/5 p-6">
      <h2 class="text-sm font-semibold text-destructive">
        Danger Zone
      </h2>
      <p class="mt-2 text-sm text-muted-foreground">
        Permanently delete this site and its configuration from HelixDeploy.
      </p>
      <Button
        type="button"
        variant="destructive"
        class="mt-4"
        @click="isDeleteDialogOpen = true"
      >
        Delete Site
      </Button>
    </section>

    <ConfirmDestructiveDialog
      v-model:open="isDeleteDialogOpen"
      title="Delete site"
      :description="deleteSiteDescription"
      :confirm-text="site.domain"
      confirm-button-label="Delete site"
      @confirm="handleDelete"
    />

    <ConfirmDestructiveDialog
      v-model:open="isRotateSecretDialogOpen"
      title="Regenerate webhook secret"
      description="This invalidates the current webhook secret. You must update the secret in your git provider before the next push can trigger a deploy."
      confirm-text="regenerate"
      confirm-button-label="Regenerate secret"
      @confirm="handleRotateWebhookSecret"
    />

    <Sheet v-model:open="isWorkersSetupOpen">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Set up Laravel workers</SheetTitle>
          <SheetDescription>
            Creates a supervised queue process and the Laravel scheduler cron on this site's server.
          </SheetDescription>
        </SheetHeader>
        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label>Queue process</Label>
            <Select v-model="workerType">
              <SelectTrigger data-testid="laravel-worker-type-select">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="horizon">
                  Horizon
                </SelectItem>
                <SelectItem value="queue">
                  Queue worker
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <p class="text-sm text-muted-foreground">
            Horizon and queue workers should not run together for the same app. The scheduler cron runs every minute via
            <code class="text-xs">schedule:run</code>.
          </p>
        </SheetBody>
        <SheetFooter>
          <Button type="button" variant="outline" @click="isWorkersSetupOpen = false">
            Cancel
          </Button>
          <Button
            type="button"
            data-testid="laravel-workers-confirm-button"
            :disabled="isSettingUpWorkers"
            @click="handleSetupLaravelWorkers"
          >
            Set up workers
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
