<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { useDocumentVisibility } from '@vueuse/core'
import { isAxiosError } from 'axios'
import { toast } from 'vue-sonner'
import { CpuIcon, PlusIcon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import {
  deleteBuildRunner,
  fetchBuildRunners,
  testBuildRunnerConnection,
} from '@/features/build-runners/api'
import BuildRunnerCard from '@/features/build-runners/components/BuildRunnerCard.vue'
import { patchBuildRunnerInList } from '@/features/build-runners/lib/patchBuildRunnerInList'
import PublicKeySuccessSheet from '@/features/servers/components/PublicKeySuccessSheet.vue'
import type { BuildRunner } from '@/features/build-runners/types'
import { useRealtimeStore } from '@/stores/useRealtimeStore'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const AddBuildRunnerModal = defineAsyncComponent(
  () => import('@/features/build-runners/components/AddBuildRunnerModal.vue'),
)

const EditBuildRunnerSheet = defineAsyncComponent(
  () => import('@/features/build-runners/components/EditBuildRunnerSheet.vue'),
)

const authStore = useAuthStore()
const { orgId } = useActiveOrg()
const realtimeStore = useRealtimeStore()
const documentVisibility = useDocumentVisibility()

const runners = ref<BuildRunner[]>([])
const isLoading = ref(false)
const hasFetched = ref(false)
const loadError = ref<string | null>(null)
const searchQuery = ref('')
const isAddModalOpen = ref(false)
const isEditSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const editingRunner = ref<BuildRunner | null>(null)
const deletingRunner = ref<BuildRunner | null>(null)
const testingRunnerIds = ref<Set<string>>(new Set())
const isDeleting = ref(false)
const showPublicKeySheet = ref(false)
const registeredPublicKey = ref('')
const registeredSshUser = ref('deploy')

const isSearchActive = computed(() => searchQuery.value.trim() !== '')

const isEmpty = computed(
  () => hasFetched.value && !isLoading.value && runners.value.length === 0 && !isSearchActive.value,
)

const isSearchEmpty = computed(
  () => hasFetched.value && !isLoading.value && runners.value.length === 0 && isSearchActive.value,
)

async function loadRunners(options: { silent?: boolean } = {}): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    runners.value = []

    return
  }

  if (!options.silent) {
    isLoading.value = true
  }

  loadError.value = null

  try {
    runners.value = await fetchBuildRunners(activeOrgId, {
      search: searchQuery.value,
    })
  } catch {
    runners.value = []
    loadError.value = 'Unable to load build runners.'
  } finally {
    isLoading.value = false
    hasFetched.value = true
  }
}

function applyLivePatch(): void {
  const patch = realtimeStore.buildRunnerPatch

  if (patch === null) {
    return
  }

  const result = patchBuildRunnerInList(runners.value, patch)

  if (result === 'missing') {
    void loadRunners({ silent: true })

    return
  }

  runners.value = result
}

onMounted(() => {
  void loadRunners()
})

watch(
  () => realtimeStore.buildRunnerPatchSeq,
  () => {
    applyLivePatch()
  },
)

watch(documentVisibility, (visibility, previousVisibility) => {
  if (
    visibility === 'visible'
    && previousVisibility === 'hidden'
    && realtimeStore.connectionStatus !== 'connected'
  ) {
    void loadRunners({ silent: true })
  }
})

async function handleSearchSubmit(): Promise<void> {
  await loadRunners()
}

async function handleRegistered(payload: { publicKey: string | null; sshUser: string }): Promise<void> {
  await loadRunners()

  const publicKey = payload.publicKey?.trim() ?? ''

  if (publicKey === '') {
    return
  }

  registeredPublicKey.value = publicKey
  registeredSshUser.value = payload.sshUser
  showPublicKeySheet.value = true
}

function openEditSheet(runner: BuildRunner): void {
  editingRunner.value = runner
  isEditSheetOpen.value = true
}

function handleRunnerUpdated(updated: BuildRunner): void {
  const index = runners.value.findIndex(runner => runner.id === updated.id)

  if (index >= 0) {
    runners.value[index] = updated
  }

  toast.success('Build runner updated.')
}

function openDeleteDialog(runner: BuildRunner): void {
  deletingRunner.value = runner
  isDeleteDialogOpen.value = true
}

async function handleDeleteRunner(): Promise<void> {
  if (deletingRunner.value === null) {
    return
  }

  const runnerId = deletingRunner.value.id
  isDeleting.value = true

  try {
    await deleteBuildRunner(runnerId)
    runners.value = runners.value.filter(runner => runner.id !== runnerId)
    isDeleteDialogOpen.value = false
    deletingRunner.value = null
    toast.success('Build runner deleted.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const slotError = fieldErrors !== null ? firstFieldError(fieldErrors, 'runner') : undefined

    toast.error(slotError ?? 'Unable to delete build runner.')
  } finally {
    isDeleting.value = false
  }
}

function isTestingRunner(runnerId: string): boolean {
  return testingRunnerIds.value.has(runnerId)
}

async function handleTestConnection(runner: BuildRunner): Promise<void> {
  if (testingRunnerIds.value.has(runner.id)) {
    return
  }

  testingRunnerIds.value = new Set([...testingRunnerIds.value, runner.id])

  const patched = patchBuildRunnerInList(runners.value, {
    runnerId: runner.id,
    status: 'connecting',
  })

  if (patched !== 'missing') {
    runners.value = patched
  }

  try {
    await testBuildRunnerConnection(runner.id)
    toast.success('Connection test started.')
  } catch (error) {
    const message = isAxiosError(error) && error.response?.status === 422
      ? 'Connection test could not be started.'
      : 'Connection test failed.'

    toast.error(message)
    void loadRunners({ silent: true })
  } finally {
    testingRunnerIds.value = new Set(
      [...testingRunnerIds.value].filter(id => id !== runner.id),
    )
  }
}

const deleteDialogDescription = computed(() => {
  if (deletingRunner.value === null) {
    return ''
  }

  const base = `This permanently removes ${deletingRunner.value.name} and its stored credentials.`

  if (deletingRunner.value.activeBuilds > 0) {
    return `${base} Wait for active builds to finish before deleting.`
  }

  return base
})

const canConfirmDelete = computed(
  () => deletingRunner.value !== null && deletingRunner.value.activeBuilds === 0 && !isDeleting.value,
)
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Build Runners"
      description="Dedicated hosts that compile artifacts before deployment."
    >
      <template v-if="authStore.isAdmin" #actions>
        <Button type="button" @click="isAddModalOpen = true">
          <PlusIcon class="mr-2 size-4" aria-hidden="true" />
          Add runner
        </Button>
      </template>
    </PageHeader>

    <form class="max-w-md" @submit.prevent="handleSearchSubmit">
      <Input
        v-model="searchQuery"
        placeholder="Search by name or IP…"
        aria-label="Search build runners"
      />
    </form>

    <div
      v-if="isLoading && !hasFetched"
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      data-testid="build-runners-loading"
    >
      <Skeleton v-for="index in 3" :key="index" class="h-44 rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel border-dashed p-8 text-center"
      data-testid="build-runners-error"
    >
      <p class="text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="loadRunners">
        Try again
      </Button>
    </div>

    <EmptyState
      v-else-if="isEmpty"
      :icon="CpuIcon"
      title="No build runners yet"
      description="Register a build runner to offload compilation from your deployment servers."
      data-testid="build-runners-empty"
      @action="isAddModalOpen = true"
    >
      <PlusIcon class="mr-2 size-4" aria-hidden="true" />
      Add runner
    </EmptyState>

    <div
      v-else-if="isSearchEmpty"
      class="panel border-dashed p-8 text-center"
      data-testid="build-runners-search-empty"
    >
      <p class="text-muted-foreground">
        No build runners match your search.
      </p>
      <Button
        type="button"
        variant="outline"
        class="mt-4"
        @click="searchQuery = ''; loadRunners()"
      >
        Clear search
      </Button>
    </div>

    <div
      v-else
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      data-testid="build-runners-grid"
    >
      <BuildRunnerCard
        v-for="runner in runners"
        :key="runner.id"
        :runner="runner"
        :can-manage="authStore.isAdmin"
        :is-testing-connection="isTestingRunner(runner.id)"
        @edit="openEditSheet(runner)"
        @delete="openDeleteDialog(runner)"
        @test-connection="handleTestConnection(runner)"
      />
    </div>

    <AddBuildRunnerModal
      v-if="isAddModalOpen"
      v-model:open="isAddModalOpen"
      @registered="handleRegistered"
    />

    <EditBuildRunnerSheet
      v-if="isEditSheetOpen"
      v-model:open="isEditSheetOpen"
      :runner="editingRunner"
      @updated="handleRunnerUpdated"
    />

    <ConfirmDestructiveDialog
      v-if="deletingRunner !== null"
      v-model:open="isDeleteDialogOpen"
      title="Delete build runner"
      :description="deleteDialogDescription"
      :confirm-text="deletingRunner.name"
      confirm-button-label="Delete runner"
      :can-confirm="canConfirmDelete"
      @confirm="handleDeleteRunner"
    />

    <PublicKeySuccessSheet
      v-model:open="showPublicKeySheet"
      :public-key="registeredPublicKey"
      :ssh-user="registeredSshUser"
      title="Build runner registered"
      description="Add this public key to the build runner. HelixDeploy will verify the connection automatically once the key is in place."
    />
  </div>
</template>
