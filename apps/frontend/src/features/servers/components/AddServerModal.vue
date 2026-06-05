<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { AlertTriangleIcon } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import PublicKeySuccessSheet from '@/features/servers/components/PublicKeySuccessSheet.vue'
import ServerFormFields from '@/features/servers/components/ServerFormFields.vue'
import type { CloudInstanceSelection } from '@/features/servers/components/CloudProviderImportPanel.vue'
import {
  fetchProjectEnvironments,
  fetchProjects,
  registerServer,
} from '@/features/servers/api'
import { useServersStore } from '@/features/servers/stores/useServersStore'
import { useActiveOrg } from '@/composables/useActiveOrg'
import { ManagementMode, ServerProvider } from '@/types'
import { extractFieldErrors } from '@/lib/validation-errors'

const CloudProviderImportPanel = defineAsyncComponent(
  () => import('@/features/servers/components/CloudProviderImportPanel.vue'),
)

interface Props {
  open: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const serversStore = useServersStore()
const { orgId } = useActiveOrg()

const activeTab = ref('new')
const isSubmitting = ref(false)
const apiError = ref<string | null>(null)

const hostname = ref('')
const ipAddress = ref('')
const sshPort = ref(22)
const sshUser = ref('deploy')
const provider = ref<ServerProvider>(ServerProvider.Generic)
const region = ref<string | null>(null)
const serverType = ref<string | null>(null)
const providerInstanceId = ref<string | null>(null)
const os = ref<string | null>(null)
const projectId = ref<string | undefined>(undefined)
const environmentId = ref<string | undefined>(undefined)
const authMethod = ref<'generate' | 'import'>('generate')
const privateKey = ref('')
const tagsInput = ref('')
const managementMode = ref<ManagementMode>(ManagementMode.Managed)

const projects = ref<Array<{ id: string; name: string }>>([])
const environments = ref<Array<{ id: string; name: string }>>([])

const showPublicKeySheet = ref(false)
const registeredPublicKey = ref('')

const providerOptions = [
  { value: ServerProvider.Hetzner, label: 'Hetzner' },
  { value: ServerProvider.DigitalOcean, label: 'DigitalOcean' },
  { value: ServerProvider.Aws, label: 'AWS' },
  { value: ServerProvider.Vultr, label: 'Vultr' },
  { value: ServerProvider.Generic, label: 'Generic' },
]

const isImportTab = computed(() => activeTab.value === 'import')
const isCloudProviderSelected = computed(() =>
  [ServerProvider.Hetzner, ServerProvider.DigitalOcean, ServerProvider.Aws].includes(provider.value),
)

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

watch(projectId, async (id) => {
  environmentId.value = undefined
  environments.value = id ? await fetchProjectEnvironments(id) : []
})

function resetForm(): void {
  hostname.value = ''
  ipAddress.value = ''
  sshPort.value = 22
  sshUser.value = 'deploy'
  provider.value = ServerProvider.Generic
  region.value = null
  serverType.value = null
  providerInstanceId.value = null
  os.value = null
  projectId.value = undefined
  environmentId.value = undefined
  authMethod.value = 'generate'
  privateKey.value = ''
  tagsInput.value = ''
  managementMode.value = ManagementMode.Managed
  apiError.value = null
}

function parseTags(value: string): string[] {
  return value
    .split(',')
    .map(tag => tag.trim())
    .filter(tag => tag !== '')
}

function handleCloudInstanceSelected(selection: CloudInstanceSelection): void {
  hostname.value = selection.hostname
  ipAddress.value = selection.ipAddress
  region.value = selection.region
  serverType.value = selection.serverType
  providerInstanceId.value = selection.providerInstanceId
  os.value = selection.os
}

async function handleSubmit(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isSubmitting.value = true
  apiError.value = null

  try {
    const result = await registerServer(activeOrgId, {
      name: hostname.value,
      hostname: hostname.value,
      ipAddress: ipAddress.value,
      sshPort: sshPort.value,
      sshUser: sshUser.value,
      provider: provider.value,
      region: region.value,
      serverType: serverType.value,
      providerInstanceId: providerInstanceId.value,
      os: os.value,
      managementMode: isImportTab.value ? managementMode.value : ManagementMode.Managed,
      authMethod: authMethod.value,
      privateKey: authMethod.value === 'import' ? privateKey.value : undefined,
      projectId: projectId.value,
      environmentId: environmentId.value,
      tags: parseTags(tagsInput.value),
    })

    await serversStore.fetch()
    emit('update:open', false)
    resetForm()

    if (result.publicKey !== null && result.publicKey !== '') {
      registeredPublicKey.value = result.publicKey
      showPublicKeySheet.value = true
    }
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      apiError.value = Object.values(fieldErrors).flat()[0] ?? 'Validation failed.'
      return
    }

    apiError.value = 'Unable to register server. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
      <SheetHeader>
        <SheetTitle>Add server</SheetTitle>
        <SheetDescription>
          Register a new server or import an existing one into HelixDeploy.
        </SheetDescription>
      </SheetHeader>

      <SheetBody>
        <CloudProviderImportPanel
          v-if="orgId !== null && isCloudProviderSelected"
          :organization-id="orgId"
          :provider="provider"
          :active="props.open"
          @instance-selected="handleCloudInstanceSelected"
        />

        <Tabs v-model="activeTab" class="w-full">
          <TabsList class="grid h-auto w-full grid-cols-2">
            <TabsTrigger value="new" class="min-h-9">
              New Server
            </TabsTrigger>
            <TabsTrigger value="import" class="min-h-9">
              Import Existing
            </TabsTrigger>
          </TabsList>

          <TabsContent value="new" class="mt-4 space-y-4">
            <ServerFormFields
              v-model:hostname="hostname"
              v-model:ip-address="ipAddress"
              v-model:ssh-port="sshPort"
              v-model:ssh-user="sshUser"
              v-model:provider="provider"
              v-model:project-id="projectId"
              v-model:environment-id="environmentId"
              v-model:auth-method="authMethod"
              v-model:private-key="privateKey"
              v-model:tags="tagsInput"
              :projects="projects"
              :environments="environments"
              :provider-options="providerOptions"
            />
          </TabsContent>

          <TabsContent value="import" class="mt-4 space-y-4">
            <ServerFormFields
              v-model:hostname="hostname"
              v-model:ip-address="ipAddress"
              v-model:ssh-port="sshPort"
              v-model:ssh-user="sshUser"
              v-model:provider="provider"
              v-model:project-id="projectId"
              v-model:environment-id="environmentId"
              v-model:auth-method="authMethod"
              v-model:private-key="privateKey"
              v-model:tags="tagsInput"
              :projects="projects"
              :environments="environments"
              :provider-options="providerOptions"
            />

            <div class="space-y-3">
              <Label>Management mode</Label>
              <div class="space-y-2">
                <label
                  class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/50"
                >
                  <input
                    v-model="managementMode"
                    type="radio"
                    class="mt-1 border-input text-primary"
                    :value="ManagementMode.Managed"
                  >
                  <span>
                    <span class="block text-sm font-medium text-foreground">Managed</span>
                    <span class="text-xs text-muted-foreground">
                      HelixDeploy controls deployments
                    </span>
                  </span>
                </label>
                <label
                  class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/50"
                >
                  <input
                    v-model="managementMode"
                    type="radio"
                    class="mt-1 border-input text-primary"
                    :value="ManagementMode.Observe"
                  >
                  <span>
                    <span class="block text-sm font-medium text-foreground">Observe</span>
                    <span class="text-xs text-muted-foreground">
                      Register for visibility only; keep existing workflow
                    </span>
                  </span>
                </label>
              </div>
            </div>

            <Alert
              v-if="managementMode === ManagementMode.Managed"
              variant="destructive"
            >
              <AlertTriangleIcon class="size-4" />
              <AlertTitle>Managed import</AlertTitle>
              <AlertDescription>
                Make sure to disable any existing GitHub Actions deploy workflows to avoid conflicts.
              </AlertDescription>
            </Alert>

            <div
              v-if="managementMode === ManagementMode.Observe"
              class="rounded-lg border border-border bg-muted/30 p-4"
              data-testid="observe-mode-guide"
            >
              <h3 class="text-sm font-medium text-foreground">
                Observe-mode setup
              </h3>
              <p class="mt-1 text-xs text-muted-foreground">
                HelixDeploy monitors this server without taking over deployments. Follow these steps:
              </p>
              <ol class="mt-3 list-decimal space-y-2 pl-4 text-sm text-foreground">
                <li>Register the server with SSH credentials HelixDeploy can use for health checks.</li>
                <li>Keep your existing CI/CD pipeline — HelixDeploy will not run deploy commands.</li>
                <li>Assign the server to a project and environment for dashboard visibility.</li>
                <li>When ready to migrate, switch the server to Managed mode from server settings.</li>
              </ol>
            </div>
          </TabsContent>
        </Tabs>

        <p v-if="apiError" class="mt-4 text-sm text-destructive">
          {{ apiError }}
        </p>
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
          :disabled="isSubmitting"
          @click="handleSubmit"
        >
          {{ isSubmitting ? 'Registering…' : 'Register server' }}
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>

  <PublicKeySuccessSheet
    v-model:open="showPublicKeySheet"
    :public-key="registeredPublicKey"
  />
</template>
