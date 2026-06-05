<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { GitBranchIcon, PlusIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { createPipeline, fetchPipelines } from '@/features/pipelines/api'
import type { PipelineRecord } from '@/features/pipelines/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const pipelines = ref<PipelineRecord[]>([])
const isLoading = ref(true)
const fetchError = ref<string | null>(null)
const isCreateOpen = ref(false)
const isSubmitting = ref(false)
const pipelineName = ref('')
const pipelineDescription = ref('')

const canManage = computed(() => authStore.isAdmin)

const isEmpty = computed(
  () => !isLoading.value && fetchError.value === null && pipelines.value.length === 0,
)

function stepCount(pipeline: PipelineRecord): number {
  return pipeline.steps?.length ?? 0
}

async function loadPipelines(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isLoading.value = true
  fetchError.value = null

  try {
    pipelines.value = await fetchPipelines(activeOrgId)
  } catch {
    fetchError.value = 'Unable to load pipelines.'
  } finally {
    isLoading.value = false
  }
}

async function submitCreate(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || pipelineName.value.trim() === '') {
    return
  }

  isSubmitting.value = true

  try {
    const created = await createPipeline(activeOrgId, {
      name: pipelineName.value.trim(),
      description: pipelineDescription.value.trim() === ''
        ? null
        : pipelineDescription.value.trim(),
    })

    pipelines.value = [created, ...pipelines.value]
    isCreateOpen.value = false
    pipelineName.value = ''
    pipelineDescription.value = ''
    toast.success('Pipeline created.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to create pipeline.')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  void loadPipelines()
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Pipelines"
      description="Define ordered deploy stages for your applications."
    >
      <template v-if="canManage" #actions>
        <Button type="button" @click="isCreateOpen = true">
          <PlusIcon class="mr-2 size-4" />
          New Pipeline
        </Button>
      </template>
    </PageHeader>

    <div v-if="isLoading" class="space-y-3">
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-10 w-full" />
    </div>

    <div
      v-else-if="fetchError !== null"
      class="panel p-6 text-sm text-destructive"
    >
      {{ fetchError }}
    </div>

    <EmptyState
      v-else-if="isEmpty"
      title="No pipelines yet"
      description="Create a pipeline to orchestrate migrations, deploys, health checks, and approval gates."
      :icon="GitBranchIcon"
    >
      <Button v-if="canManage" type="button" @click="isCreateOpen = true">
        <PlusIcon class="mr-2 size-4" />
        New Pipeline
      </Button>
    </EmptyState>

    <div v-else class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Stages</TableHead>
            <TableHead>Sites</TableHead>
            <TableHead class="w-[120px]" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="pipeline in pipelines" :key="pipeline.id">
            <TableCell>
              <div>
                <RouterLink
                  :to="`/pipelines/${pipeline.id}`"
                  class="font-medium text-foreground hover:underline"
                >
                  {{ pipeline.name }}
                </RouterLink>
                <p
                  v-if="pipeline.description"
                  class="mt-0.5 text-sm text-muted-foreground"
                >
                  {{ pipeline.description }}
                </p>
              </div>
            </TableCell>
            <TableCell>{{ stepCount(pipeline) }}</TableCell>
            <TableCell>{{ pipeline.sitesCount ?? 0 }}</TableCell>
            <TableCell class="text-right">
              <Button variant="ghost" size="sm" as-child>
                <RouterLink :to="`/pipelines/${pipeline.id}`">
                  Configure
                </RouterLink>
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isCreateOpen">
      <SheetContent>
        <SheetHeader>
          <SheetTitle>New pipeline</SheetTitle>
          <SheetDescription>
            Name your pipeline. You can add stages on the next screen.
          </SheetDescription>
        </SheetHeader>
        <SheetBody>
          <form class="space-y-4" @submit.prevent="submitCreate">
            <div class="space-y-2">
              <Label for="pipeline-name">Name</Label>
              <Input
                id="pipeline-name"
                v-model="pipelineName"
                placeholder="Production release"
                required
              />
            </div>
            <div class="space-y-2">
              <Label for="pipeline-description">Description</Label>
              <Textarea
                id="pipeline-description"
                v-model="pipelineDescription"
                rows="3"
                placeholder="Optional notes about this pipeline"
              />
            </div>
          </form>
        </SheetBody>
        <SheetFooter>
          <Button
            type="button"
            :disabled="isSubmitting || pipelineName.trim() === ''"
            @click="submitCreate"
          >
            Create pipeline
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
