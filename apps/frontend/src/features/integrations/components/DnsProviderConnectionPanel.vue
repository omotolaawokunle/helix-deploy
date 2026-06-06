<script setup lang="ts">
import { CheckCircle2Icon } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useDnsProviderConnections } from '@/features/integrations/composables/useDnsProviderConnections'
import {
  DNS_PROVIDER_CONNECT,
  DNS_PROVIDER_DISCONNECT,
  DNS_PROVIDER_UI,
} from '@/features/integrations/lib/dnsProviderConfig'
import type { DnsProvider } from '@/features/integrations/types'
import { formatRelativeTime } from '@/lib/format'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

interface Props {
  organizationId: string
  provider: DnsProvider
  canManage: boolean
}

const props = defineProps<Props>()

const ui = DNS_PROVIDER_UI[props.provider]

const { connections, ensureLoaded, updateProvider } = useDnsProviderConnections(
  () => props.organizationId,
)

const isLoading = ref(true)
const loadError = ref<string | null>(null)
const apiToken = ref('')
const isSaving = ref(false)
const isDisconnectOpen = ref(false)
const isDisconnecting = ref(false)

const connection = computed(() => connections.value?.[props.provider] ?? null)
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

const connectedSinceLabel = computed(() => {
  if (connection.value?.connectedAt === null || connection.value?.connectedAt === undefined) {
    return null
  }

  return formatRelativeTime(connection.value.connectedAt)
})

async function load(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    await ensureLoaded()
  } catch {
    loadError.value = ui.loadErrorMessage
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
    const next = await DNS_PROVIDER_CONNECT[props.provider](props.organizationId, token)
    updateProvider(props.provider, next)
    apiToken.value = ''
    toast.success(ui.connectSuccessMessage)
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)
    toast.error(
      fieldErrors === null
        ? ui.connectErrorMessage
        : (firstFieldError(fieldErrors, 'token') ?? ui.connectErrorMessage),
    )
  } finally {
    isSaving.value = false
  }
}

async function confirmDisconnect(): Promise<void> {
  isDisconnecting.value = true

  try {
    await DNS_PROVIDER_DISCONNECT[props.provider](props.organizationId)
    updateProvider(props.provider, {
      connected: false,
      status: 'disconnected',
      connectedAt: null,
      connectedBy: null,
    })
    isDisconnectOpen.value = false
    toast.success(ui.disconnectSuccessMessage)
  } catch {
    toast.error(ui.disconnectErrorMessage)
  } finally {
    isDisconnecting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section
    class="panel space-y-4 p-6 transition-shadow duration-200 hover:shadow-sm"
    :data-testid="ui.testId"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="space-y-1">
        <h2 class="section-label">
          {{ ui.title }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ ui.description }}
        </p>
      </div>
      <Badge
        :variant="statusVariant"
        class="transition-colors duration-200"
      >
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
      <Transition name="fade-up">
        <div
          v-if="isConnected && connectedSinceLabel !== null"
          key="connected-meta"
          class="flex items-center gap-2 text-sm text-muted-foreground"
        >
          <CheckCircle2Icon class="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span>Connected {{ connectedSinceLabel }}</span>
        </div>
      </Transition>

      <div v-if="canManage && !isConnected" class="space-y-3 rounded-lg border p-4">
        <div class="space-y-2">
          <Label :for="ui.tokenInputId">{{ ui.tokenLabel }}</Label>
          <Input
            :id="ui.tokenInputId"
            v-model="apiToken"
            type="password"
            autocomplete="off"
            :placeholder="ui.tokenPlaceholder"
          />
          <p class="text-xs text-muted-foreground">
            {{ ui.tokenHint }}
          </p>
        </div>
        <Button
          type="button"
          size="sm"
          :disabled="isSaving || apiToken.trim() === ''"
          @click="handleConnect"
        >
          {{ isSaving ? ui.connectingLabel : ui.connectLabel }}
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
        {{ ui.notConnectedAdminHint }}
      </p>
    </template>

    <ConfirmDestructiveDialog
      v-model:open="isDisconnectOpen"
      :title="ui.disconnectTitle"
      :description="ui.disconnectDescription"
      confirm-text="disconnect"
      confirm-button-label="Disconnect"
      :can-confirm="!isDisconnecting"
      @confirm="confirmDisconnect"
    />
  </section>
</template>
