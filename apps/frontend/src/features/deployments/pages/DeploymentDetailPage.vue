<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { GitBranchIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BackLink from '@/components/layout/BackLink.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  approvePipelineRun,
  cancelDeployment,
  fetchDeployment,
  rejectPipelineRun,
  rollbackDeployment,
} from '@/features/deployments/api'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import DeploymentLogViewer from '@/features/deployments/components/DeploymentLogViewer.vue'
import RollbackDialog from '@/features/deployments/components/RollbackDialog.vue'
import type { DeploymentCompletedPayload, DeploymentDetail } from '@/features/deployments/types'
import {
  formatDurationSeconds,
  formatRelativeTime,
  shortCommitHash,
} from '@/lib/format'
import { DeploymentStatus } from '@/types'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const deployment = ref<DeploymentDetail | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isRollbackDialogOpen = ref(false)
const isSubmittingRollback = ref(false)
const isCancelling = ref(false)
const isApproving = ref(false)
const isRejecting = ref(false)

const deploymentId = computed(() => String(route.params.id))

const siteBackLink = computed(() => {
  const serverId = deployment.value?.site?.serverId

  if (serverId === undefined) {
    return '/servers'
  }

  return `/servers/${serverId}/sites/${deployment.value?.siteId}`
})

const canRollback = computed(
  () => deployment.value?.isRollbackable === true,
)

const canCancel = computed(() => {
  const status = deployment.value?.status

  return status === DeploymentStatus.Pending || status === DeploymentStatus.Running
})

const isProduction = computed(() => deployment.value?.site?.isProduction ?? false)

const environmentName = computed(
  () => (isProduction.value ? 'production' : 'development'),
)

const isAwaitingApproval = computed(
  () => deployment.value?.status === DeploymentStatus.AwaitingApproval,
)

const canApprove = computed(
  () => isAwaitingApproval.value
    && deployment.value?.pipelineRunId !== null
    && deployment.value?.pipelineRunId !== undefined
    && authStore.isAdmin,
)

async function loadDeployment(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    deployment.value = await fetchDeployment(deploymentId.value)

    if (route.query.rollback === '1' && deployment.value.isRollbackable) {
      isRollbackDialogOpen.value = true
    }
  } catch {
    loadError.value = 'Unable to load deployment.'
  } finally {
    isLoading.value = false
  }
}

async function handleRollback(reason: string | undefined): Promise<void> {
  if (deployment.value === null) {
    return
  }

  isSubmittingRollback.value = true

  try {
    const response = await rollbackDeployment(deployment.value.id, { reason })
    isRollbackDialogOpen.value = false
    await router.push(`/deployments/${response.data.id}`)
  } catch {
    toast.error('Rollback failed', {
      description: 'Please try again or check your permissions.',
    })
  } finally {
    isSubmittingRollback.value = false
  }
}

async function handleCancel(): Promise<void> {
  if (deployment.value === null) {
    return
  }

  isCancelling.value = true

  try {
    deployment.value = await cancelDeployment(deployment.value.id)
    toast.success('Deployment cancelled')
  } catch {
    toast.error('Unable to cancel deployment')
  } finally {
    isCancelling.value = false
  }
}

async function handleApprove(): Promise<void> {
  const pipelineRunId = deployment.value?.pipelineRunId

  if (pipelineRunId === null || pipelineRunId === undefined) {
    return
  }

  isApproving.value = true

  try {
    await approvePipelineRun(pipelineRunId)
    await loadDeployment()
    toast.success('Pipeline approved. Execution resumed.')
  } catch {
    toast.error('Unable to approve pipeline.')
  } finally {
    isApproving.value = false
  }
}

async function handleReject(): Promise<void> {
  const pipelineRunId = deployment.value?.pipelineRunId

  if (pipelineRunId === null || pipelineRunId === undefined) {
    return
  }

  isRejecting.value = true

  try {
    await rejectPipelineRun(pipelineRunId, { reason: 'Rejected from deployment detail.' })
    await loadDeployment()
    toast.success('Pipeline rejected.')
  } catch {
    toast.error('Unable to reject pipeline.')
  } finally {
    isRejecting.value = false
  }
}

async function handleDeploymentCompleted(payload: DeploymentCompletedPayload): Promise<void> {
  if (deployment.value !== null) {
    deployment.value = {
      ...deployment.value,
      status: payload.status,
      duration: payload.duration,
      finishedAt: new Date().toISOString(),
    }
  }

  await loadDeployment()
}

onMounted(() => {
  void loadDeployment()
})
</script>

<template>
  <div class="space-y-8">
    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-6 w-40" />
      <Skeleton class="h-10 w-full max-w-3xl" />
      <Skeleton class="min-h-96 w-full rounded-lg" />
    </div>

    <template v-else-if="deployment !== null">
      <BackLink
        :to="siteBackLink"
        label="Back to site"
      />

      <div
        v-if="isProduction"
        class="-mx-4 lg:-mx-8"
      >
        <ProductionWarningBanner
          :resource-name="deployment.site?.domain ?? 'site'"
          :is-production="true"
        />
      </div>

      <div
        v-if="isAwaitingApproval"
        class="panel flex flex-col gap-4 border-yellow-500/40 bg-yellow-500/5 p-4 sm:flex-row sm:items-center sm:justify-between"
        data-testid="approval-banner"
      >
        <div>
          <p class="text-sm font-semibold text-foreground">
            Approval required
          </p>
          <p class="mt-1 text-sm text-muted-foreground">
            This deployment is paused at a pipeline approval gate. An authorized approver must continue or reject it.
          </p>
        </div>
        <div v-if="canApprove" class="flex shrink-0 gap-2">
          <Button
            type="button"
            variant="outline"
            data-testid="reject-pipeline-button"
            :disabled="isRejecting || isApproving"
            @click="handleReject"
          >
            Reject
          </Button>
          <Button
            type="button"
            data-testid="approve-pipeline-button"
            :disabled="isApproving || isRejecting"
            @click="handleApprove"
          >
            Approve &amp; continue
          </Button>
        </div>
      </div>

      <div class="flex flex-col gap-4 border-b pb-8 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-2">
          <h1 class="page-title">
            {{ deployment.site?.domain ?? 'Deployment' }}
          </h1>
          <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <EnvironmentBadge
              v-if="deployment.site !== null"
              :environment="environmentName"
              :is-production="isProduction"
            />
            <span class="text-muted-foreground">·</span>
            <span class="inline-flex items-center gap-1">
              <GitBranchIcon class="size-3.5" />
              {{ deployment.branch ?? deployment.site?.deployBranch ?? 'main' }}
            </span>
            <span class="font-mono text-xs">{{ shortCommitHash(deployment.commitHash) }}</span>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <StatusBadge :status="deployment.status" type="deployment" />
            <span class="text-sm text-muted-foreground">
              Triggered by {{ deployment.triggeredBy?.name ?? 'System' }}
            </span>
            <span class="text-sm text-muted-foreground">
              {{ formatRelativeTime(deployment.startedAt ?? deployment.createdAt) }}
            </span>
            <span class="text-sm text-muted-foreground">
              Duration: {{ formatDurationSeconds(deployment.duration) }}
            </span>
          </div>
        </div>

        <div class="flex shrink-0 gap-2">
          <Button
            v-if="canRollback"
            variant="destructive"
            data-testid="rollback-button"
            @click="isRollbackDialogOpen = true"
          >
            Rollback
          </Button>
          <Button
            v-if="canCancel"
            variant="outline"
            data-testid="cancel-button"
            :disabled="isCancelling"
            @click="handleCancel"
          >
            Cancel
          </Button>
        </div>
      </div>

      <DeploymentLogViewer
        :deployment-id="deployment.id"
        @completed="handleDeploymentCompleted"
        @approval-required="() => toast.warning('Deployment approval required', {
          description: 'An approver must confirm before this deployment can continue.',
        })"
      />
    </template>

    <div v-else class="panel border-dashed p-8 text-center">
      <p class="text-muted-foreground">
        {{ loadError ?? 'Deployment not found.' }}
      </p>
    </div>

    <RollbackDialog
      v-model:open="isRollbackDialogOpen"
      :deployment="deployment"
      :is-production="isProduction"
      :is-submitting="isSubmittingRollback"
      @submit="handleRollback"
    />
  </div>
</template>
