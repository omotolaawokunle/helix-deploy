<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
  connectCloudflare,
  disconnectCloudflare,
  fetchCloudflareConnection,
} from '@/features/integrations/api'
import type { CloudflareConnection } from '@/features/integrations/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

interface Props {
  organizationId: string
  canManage: boolean
}

const props = defineProps<Props>()

const connection = ref<CloudflareConnection | null>(null)
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const apiToken = ref('')
const isSaving = ref(false)
const isDisconnectOpen = ref(false)
const isDisconnecting = ref(false)

const isConnected = computed(() => connection.value?.connected === true)

const statusLabel = computed(() => {
  if (connection.value === null) {
    return 'Unknown'
  }

  if (connection.value.connected) {
    return 'Connected'
  }

  if (connection.value.status === 'error') {
    return 'Error'
  }

  return 'Not connected'
})

const statusVariant = computed(() => {
  if (isConnected.value) {
    return 'default' as const
  }

  if (connection.value?.status === 'error') {
    return 'destructive' as const
  }

  return 'outline' as const
})

async function load(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    connection.value = await fetchCloudflareConnection(props.organizationId)
  } catch {
    loadError.value = 'Unable to load Cloudflare connection status.'
  } finally {
    isLoading.value = false
  }
}

async function handleConnect(): Promise<void> {
  const token = apiToken.value.trim()

  if (token === '') {
    return
  }

  isSaving.value = true

  try {
    connection.value = await connectCloudflare(props.organizationId, token)
    apiToken.value = ''
    toast.success('Cloudflare connected.')
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)
    toast.error(
      fieldErrors === null
        ? 'Unable to connect Cloudflare.'
        : (firstFieldError(fieldErrors, 'token') ?? 'Unable to connect Cloudflare.'),
    )
  } finally {
    isSaving.value = false
  }
}

async function confirmDisconnect(): Promise<void> {
  isDisconnecting.value = true

  try {
    await disconnectCloudflare(props.organizationId)
    connection.value = {
      connected: false,
      status: 'disconnected',
      connectedAt: null,
      connectedBy: null,
    }
    isDisconnectOpen.value = false
    toast.success('Cloudflare disconnected.')
  } catch {
    toast.error('Unable to disconnect Cloudflare.')
  } finally {
    isDisconnecting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="panel space-y-4 p-6" data-testid="cloudflare-connection-panel">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="space-y-1">
        <h2 class="section-label">
          Cloudflare DNS
        </h2>
        <p class="text-sm text-muted-foreground">
          Connect a Cloudflare API token to manage DNS records for assigned project zones.
          Tokens are encrypted and never shown after save.
        </p>
      </div>
      <Badge :variant="statusVariant">
        {{ statusLabel }}
      </Badge>
    </div>

    <div v-if="isLoading" class="space-y-3">
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-10 w-32" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="rounded-lg border border-dashed p-4 text-center"
    >
      <p class="text-sm text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" size="sm" class="mt-3" @click="load">
        Try again
      </Button>
    </div>

    <template v-else>
      <div
        v-if="isConnected && connection?.connectedAt !== null"
        class="text-sm text-muted-foreground"
      >
        Connected {{ connection?.connectedAt }}
      </div>

      <div v-if="canManage && !isConnected" class="space-y-3 rounded-lg border p-4">
        <div class="space-y-2">
          <Label for="cloudflare-api-token">Cloudflare API token</Label>
          <Input
            id="cloudflare-api-token"
            v-model="apiToken"
            type="password"
            autocomplete="off"
            placeholder="Paste token with Zone.DNS:Edit on approved zones"
          />
          <p class="text-xs text-muted-foreground">
            Use a scoped token limited to the zones you plan to assign to projects.
          </p>
        </div>
        <Button
          type="button"
          size="sm"
          :disabled="isSaving || apiToken.trim() === ''"
          @click="handleConnect"
        >
          {{ isSaving ? 'Connecting…' : 'Connect Cloudflare' }}
        </Button>
      </div>

      <div
        v-else-if="canManage && isConnected"
        class="flex flex-wrap items-center gap-3"
      >
        <Button
          type="button"
          variant="outline"
          size="sm"
          @click="isDisconnectOpen = true"
        >
          Disconnect
        </Button>
      </div>

      <p v-else-if="!canManage && !isConnected" class="text-sm text-muted-foreground">
        Ask an organization admin to connect Cloudflare before assigning DNS zones to projects.
      </p>
    </template>

    <ConfirmDestructiveDialog
      v-model:open="isDisconnectOpen"
      title="Disconnect Cloudflare"
      description="Sites with managed DNS will stop receiving automatic record updates. Existing Cloudflare records are not removed."
      confirm-text="disconnect"
      confirm-button-label="Disconnect"
      :can-confirm="!isDisconnecting"
      @confirm="confirmDisconnect"
    />
  </section>
</template>
