<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { RocketIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { fetchSiteDeployments, triggerDeployment } from '@/features/deployments/api'
import type { DeploymentListItem } from '@/features/deployments/types'
import DeployNowModal from '@/features/sites/components/DeployNowModal.vue'
import {
  formatDurationSeconds,
  formatRelativeTime,
  shortCommitHash,
} from '@/lib/format'
import { DeploymentType, TeamRole, type Site } from '@/types'

interface Props {
  site: Site
  isProduction: boolean
  memberRole: TeamRole | null
}

const props = defineProps<Props>()

const router = useRouter()

const deployments = ref<DeploymentListItem[]>([])
const nextCursor = ref<string | null>(null)
const isLoading = ref(true)
const isLoadingMore = ref(false)
const isDeployModalOpen = ref(false)
const isDeploying = ref(false)

const latestDeployment = computed(() => deployments.value[0] ?? null)

const isEmpty = computed(
  () => !isLoading.value && deployments.value.length === 0,
)

const canRollbackInTable = computed(
  () => props.memberRole === TeamRole.Owner || props.memberRole === TeamRole.Admin,
)

function typeLabel(type: string): string {
  return type === DeploymentType.Rollback ? 'Rollback' : 'Deploy'
}

async function loadDeployments(append = false): Promise<void> {
  if (append) {
    isLoadingMore.value = true
  } else {
    isLoading.value = true
  }

  try {
    const response = await fetchSiteDeployments(props.site.id, {
      cursor: append ? nextCursor.value ?? undefined : undefined,
      per_page: 20,
    })

    deployments.value = append
      ? [...deployments.value, ...response.data]
      : response.data
    nextCursor.value = response.meta.next_cursor
  } catch {
    if (!append) {
      toast.error('Unable to load deployments')
    }
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

async function handleDeploy(branch: string): Promise<void> {
  isDeploying.value = true

  try {
    const response = await triggerDeployment(props.site.id, { branch })
    isDeployModalOpen.value = false
    await router.push(`/deployments/${response.data.id}`)
  } catch {
    toast.error('Deploy failed', {
      description: 'Another deployment may already be in progress.',
    })
  } finally {
    isDeploying.value = false
  }
}

function navigateToDeployment(deploymentId: string): void {
  void router.push(`/deployments/${deploymentId}`)
}

function truncateMessage(message: string | null, max = 48): string {
  if (message === null || message === '') {
    return ''
  }

  return message.length > max ? `${message.slice(0, max)}…` : message
}

onMounted(() => {
  void loadDeployments()
})
</script>

<template>
  <div class="space-y-6" data-testid="deployments-tab">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h3 class="text-lg font-medium">
          Deployments
        </h3>
        <p
          v-if="latestDeployment !== null"
          class="mt-1 text-sm text-muted-foreground"
          data-testid="latest-deployment-summary"
        >
          Latest:
          <span class="font-mono">{{ shortCommitHash(latestDeployment.commitHash) }}</span>
          <span v-if="latestDeployment.commitMessage">
            — {{ truncateMessage(latestDeployment.commitMessage) }}
          </span>
          ·
          {{ formatRelativeTime(latestDeployment.finishedAt ?? latestDeployment.createdAt) }}
          ·
          <StatusBadge :status="latestDeployment.status" type="deployment" />
        </p>
      </div>
      <Button
        v-if="!isEmpty"
        data-testid="deploy-now-button"
        @click="isDeployModalOpen = true"
      >
        Deploy Now
      </Button>
    </div>

    <EmptyState
      v-if="isEmpty"
      data-testid="deployments-empty-state"
      title="No deployments yet"
      description="Deploy your site to push the latest code and watch the live log output."
      :icon="RocketIcon"
      @action="isDeployModalOpen = true"
    >
      Deploy Now
    </EmptyState>

    <div v-else class="rounded-lg border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Status</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Branch</TableHead>
            <TableHead>Commit</TableHead>
            <TableHead>Triggered By</TableHead>
            <TableHead>Duration</TableHead>
            <TableHead>When</TableHead>
            <TableHead class="text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="8" class="py-8 text-center text-muted-foreground">
              Loading deployments…
            </TableCell>
          </TableRow>
          <TableRow
            v-for="deployment in deployments"
            v-else
            :key="deployment.id"
            class="cursor-pointer"
            :class="deployment.isActiveRelease ? 'ring-1 ring-inset ring-primary/40' : ''"
            data-testid="deployment-row"
            @click="navigateToDeployment(deployment.id)"
          >
            <TableCell>
              <StatusBadge :status="deployment.status" type="deployment" />
            </TableCell>
            <TableCell>
              <Badge variant="outline" class="capitalize">
                {{ typeLabel(deployment.type) }}
              </Badge>
            </TableCell>
            <TableCell>{{ deployment.branch ?? '—' }}</TableCell>
            <TableCell>
              <span class="font-mono text-xs">{{ shortCommitHash(deployment.commitHash) }}</span>
              <span
                v-if="deployment.commitMessage"
                class="ml-2 text-muted-foreground"
              >
                {{ truncateMessage(deployment.commitMessage) }}
              </span>
            </TableCell>
            <TableCell>{{ deployment.triggeredBy?.name ?? 'System' }}</TableCell>
            <TableCell>{{ formatDurationSeconds(deployment.duration) }}</TableCell>
            <TableCell>{{ formatRelativeTime(deployment.finishedAt ?? deployment.createdAt) }}</TableCell>
            <TableCell class="text-right" @click.stop>
              <Button
                v-if="deployment.isRollbackable && canRollbackInTable"
                variant="destructive"
                size="sm"
                data-testid="row-rollback-button"
                @click="void router.push(`/deployments/${deployment.id}?rollback=1`)"
              >
                Rollback
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div v-if="nextCursor !== null" class="flex justify-center">
      <Button
        variant="outline"
        data-testid="load-more-deployments"
        :disabled="isLoadingMore"
        @click="loadDeployments(true)"
      >
        {{ isLoadingMore ? 'Loading…' : 'Load more' }}
      </Button>
    </div>

    <DeployNowModal
      v-model:open="isDeployModalOpen"
      :site="site"
      :is-production="isProduction"
      :is-submitting="isDeploying"
      @submit="handleDeploy"
    />
  </div>
</template>
