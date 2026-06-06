<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
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
  fetchCloudInstancesCached,
  fetchCloudProvidersCached,
  invalidateCloudProviderStatusCache,
} from '@/features/servers/composables/useCloudProviderCache'
import {
  deleteCloudProviderCredential,
  storeCloudProviderCredential,
  type CloudInstance,
  type CloudProviderType,
} from '@/features/servers/api'
import { ServerProvider } from '@/types'

export interface CloudInstanceSelection {
  hostname: string
  ipAddress: string
  region: string | null
  serverType: string | null
  providerInstanceId: string
  os: string | null
}

interface Props {
  organizationId: string
  provider: ServerProvider
  active?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  active: true,
})

const emit = defineEmits<{
  'instance-selected': [value: CloudInstanceSelection]
}>()

const cloudProvider = computed((): CloudProviderType | null => {
  if (props.provider === ServerProvider.Hetzner) {
    return 'hetzner'
  }

  if (props.provider === ServerProvider.DigitalOcean) {
    return 'digitalocean'
  }

  if (props.provider === ServerProvider.Aws) {
    return 'aws'
  }

  return null
})

const isCloudProvider = computed(() => cloudProvider.value !== null)

const credentialConfigured = ref(false)
const isSavingCredential = ref(false)
const isLoadingInstances = ref(false)
const instancesLoaded = ref(false)
const providerToken = ref('')
const accessKeyId = ref('')
const secretAccessKey = ref('')
const awsRegion = ref('us-east-1')
const instances = ref<CloudInstance[]>([])
const selectedInstanceId = ref<string | undefined>(undefined)
let statusRequestId = 0
let instanceRequestId = 0

const instanceById = computed(() => {
  const map = new Map<string, CloudInstance>()

  for (const instance of instances.value) {
    map.set(instance.id, instance)
  }

  return map
})

function resetInstanceState(): void {
  instances.value = []
  instancesLoaded.value = false
  selectedInstanceId.value = undefined
  instanceRequestId += 1
}

async function refreshCredentialStatus(): Promise<void> {
  const provider = cloudProvider.value

  if (!props.active || provider === null) {
    credentialConfigured.value = false
    resetInstanceState()

    return
  }

  const requestId = ++statusRequestId

  try {
    const providers = await fetchCloudProvidersCached(props.organizationId)
    const configured = providers.some(
      entry => entry.provider === provider && entry.configured,
    )

    if (requestId !== statusRequestId) {
      return
    }

    credentialConfigured.value = configured
  } catch {
    if (requestId !== statusRequestId) {
      return
    }

    credentialConfigured.value = false
  }

  if (!credentialConfigured.value) {
    resetInstanceState()
  }
}

async function handleSaveCredential(): Promise<void> {
  const provider = cloudProvider.value

  if (provider === null) {
    return
  }

  isSavingCredential.value = true

  try {
    if (provider === 'aws') {
      if (accessKeyId.value.trim() === '' || secretAccessKey.value.trim() === '') {
        return
      }

      await storeCloudProviderCredential(props.organizationId, {
        provider,
        accessKeyId: accessKeyId.value.trim(),
        secretAccessKey: secretAccessKey.value.trim(),
        region: awsRegion.value.trim(),
      })
      secretAccessKey.value = ''
    } else {
      if (providerToken.value.trim() === '') {
        return
      }

      await storeCloudProviderCredential(props.organizationId, {
        provider,
        token: providerToken.value.trim(),
      })
      providerToken.value = ''
    }

    invalidateCloudProviderStatusCache(props.organizationId)
    credentialConfigured.value = true
    toast.success('Cloud provider credentials saved.')
    await loadInstances({ force: true })
  } catch {
    toast.error('Unable to save cloud provider credentials.')
  } finally {
    isSavingCredential.value = false
  }
}

async function handleRevokeCredential(): Promise<void> {
  const provider = cloudProvider.value

  if (provider === null) {
    return
  }

  try {
    await deleteCloudProviderCredential(props.organizationId, provider)
    invalidateCloudProviderStatusCache(props.organizationId)
    credentialConfigured.value = false
    resetInstanceState()
    toast.success('Cloud provider credentials removed.')
  } catch {
    toast.error('Unable to remove cloud provider credentials.')
  }
}

async function loadInstances(options: { force?: boolean } = {}): Promise<void> {
  const provider = cloudProvider.value

  if (provider === null || !credentialConfigured.value) {
    return
  }

  const requestId = ++instanceRequestId
  isLoadingInstances.value = true

  try {
    const nextInstances = await fetchCloudInstancesCached(
      props.organizationId,
      provider,
      options,
    )

    if (requestId !== instanceRequestId) {
      return
    }

    instances.value = nextInstances
    instancesLoaded.value = true
  } catch {
    if (requestId !== instanceRequestId) {
      return
    }

    instances.value = []
    instancesLoaded.value = false
    toast.error('Unable to load instances from cloud provider.')
  } finally {
    if (requestId === instanceRequestId) {
      isLoadingInstances.value = false
    }
  }
}

function handleInstanceChange(instanceId: string): void {
  selectedInstanceId.value = instanceId

  const instance = instanceById.value.get(instanceId)

  if (instance === undefined) {
    return
  }

  emit('instance-selected', {
    hostname: instance.name,
    ipAddress: instance.ipAddress ?? '',
    region: instance.region,
    serverType: instance.serverType,
    providerInstanceId: instance.id,
    os: instance.os,
  })
}

watch(
  () => [props.active, props.organizationId, props.provider] as const,
  () => {
    resetInstanceState()
    void refreshCredentialStatus()
  },
  { immediate: true },
)
</script>

<template>
  <div
    v-if="isCloudProvider && active"
    class="space-y-4 rounded-lg border border-border bg-muted/20 p-4"
    data-testid="cloud-provider-import-panel"
  >
    <div>
      <h3 class="text-sm font-medium text-foreground">
        Import from cloud provider
      </h3>
      <p class="mt-1 text-xs text-muted-foreground">
        Connect your provider API, then load instances to auto-fill server details.
      </p>
    </div>

    <div v-if="!credentialConfigured" class="space-y-3">
      <template v-if="cloudProvider === 'aws'">
        <div class="space-y-2">
          <Label for="aws-access-key">Access key ID</Label>
          <Input
            id="aws-access-key"
            v-model="accessKeyId"
            autocomplete="off"
          />
        </div>
        <div class="space-y-2">
          <Label for="aws-secret-key">Secret access key</Label>
          <Input
            id="aws-secret-key"
            v-model="secretAccessKey"
            type="password"
            autocomplete="off"
          />
        </div>
        <div class="space-y-2">
          <Label for="aws-region">Default region</Label>
          <Input
            id="aws-region"
            v-model="awsRegion"
            placeholder="us-east-1"
          />
        </div>
      </template>
      <template v-else>
        <div class="space-y-2">
          <Label for="cloud-provider-token">API token</Label>
          <Input
            id="cloud-provider-token"
            v-model="providerToken"
            type="password"
            autocomplete="off"
          />
        </div>
      </template>

      <Button
        type="button"
        size="sm"
        :disabled="isSavingCredential"
        @click="handleSaveCredential"
      >
        {{ isSavingCredential ? 'Saving…' : 'Save credentials' }}
      </Button>
    </div>

    <div v-else class="space-y-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs text-muted-foreground">Credentials configured</span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          @click="handleRevokeCredential"
        >
          Remove
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="isLoadingInstances"
          @click="loadInstances({ force: true })"
        >
          {{
            isLoadingInstances
              ? 'Loading…'
              : instancesLoaded
                ? 'Refresh instances'
                : 'Load instances'
          }}
        </Button>
      </div>

      <div v-if="instancesLoaded && instances.length > 0" class="space-y-2">
        <Label>Instance</Label>
        <Select
          :model-value="selectedInstanceId"
          @update:model-value="handleInstanceChange(String($event))"
        >
          <SelectTrigger>
            <SelectValue placeholder="Select an instance" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="instance in instances"
              :key="instance.id"
              :value="instance.id"
            >
              {{ instance.name }}
              <span v-if="instance.ipAddress" class="text-muted-foreground">
                ({{ instance.ipAddress }})
              </span>
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <p
        v-else-if="instancesLoaded && !isLoadingInstances"
        class="text-xs text-muted-foreground"
      >
        No instances found. Check your credentials or create an instance in your cloud console.
      </p>
    </div>
  </div>
</template>
