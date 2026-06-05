<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
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
import type { BuildRunner } from '@/features/build-runners/types'

const AddBuildRunnerModal = defineAsyncComponent(
  () => import('@/features/build-runners/components/AddBuildRunnerModal.vue'),
)

const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const runners = ref<BuildRunner[]>([])
const isLoading = ref(false)
const hasFetched = ref(false)
const searchQuery = ref('')
const isAddModalOpen = ref(false)

const isEmpty = computed(
  () => hasFetched.value && !isLoading.value && runners.value.length === 0,
)

async function loadRunners(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    runners.value = []

    return
  }

  isLoading.value = true

  try {
    runners.value = await fetchBuildRunners(activeOrgId, {
      search: searchQuery.value,
    })
  } catch {
    runners.value = []
  } finally {
    isLoading.value = false
    hasFetched.value = true
  }
}

onMounted(() => {
  void loadRunners()
})

async function handleSearchSubmit(): Promise<void> {
  await loadRunners()
}

async function handleRegistered(): Promise<void> {
  await loadRunners()
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
          <PlusIcon class="size-4" aria-hidden="true" />
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

    <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <Skeleton v-for="index in 3" :key="index" class="h-44 rounded-lg" />
    </div>

    <EmptyState
      v-else-if="isEmpty"
      :icon="CpuIcon"
      title="No build runners yet"
      description="Register a build runner to offload compilation from your deployment servers."
    >
      <Button
        v-if="authStore.isAdmin"
        type="button"
        @click="isAddModalOpen = true"
      >
        <PlusIcon class="size-4" aria-hidden="true" />
        Add runner
      </Button>
    </EmptyState>

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
      v-model:open="isAddModalOpen"
      @registered="handleRegistered"
    />
  </div>
</template>
