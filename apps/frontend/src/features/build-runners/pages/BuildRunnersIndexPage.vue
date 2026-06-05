<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { CpuIcon, PlusIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchBuildRunners } from '@/features/build-runners/api'
import BuildRunnerCard from '@/features/build-runners/components/BuildRunnerCard.vue'
import PublicKeySuccessSheet from '@/features/servers/components/PublicKeySuccessSheet.vue'
import type { BuildRunner } from '@/features/build-runners/types'
import { useRealtimeStore } from '@/stores/useRealtimeStore'

const AddBuildRunnerModal = defineAsyncComponent(
  () => import('@/features/build-runners/components/AddBuildRunnerModal.vue'),
)

const authStore = useAuthStore()
const { orgId } = useActiveOrg()
const realtimeStore = useRealtimeStore()

const runners = ref<BuildRunner[]>([])
const isLoading = ref(false)
const hasFetched = ref(false)
const loadError = ref<string | null>(null)
const searchQuery = ref('')
const isAddModalOpen = ref(false)
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

async function loadRunners(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    runners.value = []

    return
  }

  isLoading.value = true
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

onMounted(() => {
  void loadRunners()
})

watch(
  () => realtimeStore.buildRunnersRefreshToken,
  () => {
    void loadRunners()
  },
)

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
      />
    </div>

    <AddBuildRunnerModal
      v-if="isAddModalOpen"
      v-model:open="isAddModalOpen"
      @registered="handleRegistered"
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
