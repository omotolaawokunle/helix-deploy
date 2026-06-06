<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import {
  ArrowDownIcon,
  ArrowUpIcon,
  PlusIcon,
  Trash2Icon,
} from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import BackLink from '@/components/layout/BackLink.vue'
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
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import {
  deletePipeline,
  fetchPipeline,
  updatePipeline,
} from '@/features/pipelines/api'
import {
  APPROVER_ROLES,
  PIPELINE_STEP_TYPES,
  type PipelineRecord,
  type PipelineStepInput,
  type PipelineStepType,
  type TeamRole,
} from '@/features/pipelines/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

interface EditableStep {
  clientId: string
  id?: string
  name: string
  type: PipelineStepType
  order: number
  config: Record<string, unknown>
  requiresApproval: boolean
  approverRole: TeamRole | null
  retryAttempts: number
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const pipeline = ref<PipelineRecord | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const pipelineName = ref('')
const pipelineDescription = ref('')
const steps = ref<EditableStep[]>([])
const isSaving = ref(false)

const isDeleteOpen = ref(false)
const isDeleting = ref(false)

const pipelineId = computed(() => String(route.params.id))
const canManage = computed(() => authStore.isAdmin)

function createClientId(): string {
  return `step-${crypto.randomUUID()}`
}

function defaultStep(order: number): EditableStep {
  return {
    clientId: createClientId(),
    name: 'New stage',
    type: 'deploy',
    order,
    config: {},
    requiresApproval: false,
    approverRole: null,
    retryAttempts: 0,
  }
}

function mapStepsFromPipeline(record: PipelineRecord): EditableStep[] {
  return (record.steps ?? [])
    .slice()
    .sort((left, right) => left.order - right.order)
    .map((step, index) => ({
      clientId: step.id,
      id: step.id,
      name: step.name,
      type: step.type,
      order: index,
      config: step.config ?? {},
      requiresApproval: step.requiresApproval,
      approverRole: step.approverRole,
      retryAttempts: step.retryAttempts,
    }))
}

function normalizeStepOrders(): void {
  steps.value = steps.value.map((step, index) => ({
    ...step,
    order: index,
  }))
}

function addStep(): void {
  steps.value = [...steps.value, defaultStep(steps.value.length)]
}

function removeStep(clientId: string): void {
  steps.value = steps.value.filter((step) => step.clientId !== clientId)
  normalizeStepOrders()
}

function moveStep(clientId: string, direction: -1 | 1): void {
  const index = steps.value.findIndex((step) => step.clientId === clientId)
  const targetIndex = index + direction

  if (index < 0 || targetIndex < 0 || targetIndex >= steps.value.length) {
    return
  }

  const reordered = [...steps.value]
  const [moved] = reordered.splice(index, 1)
  reordered.splice(targetIndex, 0, moved)
  steps.value = reordered
  normalizeStepOrders()
}

function configString(step: EditableStep, key: string): string {
  const value = step.config[key]
  return typeof value === 'string' ? value : ''
}

function configNumber(step: EditableStep, key: string): number {
  const value = step.config[key]
  return typeof value === 'number' ? value : 0
}

function setConfigString(step: EditableStep, key: string, value: string): void {
  step.config = {
    ...step.config,
    [key]: value,
  }
}

function setConfigNumber(step: EditableStep, key: string, value: number): void {
  step.config = {
    ...step.config,
    [key]: value,
  }
}

function buildStepPayload(): PipelineStepInput[] {
  return steps.value.map((step, index) => ({
    ...(step.id !== undefined ? { id: step.id } : {}),
    name: step.name.trim(),
    type: step.type,
    order: index,
    config: step.config,
    requiresApproval: step.requiresApproval,
    approverRole: step.approverRole,
    retryAttempts: step.retryAttempts,
  }))
}

async function loadPipeline(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    const record = await fetchPipeline(pipelineId.value)
    pipeline.value = record
    pipelineName.value = record.name
    pipelineDescription.value = record.description ?? ''
    steps.value = mapStepsFromPipeline(record)
  } catch {
    loadError.value = 'Unable to load pipeline.'
  } finally {
    isLoading.value = false
  }
}

async function handleSave(): Promise<void> {
  if (pipelineName.value.trim() === '') {
    return
  }

  isSaving.value = true

  try {
    const updated = await updatePipeline(pipelineId.value, {
      name: pipelineName.value.trim(),
      description: pipelineDescription.value.trim() === ''
        ? null
        : pipelineDescription.value.trim(),
      steps: buildStepPayload(),
    })

    pipeline.value = updated
    steps.value = mapStepsFromPipeline(updated)
    toast.success('Pipeline saved.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to save pipeline.')
  } finally {
    isSaving.value = false
  }
}

async function handleDelete(): Promise<void> {
  isDeleting.value = true

  try {
    await deletePipeline(pipelineId.value)
    toast.success('Pipeline deleted.')
    await router.push('/pipelines')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'pipeline')

    toast.error(message ?? 'Unable to delete pipeline.')
  } finally {
    isDeleting.value = false
    isDeleteOpen.value = false
  }
}

watch(pipelineId, () => {
  void loadPipeline()
})

onMounted(() => {
  void loadPipeline()
})
</script>

<template>
  <div class="space-y-8">
    <BackLink to="/pipelines" label="Pipelines" />

    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-8 w-64" />
      <Skeleton class="h-40 w-full" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel p-6 text-sm text-destructive"
    >
      {{ loadError }}
    </div>

    <template v-else-if="pipeline !== null">
      <PageHeader
        :title="pipeline.name"
        description="Configure ordered stages for this deployment pipeline."
      >
        <template v-if="canManage" #actions>
          <Button
            type="button"
            variant="destructive"
            @click="isDeleteOpen = true"
          >
            <Trash2Icon class="mr-2 size-4" />
            Delete
          </Button>
        </template>
      </PageHeader>

      <form class="space-y-8" @submit.prevent="handleSave">
        <section class="panel space-y-4 p-6">
          <h2 class="section-label">
            Details
          </h2>
          <div class="space-y-2">
            <Label for="pipeline-name">Name</Label>
            <Input
              id="pipeline-name"
              v-model="pipelineName"
              :disabled="!canManage"
              required
            />
          </div>
          <div class="space-y-2">
            <Label for="pipeline-description">Description</Label>
            <Textarea
              id="pipeline-description"
              v-model="pipelineDescription"
              rows="3"
              :disabled="!canManage"
            />
          </div>
        </section>

        <section class="panel space-y-4 p-6">
          <div class="flex items-center justify-between gap-4">
            <h2 class="section-label">
              Stages
            </h2>
            <Button
              v-if="canManage"
              type="button"
              variant="outline"
              size="sm"
              @click="addStep"
            >
              <PlusIcon class="mr-2 size-4" />
              Add stage
            </Button>
          </div>

          <p
            v-if="steps.length === 0"
            class="text-sm text-muted-foreground"
          >
            No stages yet. Add stages to define the execution order.
          </p>

          <div
            v-for="(step, index) in steps"
            :key="step.clientId"
            class="space-y-4 rounded-lg border border-border p-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-3">
              <p class="text-sm font-medium text-foreground">
                Stage {{ index + 1 }}
              </p>
              <div v-if="canManage" class="flex items-center gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  :disabled="index === 0"
                  @click="moveStep(step.clientId, -1)"
                >
                  <ArrowUpIcon class="size-4" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  :disabled="index === steps.length - 1"
                  @click="moveStep(step.clientId, 1)"
                >
                  <ArrowDownIcon class="size-4" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  @click="removeStep(step.clientId)"
                >
                  <Trash2Icon class="size-4" />
                </Button>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`step-name-${step.clientId}`">Name</Label>
                <Input
                  :id="`step-name-${step.clientId}`"
                  v-model="step.name"
                  :disabled="!canManage"
                />
              </div>
              <div class="space-y-2">
                <Label :for="`step-type-${step.clientId}`">Type</Label>
                <Select
                  :model-value="step.type"
                  :disabled="!canManage"
                  @update:model-value="(value) => { step.type = value as PipelineStepType }"
                >
                  <SelectTrigger :id="`step-type-${step.clientId}`">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="option in PIPELINE_STEP_TYPES"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`step-retries-${step.clientId}`">Retry attempts</Label>
                <Input
                  :id="`step-retries-${step.clientId}`"
                  v-model.number="step.retryAttempts"
                  type="number"
                  min="0"
                  max="10"
                  :disabled="!canManage"
                />
              </div>
              <div class="space-y-2">
                <Label class="flex items-center gap-2">
                  <input
                    v-model="step.requiresApproval"
                    type="checkbox"
                    class="rounded border-input"
                    :disabled="!canManage"
                  >
                  Requires approval
                </Label>
                <Select
                  v-if="step.requiresApproval || step.type === 'approve'"
                  :model-value="step.approverRole ?? undefined"
                  :disabled="!canManage"
                  @update:model-value="(value) => { step.approverRole = (value as TeamRole) ?? null }"
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Approver role" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="role in APPROVER_ROLES"
                      :key="role.value"
                      :value="role.value"
                    >
                      {{ role.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div v-if="step.type === 'health_check'" class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`health-url-${step.clientId}`">Health check URL</Label>
                <Input
                  :id="`health-url-${step.clientId}`"
                  :model-value="configString(step, 'url')"
                  placeholder="https://example.com/health"
                  :disabled="!canManage"
                  @update:model-value="(value) => setConfigString(step, 'url', String(value))"
                />
              </div>
              <div class="space-y-2">
                <Label :for="`health-timeout-${step.clientId}`">Timeout (seconds)</Label>
                <Input
                  :id="`health-timeout-${step.clientId}`"
                  :model-value="configNumber(step, 'timeout')"
                  type="number"
                  min="1"
                  :disabled="!canManage"
                  @update:model-value="(value) => setConfigNumber(step, 'timeout', Number(value))"
                />
              </div>
            </div>

            <div v-else-if="step.type === 'script'" class="space-y-2">
              <Label :for="`script-${step.clientId}`">Script</Label>
              <Textarea
                :id="`script-${step.clientId}`"
                :model-value="configString(step, 'script')"
                rows="5"
                class="font-mono text-sm"
                :disabled="!canManage"
                @update:model-value="(value) => setConfigString(step, 'script', String(value))"
              />
            </div>

            <div v-else-if="step.type === 'notify'" class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`notify-channel-${step.clientId}`">Channel</Label>
                <Input
                  :id="`notify-channel-${step.clientId}`"
                  :model-value="configString(step, 'channel')"
                  placeholder="slack, email, webhook"
                  :disabled="!canManage"
                  @update:model-value="(value) => setConfigString(step, 'channel', String(value))"
                />
              </div>
              <div class="space-y-2">
                <Label :for="`notify-message-${step.clientId}`">Message</Label>
                <Input
                  :id="`notify-message-${step.clientId}`"
                  :model-value="configString(step, 'message')"
                  :disabled="!canManage"
                  @update:model-value="(value) => setConfigString(step, 'message', String(value))"
                />
              </div>
            </div>

            <div v-else-if="step.type === 'migrate' || step.type === 'deploy'" class="space-y-2">
              <Label :for="`env-${step.clientId}`">Environment filter (optional)</Label>
              <Input
                :id="`env-${step.clientId}`"
                :model-value="configString(step, 'environment')"
                placeholder="production"
                :disabled="!canManage"
                @update:model-value="(value) => setConfigString(step, 'environment', String(value))"
              />
            </div>
          </div>
        </section>

        <div v-if="canManage" class="flex justify-end">
          <Button type="submit" :disabled="isSaving">
            Save pipeline
          </Button>
        </div>
      </form>

      <ConfirmDestructiveDialog
        v-model:open="isDeleteOpen"
        title="Delete pipeline"
        :description="`Delete ${pipeline.name}? Sites must be unlinked first.`"
        confirm-label="Delete pipeline"
        :confirm-text="pipeline.name"
        :is-loading="isDeleting"
        @confirm="handleDelete"
      />
    </template>
  </div>
</template>
