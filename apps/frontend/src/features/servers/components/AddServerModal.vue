<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { AlertTriangleIcon, ImportIcon, ServerIcon } from '@lucide/vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
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
  registered: [payload: { publicKey: string | null; sshUser: string }]
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

const providerOptions = [
  { value: ServerProvider.Hetzner, label: 'Hetzner' },
  { value: ServerProvider.DigitalOcean, label: 'DigitalOcean' },
  { value: ServerProvider.Aws, label: 'AWS' },
  { value: ServerProvider.Vultr, label: 'Vultr' },
  { value: ServerProvider.Generic, label: 'Generic' },
]

const isImportTab = computed(() => activeTab.value === 'import')
const submitButtonLabel = computed((): string => {
  if (isSubmitting.value) {
    return isImportTab.value ? 'Importing…' : 'Registering…'
  }

  return isImportTab.value ? 'Import server' : 'Register server'
})
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

watch(activeTab, (tab, previousTab) => {
  if (tab === 'import' && previousTab === 'new') {
    authMethod.value = 'import'
  } else if (tab === 'new' && previousTab === 'import') {
    authMethod.value = 'generate'
  }
})

function managementModeCardClass(mode: ManagementMode): string {
  return managementMode.value === mode
    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
    : 'border-border'
}

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
    emit('registered', {
      publicKey: result.publicKey,
      sshUser: sshUser.value,
    })
    emit('update:open', false)
    resetForm()
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
          <TabsList class="grid h-10 w-full grid-cols-2 gap-1 p-1">
            <TabsTrigger
              value="new"
              class="min-h-8 gap-1.5 data-[state=active]:bg-popover data-[state=active]:shadow-sm"
            >
              <ServerIcon class="size-4 shrink-0" aria-hidden="true" />
              New server
            </TabsTrigger>
            <TabsTrigger
              value="import"
              class="min-h-8 gap-1.5 data-[state=active]:bg-popover data-[state=active]:shadow-sm"
            >
              <ImportIcon class="size-4 shrink-0" aria-hidden="true" />
              Import existing
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

          <TabsContent value="import" class="mt-4 space-y-6">
            <section class="space-y-3">
              <div>
                <h3 class="text-sm font-medium text-foreground">
                  How should HelixDeploy use this server?
                </h3>
                <p class="mt-1 text-xs text-muted-foreground">
                  Choose a management mode before entering connection details.
                </p>
              </div>

              <div class="space-y-2" role="radiogroup" aria-label="Management mode">
                <label
                  class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                  :class="managementModeCardClass(ManagementMode.Managed)"
                >
                  <input
                    v-model="managementMode"
                    type="radio"
                    name="management-mode"
                    class="mt-1 border-input text-primary"
                    :value="ManagementMode.Managed"
                  >
                  <span>
                    <span class="block text-sm font-medium text-foreground">Managed</span>
                    <span class="text-xs text-muted-foreground">
                      HelixDeploy runs deployments on this server
                    </span>
                  </span>
                </label>
                <label
                  class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                  :class="managementModeCardClass(ManagementMode.Observe)"
                >
                  <input
                    v-model="managementMode"
                    type="radio"
                    name="management-mode"
                    class="mt-1 border-input text-primary"
                    :value="ManagementMode.Observe"
                  >
                  <span>
                    <span class="block text-sm font-medium text-foreground">Observe</span>
                    <span class="text-xs text-muted-foreground">
                      Monitor only — keep your existing deploy workflow
                    </span>
                  </span>
                </label>
              </div>
            </section>

            <Alert
              v-if="managementMode === ManagementMode.Managed"
              class="border-amber-500/30 bg-amber-500/10 text-foreground *:data-[slot=alert-description]:text-muted-foreground"
              data-testid="managed-import-warning"
            >
              <AlertTriangleIcon class="text-amber-600 dark:text-amber-400" />
              <AlertTitle>Disable existing deploy workflows</AlertTitle>
              <AlertDescription>
                If this server already deploys via GitHub Actions or another CI pipeline,
                turn those workflows off before importing as Managed. Provisioning can also install
                or update stack packages — existing nginx configs are preserved, but only run it when
                you intend to change the server setup.
              </AlertDescription>
            </Alert>

            <div
              v-if="managementMode === ManagementMode.Observe"
              class="rounded-lg border border-border bg-muted/30 p-4"
              data-testid="observe-mode-guide"
            >
              <h3 class="text-sm font-medium text-foreground">
                Observe mode
              </h3>
              <p class="mt-1 text-xs text-muted-foreground">
                HelixDeploy monitors health and inventory without running deploy commands or provisioning.
              </p>
              <ol class="mt-3 list-decimal space-y-1.5 pl-4 text-sm text-foreground">
                <li>Provide SSH credentials HelixDeploy can use for health checks.</li>
                <li>Keep your existing CI/CD pipeline and server stack unchanged.</li>
                <li>Assign a project and environment for dashboard visibility.</li>
                <li>Switch to Managed mode later when you are ready to migrate.</li>
              </ol>
            </div>

            <section class="space-y-4">
              <h2 class="section-label">
                Connection details
              </h2>
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
            </section>
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
          {{ submitButtonLabel }}
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
