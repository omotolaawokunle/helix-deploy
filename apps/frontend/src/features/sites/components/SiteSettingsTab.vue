<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
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
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchBuildRunners } from '@/features/build-runners/api'
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
  updateSite,
} from '@/features/sites/api'
import type { GitProviderType, Site, SiteBuildStrategy } from '@/types'

interface Props {
  site: Site
}

const props = defineProps<Props>()

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
const runMigrations = ref(false)
const dockerImage = ref('')
const dockerRegistry = ref('')
const dockerComposePath = ref('')
const isSaving = ref(false)
const isDeleteDialogOpen = ref(false)

const providerOptions: Array<{ value: GitProviderType; label: string }> = [
  { value: 'github', label: 'GitHub' },
  { value: 'gitlab', label: 'GitLab' },
  { value: 'bitbucket', label: 'Bitbucket' },
]

const buildStrategyOptions: Array<{ value: SiteBuildStrategy; label: string; description: string }> = [
  {
    value: 'on_server',
    label: 'On server',
    description: 'Clone and build directly on the deployment server.',
  },
  {
    value: 'runner',
    label: 'Build runner',
    description: 'Compile on a dedicated runner, then deploy the artifact.',
  },
  {
    value: 'external',
    label: 'External artifact',
    description: 'Supply a pre-built artifact from outside HelixDeploy.',
  },
]

const selectedRepository = computed((): string | null => {
  const match = gitRepositories.value.find(repo => repo.cloneUrl === repositoryUrl.value)

  return match?.fullName ?? null
})

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
    pipelineId.value = site.pipelineId
    repositoryUrl.value = site.repositoryUrl ?? ''
    repositoryProvider.value = site.repositoryProvider ?? 'none'
    gitCredentialConfigured.value = site.gitCredentialConfigured
  },
  { immediate: true },
)

watch(repositoryProvider, () => {
  void refreshGitMetadata()
})

onMounted(() => {
  void Promise.all([loadPipelines(), refreshGitMetadata(), loadBuildRunners()])
})

async function loadBuildRunners(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    buildRunners.value = []

    return
  }

  isLoadingBuildRunners.value = true

  try {
    buildRunners.value = await fetchBuildRunners(activeOrgId)
  } catch {
    buildRunners.value = []
  } finally {
    isLoadingBuildRunners.value = false
  }
}

async function loadPipelines(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isLoadingPipelines.value = true

  try {
    pipelines.value = await fetchPipelines(activeOrgId)
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

async function handleSave(): Promise<void> {
  isSaving.value = true

  try {
    const updated = await updateSite(props.site.id, {
      deployBranch: deployBranch.value,
      preDeployScript: preDeployScript.value,
      postDeployScript: postDeployScript.value,
      preBuildScript: preBuildScript.value,
      buildStrategy: buildStrategy.value,
      buildRunnerId: buildStrategy.value === 'runner' ? buildRunnerId.value : null,
      runMigrations: runMigrations.value,
      dockerImage: dockerImage.value || null,
      dockerRegistry: dockerRegistry.value || null,
      dockerComposePath: dockerComposePath.value || null,
      pipelineId: pipelineId.value,
      repositoryUrl: repositoryUrl.value || null,
      repositoryProvider: repositoryProvider.value === 'none' ? null : repositoryProvider.value,
    })
    emit('updated', updated)
    toast.success('Site settings saved.')
  } catch {
    toast.error('Unable to save site settings.')
  } finally {
    isSaving.value = false
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

      <h2 class="section-label pt-4">
        Build strategy
      </h2>
      <div class="space-y-2">
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
              v-for="option in buildStrategyOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p class="text-sm text-muted-foreground">
          {{ buildStrategyOptions.find(option => option.value === buildStrategy)?.description }}
        </p>
      </div>

      <div v-if="buildStrategy === 'runner'" class="space-y-2">
        <Label for="build-runner">Preferred build runner</Label>
        <Select
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
        <p class="text-sm text-muted-foreground">
          <RouterLink to="/build-runners" class="text-primary hover:underline">
            Manage build runners
          </RouterLink>
        </p>
      </div>

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

      <h2 class="section-label pt-4">
        Docker
      </h2>
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
      :description="`This will permanently delete ${site.domain}. This cannot be undone.`"
      :confirm-text="site.domain"
      confirm-button-label="Delete site"
      @confirm="handleDelete"
    />
  </div>
</template>
