<script setup lang="ts">
import { ref, watch } from 'vue'
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
import { BUILD_RUNNER_RUNTIME_OPTIONS } from '@/features/build-runners/constants'
import { updateBuildRunner } from '@/features/build-runners/api'
import type { BuildRunner, BuildRunnerRuntime } from '@/features/build-runners/types'
import { fetchProjects } from '@/features/servers/api'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { extractFieldErrors } from '@/lib/validation-errors'

interface Props {
  open: boolean
  runner: BuildRunner | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  updated: [runner: BuildRunner]
}>()

const { orgId } = useActiveOrg()

const name = ref('')
const maxConcurrentBuilds = ref(2)
const cpuCores = ref<number | undefined>(undefined)
const ramGb = ref<number | undefined>(undefined)
const projectId = ref<string | undefined>(undefined)
const supportedRuntimes = ref<BuildRunnerRuntime[]>(['php'])
const projects = ref<Array<{ id: string; name: string }>>([])
const isSubmitting = ref(false)
const apiError = ref<string | null>(null)
const runtimeValidationError = ref<string | null>(null)

watch(
  () => orgId.value,
  async (id) => {
    if (id === null) {
      projects.value = []

      return
    }

    projects.value = await fetchProjects(id)
  },
  { immediate: true },
)

watch(
  () => [props.open, props.runner] as const,
  ([isOpen, runner]) => {
    if (!isOpen || runner === null) {
      return
    }

    name.value = runner.name
    maxConcurrentBuilds.value = runner.maxConcurrentBuilds
    cpuCores.value = runner.cpuCores ?? undefined
    ramGb.value = runner.ramGb ?? undefined
    projectId.value = runner.project?.id
    supportedRuntimes.value = [...runner.supportedRuntimes]
    apiError.value = null
    runtimeValidationError.value = null
  },
  { immediate: true },
)

function toggleRuntime(runtime: BuildRunnerRuntime): void {
  runtimeValidationError.value = null

  if (supportedRuntimes.value.includes(runtime)) {
    supportedRuntimes.value = supportedRuntimes.value.filter(entry => entry !== runtime)

    return
  }

  supportedRuntimes.value = [...supportedRuntimes.value, runtime]
}

async function handleSubmit(): Promise<void> {
  if (props.runner === null) {
    return
  }

  if (supportedRuntimes.value.length === 0) {
    runtimeValidationError.value = 'Select at least one supported runtime.'

    return
  }

  runtimeValidationError.value = null
  isSubmitting.value = true
  apiError.value = null

  try {
    const updated = await updateBuildRunner(props.runner.id, {
      name: name.value.trim(),
      maxConcurrentBuilds: maxConcurrentBuilds.value,
      cpuCores: cpuCores.value ?? null,
      ramGb: ramGb.value ?? null,
      supportedRuntimes: supportedRuntimes.value,
      projectId: projectId.value ?? null,
    })

    emit('updated', updated)
    emit('update:open', false)
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      const messages = Object.values(fieldErrors).reduce<string[]>(
        (accumulator, entry) => accumulator.concat(entry),
        [],
      )
      apiError.value = messages[0] ?? 'Validation failed.'
    } else {
      apiError.value = 'Unable to update build runner.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function handleOpenChange(value: boolean): void {
  emit('update:open', value)
}
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
      <SheetHeader>
        <SheetTitle>Edit build runner</SheetTitle>
        <SheetDescription>
          Update capacity, runtimes, and project scope. SSH connection settings cannot be changed here.
        </SheetDescription>
      </SheetHeader>

      <SheetBody v-if="runner !== null" class="space-y-4">
        <p v-if="apiError !== null" class="text-sm text-destructive">
          {{ apiError }}
        </p>

        <div class="rounded-lg border border-border bg-muted/30 px-3 py-2 font-mono text-xs text-muted-foreground">
          {{ runner.sshUser }}@{{ runner.ipAddress }}:{{ runner.sshPort }}
        </div>

        <div class="space-y-2">
          <Label for="edit-runner-name">Name</Label>
          <Input id="edit-runner-name" v-model="name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="edit-runner-slots">Max concurrent builds</Label>
            <Input
              id="edit-runner-slots"
              v-model.number="maxConcurrentBuilds"
              type="number"
              min="1"
              max="32"
            />
          </div>
          <div class="space-y-2">
            <Label for="edit-runner-project">Project scope</Label>
            <Select
              :model-value="projectId ?? 'none'"
              @update:model-value="(value) => { projectId = value === 'none' ? undefined : String(value) }"
            >
              <SelectTrigger id="edit-runner-project">
                <SelectValue placeholder="Organization-wide" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">
                  Organization-wide
                </SelectItem>
                <SelectItem
                  v-for="project in projects"
                  :key="project.id"
                  :value="project.id"
                >
                  {{ project.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="edit-runner-cpu">CPU cores</Label>
            <Input id="edit-runner-cpu" v-model.number="cpuCores" type="number" min="1" />
          </div>
          <div class="space-y-2">
            <Label for="edit-runner-ram">RAM (GB)</Label>
            <Input id="edit-runner-ram" v-model.number="ramGb" type="number" min="1" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Supported runtimes</Label>
          <div class="flex flex-wrap gap-2">
            <Button
              v-for="option in BUILD_RUNNER_RUNTIME_OPTIONS"
              :key="option.value"
              type="button"
              size="sm"
              :variant="supportedRuntimes.includes(option.value) ? 'default' : 'outline'"
              @click="toggleRuntime(option.value)"
            >
              {{ option.label }}
            </Button>
          </div>
          <p v-if="runtimeValidationError !== null" class="text-sm text-destructive">
            {{ runtimeValidationError }}
          </p>
        </div>
      </SheetBody>

      <SheetFooter>
        <Button
          type="button"
          variant="outline"
          @click="emit('update:open', false)"
        >
          Cancel
        </Button>
        <Button
          type="button"
          :disabled="isSubmitting || name.trim() === '' || supportedRuntimes.length === 0"
          @click="handleSubmit"
        >
          {{ isSubmitting ? 'Saving…' : 'Save changes' }}
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
