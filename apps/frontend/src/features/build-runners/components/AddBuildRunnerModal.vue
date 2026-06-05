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
import { Textarea } from '@/components/ui/textarea'
import PublicKeySuccessSheet from '@/features/servers/components/PublicKeySuccessSheet.vue'
import { registerBuildRunner } from '@/features/build-runners/api'
import type { BuildRunnerRuntime } from '@/features/build-runners/types'
import { fetchProjects } from '@/features/servers/api'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { extractFieldErrors } from '@/lib/validation-errors'

interface Props {
  open: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  registered: []
}>()

const { orgId } = useActiveOrg()

const name = ref('')
const ipAddress = ref('')
const sshPort = ref(22)
const sshUser = ref('deploy')
const authMethod = ref<'generate' | 'import'>('generate')
const privateKey = ref('')
const maxConcurrentBuilds = ref(2)
const cpuCores = ref<number | undefined>(undefined)
const ramGb = ref<number | undefined>(undefined)
const projectId = ref<string | undefined>(undefined)
const supportedRuntimes = ref<BuildRunnerRuntime[]>(['php'])
const projects = ref<Array<{ id: string; name: string }>>([])
const isSubmitting = ref(false)
const apiError = ref<string | null>(null)
const showPublicKeySheet = ref(false)
const registeredPublicKey = ref('')

const runtimeOptions: Array<{ value: BuildRunnerRuntime; label: string }> = [
  { value: 'php', label: 'PHP' },
  { value: 'nodejs', label: 'Node.js' },
  { value: 'python', label: 'Python' },
  { value: 'go', label: 'Go' },
  { value: 'static', label: 'Static' },
  { value: 'docker', label: 'Docker' },
]

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

function resetForm(): void {
  name.value = ''
  ipAddress.value = ''
  sshPort.value = 22
  sshUser.value = 'deploy'
  authMethod.value = 'generate'
  privateKey.value = ''
  maxConcurrentBuilds.value = 2
  cpuCores.value = undefined
  ramGb.value = undefined
  projectId.value = undefined
  supportedRuntimes.value = ['php']
  apiError.value = null
  runtimeValidationError.value = null
}

function toggleRuntime(runtime: BuildRunnerRuntime): void {
  runtimeValidationError.value = null

  if (supportedRuntimes.value.includes(runtime)) {
    supportedRuntimes.value = supportedRuntimes.value.filter(entry => entry !== runtime)

    return
  }

  supportedRuntimes.value = [...supportedRuntimes.value, runtime]
}

const runtimeValidationError = ref<string | null>(null)

async function handleSubmit(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
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
    const result = await registerBuildRunner(activeOrgId, {
      name: name.value.trim(),
      ipAddress: ipAddress.value.trim(),
      sshPort: sshPort.value,
      sshUser: sshUser.value.trim(),
      authMethod: authMethod.value,
      privateKey: authMethod.value === 'import' ? privateKey.value.trim() : undefined,
      maxConcurrentBuilds: maxConcurrentBuilds.value,
      cpuCores: cpuCores.value ?? null,
      ramGb: ramGb.value ?? null,
      supportedRuntimes: supportedRuntimes.value,
      projectId: projectId.value ?? null,
    })

    emit('update:open', false)
    emit('registered')

    if (result.publicKey !== null && result.publicKey !== '') {
      registeredPublicKey.value = result.publicKey
      showPublicKeySheet.value = true
    }

    resetForm()
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      apiError.value = Object.values(fieldErrors).flat()[0] ?? 'Validation failed.'
    } else {
      apiError.value = 'Unable to register build runner.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function handleOpenChange(value: boolean): void {
  emit('update:open', value)

  if (!value) {
    resetForm()
  }
}
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
      <SheetHeader>
        <SheetTitle>Add build runner</SheetTitle>
        <SheetDescription>
          Register a dedicated host for compiling and packaging deployment artifacts.
        </SheetDescription>
      </SheetHeader>

      <SheetBody class="space-y-4">
        <p v-if="apiError !== null" class="text-sm text-destructive">
          {{ apiError }}
        </p>

        <div class="space-y-2">
          <Label for="runner-name">Name</Label>
          <Input id="runner-name" v-model="name" placeholder="CI Runner 1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2 sm:col-span-2">
            <Label for="runner-ip">IP address</Label>
            <Input id="runner-ip" v-model="ipAddress" placeholder="203.0.113.50" />
          </div>
          <div class="space-y-2">
            <Label for="runner-port">SSH port</Label>
            <Input id="runner-port" v-model.number="sshPort" type="number" min="1" max="65535" />
          </div>
          <div class="space-y-2">
            <Label for="runner-user">SSH user</Label>
            <Input id="runner-user" v-model="sshUser" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Authentication</Label>
          <Select
            :model-value="authMethod"
            @update:model-value="(value) => { authMethod = value as 'generate' | 'import' }"
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="generate">
                Generate new key pair
              </SelectItem>
              <SelectItem value="import">
                Import existing private key
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div v-if="authMethod === 'import'" class="space-y-2">
          <Label for="runner-private-key">Private key</Label>
          <Textarea
            id="runner-private-key"
            v-model="privateKey"
            rows="6"
            class="font-mono text-xs"
            placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="runner-slots">Max concurrent builds</Label>
            <Input
              id="runner-slots"
              v-model.number="maxConcurrentBuilds"
              type="number"
              min="1"
              max="32"
            />
          </div>
          <div class="space-y-2">
            <Label for="runner-project">Project scope</Label>
            <Select
              :model-value="projectId ?? 'none'"
              @update:model-value="(value) => { projectId = value === 'none' ? undefined : String(value) }"
            >
              <SelectTrigger id="runner-project">
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
            <Label for="runner-cpu">CPU cores</Label>
            <Input id="runner-cpu" v-model.number="cpuCores" type="number" min="1" />
          </div>
          <div class="space-y-2">
            <Label for="runner-ram">RAM (GB)</Label>
            <Input id="runner-ram" v-model.number="ramGb" type="number" min="1" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Supported runtimes</Label>
          <div class="flex flex-wrap gap-2">
            <Button
              v-for="option in runtimeOptions"
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
          :disabled="isSubmitting || name.trim() === '' || ipAddress.trim() === '' || supportedRuntimes.length === 0"
          @click="handleSubmit"
        >
          {{ isSubmitting ? 'Registering…' : 'Register runner' }}
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>

  <PublicKeySuccessSheet
    v-model:open="showPublicKeySheet"
    :public-key="registeredPublicKey"
    title="Build runner registered"
    description="Your build runner has been registered. Add this SSH public key to the runner's authorized_keys file to complete setup."
  />
</template>
