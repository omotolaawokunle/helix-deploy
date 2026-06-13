<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue'
import { RefreshCwIcon, ServerCogIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  fetchServerServices,
  restartServerService,
  startServerService,
  stopServerService,
  syncServerServiceStatuses,
} from '@/features/servers/api'
import type { InstalledServiceRecord } from '@/features/servers/types'
import { formatRelativeTime } from '@/lib/format'
import { ManagementMode } from '@/types'

interface Props {
  serverId: string
  managementMode: ManagementMode
  isProduction: boolean
  canManage: boolean
}

interface ServiceActionVisibility {
  start: boolean
  stop: boolean
  restart: boolean
}

const SYNC_HINTS = [
  'Querying systemd units on the server…',
  'Reading active service state…',
  'Updating cached status…',
] as const

const props = defineProps<Props>()

const serverId = toRef(props, 'serverId')

const services = ref<InstalledServiceRecord[]>([])
const isLoading = ref(true)
const isSyncing = ref(false)
const loadError = ref<string | null>(null)
const pendingServiceKeys = ref<string[]>([])
const recentlyUpdatedKeys = ref<string[]>([])
const pendingAction = ref<'stop' | 'restart' | null>(null)
const pendingService = ref<InstalledServiceRecord | null>(null)
const isConfirmOpen = ref(false)
const syncHintIndex = ref(0)

let syncHintTimer: ReturnType<typeof setInterval> | null = null
const updateFlashTimers = new Map<string, ReturnType<typeof setTimeout>>()

const isObserveMode = computed((): boolean => props.managementMode === ManagementMode.Observe)

const isEmpty = computed((): boolean => !isLoading.value && loadError.value === null && services.value.length === 0)

const showTable = computed((): boolean => isLoading.value || services.value.length > 0)

const syncHint = computed((): string | null => {
  if (!isSyncing.value) {
    return null
  }

  return SYNC_HINTS[syncHintIndex.value] ?? SYNC_HINTS[0]
})

const emptyDescription = computed((): string => {
  if (isObserveMode.value) {
    return 'HelixDeploy scans the server after SSH connects and lists detected stack services here.'
  }

  return 'HelixDeploy scans the server after SSH connects. You can also provision a stack from the Provision action.'
})

const refreshLabel = computed((): string => (isSyncing.value ? 'Refreshing…' : 'Refresh status'))

function isServicePending(serviceKey: string): boolean {
  return pendingServiceKeys.value.includes(serviceKey)
}

function isRecentlyUpdated(serviceKey: string): boolean {
  return recentlyUpdatedKeys.value.includes(serviceKey)
}

function rowEntranceDelay(index: number): string {
  return `${Math.min(index, 8) * 45}ms`
}

function markServicePending(serviceKey: string): void {
  if (pendingServiceKeys.value.includes(serviceKey)) {
    return
  }

  pendingServiceKeys.value = [...pendingServiceKeys.value, serviceKey]
}

function clearServicePending(serviceKey: string): void {
  pendingServiceKeys.value = pendingServiceKeys.value.filter(key => key !== serviceKey)
}

function markRecentlyUpdated(serviceKey: string): void {
  if (!recentlyUpdatedKeys.value.includes(serviceKey)) {
    recentlyUpdatedKeys.value = [...recentlyUpdatedKeys.value, serviceKey]
  }

  const existingTimer = updateFlashTimers.get(serviceKey)

  if (existingTimer !== undefined) {
    clearTimeout(existingTimer)
  }

  updateFlashTimers.set(
    serviceKey,
    setTimeout(() => {
      recentlyUpdatedKeys.value = recentlyUpdatedKeys.value.filter(key => key !== serviceKey)
      updateFlashTimers.delete(serviceKey)
    }, 1200),
  )
}

function actionsForService(service: InstalledServiceRecord): ServiceActionVisibility {
  if (!service.controllable || !props.canManage || isObserveMode.value) {
    return { start: false, stop: false, restart: false }
  }

  if (service.status === 'running') {
    return { start: false, stop: true, restart: true }
  }

  if (service.status === 'stopped') {
    return { start: true, stop: false, restart: false }
  }

  return { start: true, stop: false, restart: true }
}

const serviceActionsByKey = computed((): Record<string, ServiceActionVisibility> => {
  const map: Record<string, ServiceActionVisibility> = {}

  for (const service of services.value) {
    map[service.key] = actionsForService(service)
  }

  return map
})

const servicesWithActions = computed((): Array<InstalledServiceRecord & { actions: ServiceActionVisibility }> => {
  return services.value.map((service) => ({
    ...service,
    actions: serviceActionsByKey.value[service.key] ?? { start: false, stop: false, restart: false },
  }))
})

function formatLastChecked(checkedAt: string | null): string {
  if (checkedAt === null) {
    return 'Not checked yet'
  }

  return formatRelativeTime(checkedAt)
}

function queuedToastMessage(action: 'start' | 'stop' | 'restart', label: string): string {
  if (action === 'start') {
    return `${label} start queued`
  }

  if (action === 'stop') {
    return `${label} stop queued`
  }

  return `${label} restart queued`
}

function applyServicesUpdate(updated: InstalledServiceRecord[]): void {
  const previousStatuses = new Map(
    services.value.map(service => [service.key, service.status]),
  )

  updated.forEach((service) => {
    clearServicePending(service.key)

    const previousStatus = previousStatuses.get(service.key)

    if (previousStatus !== undefined && previousStatus !== service.status) {
      markRecentlyUpdated(service.key)
    }
  })

  services.value = updated
  loadError.value = null
}

async function loadServices(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    services.value = await fetchServerServices(serverId.value)
  } catch {
    loadError.value = 'Unable to load installed services.'
  } finally {
    isLoading.value = false
  }
}

async function refreshStatuses(options: { notifyOnError?: boolean } = {}): Promise<void> {
  if (isSyncing.value) {
    return
  }

  isSyncing.value = true

  try {
    await syncServerServiceStatuses(serverId.value)
  } catch {
    if (options.notifyOnError ?? true) {
      toast.error('Unable to refresh service statuses.')
    }
  } finally {
    isSyncing.value = false
  }
}

async function executeAction(
  action: 'start' | 'stop' | 'restart',
  service: InstalledServiceRecord,
): Promise<void> {
  markServicePending(service.key)

  try {
    if (action === 'start') {
      await startServerService(serverId.value, service.key)
    } else if (action === 'stop') {
      await stopServerService(serverId.value, service.key)
    } else {
      await restartServerService(serverId.value, service.key)
    }

    toast.success(queuedToastMessage(action, service.label), {
      description: 'Status updates when the operation completes on the server.',
    })
  } catch {
    clearServicePending(service.key)
    toast.error(`Unable to ${action} ${service.label}.`)
  }
}

function runAction(action: 'start' | 'stop' | 'restart', service: InstalledServiceRecord): void {
  if (!props.canManage || !service.controllable) {
    return
  }

  if (props.isProduction && (action === 'stop' || action === 'restart')) {
    pendingAction.value = action
    pendingService.value = service
    isConfirmOpen.value = true

    return
  }

  void executeAction(action, service)
}

function confirmDestructiveAction(): void {
  if (pendingAction.value === null || pendingService.value === null) {
    return
  }

  const service = pendingService.value
  const action = pendingAction.value

  pendingAction.value = null
  pendingService.value = null

  void executeAction(action, service)
}

function startSyncHints(): void {
  syncHintIndex.value = 0

  if (syncHintTimer !== null) {
    clearInterval(syncHintTimer)
  }

  syncHintTimer = setInterval(() => {
    syncHintIndex.value = (syncHintIndex.value + 1) % SYNC_HINTS.length
  }, 2200)
}

function stopSyncHints(): void {
  if (syncHintTimer !== null) {
    clearInterval(syncHintTimer)
    syncHintTimer = null
  }

  syncHintIndex.value = 0
}

watch(isConfirmOpen, (isOpen) => {
  if (!isOpen) {
    pendingAction.value = null
    pendingService.value = null
  }
})

watch(isSyncing, (syncing) => {
  if (syncing) {
    startSyncHints()

    return
  }

  stopSyncHints()
})

onMounted(async () => {
  await loadServices()
  await refreshStatuses({ notifyOnError: false })
})

onBeforeUnmount(() => {
  stopSyncHints()

  updateFlashTimers.forEach((timer) => {
    clearTimeout(timer)
  })

  updateFlashTimers.clear()
})

defineExpose({
  applyServicesUpdate,
})
</script>

<template>
  <section aria-labelledby="installed-services-heading">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <div class="space-y-1">
        <h2 id="installed-services-heading" class="section-label !mb-0">
          Installed services
        </h2>
        <Transition name="fade-up">
          <p
            v-if="syncHint !== null"
            class="text-xs text-muted-foreground motion-reduce:transition-none"
            aria-live="polite"
          >
            {{ syncHint }}
          </p>
        </Transition>
      </div>
      <Button
        v-if="showTable && loadError === null"
        type="button"
        size="sm"
        variant="outline"
        class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
        :disabled="isLoading || isSyncing"
        :aria-busy="isSyncing"
        @click="refreshStatuses()"
      >
        <RefreshCwIcon
          class="mr-2 size-4 shrink-0 motion-reduce:animate-none"
          :class="{ 'animate-spin': isSyncing }"
          aria-hidden="true"
        />
        {{ refreshLabel }}
      </Button>
    </div>

    <Transition name="fade-up">
      <div
        v-if="loadError !== null"
        class="panel flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
        role="alert"
      >
        <p class="text-sm text-destructive">
          {{ loadError }}
        </p>
        <Button
          type="button"
          size="sm"
          variant="outline"
          class="min-h-9 shrink-0 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
          @click="loadServices"
        >
          Retry
        </Button>
      </div>
    </Transition>

    <EmptyState
      v-if="loadError === null && isEmpty"
      title="No services detected"
      :description="emptyDescription"
      :icon="ServerCogIcon"
    />

    <div
      v-else-if="loadError === null && showTable"
      class="panel animate-panel-in overflow-hidden motion-reduce:animate-none"
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Service</TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="hidden md:table-cell">
              Last checked
            </TableHead>
            <TableHead class="text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="4">
              <div class="space-y-2 py-1" aria-hidden="true">
                <Skeleton class="h-4 w-32 motion-reduce:animate-none" />
                <Skeleton class="h-4 w-48 motion-reduce:animate-none" />
                <Skeleton class="h-4 w-40 motion-reduce:animate-none" />
              </div>
              <span class="sr-only">Loading services</span>
            </TableCell>
          </TableRow>
          <TableRow
            v-for="(service, index) in servicesWithActions"
            v-else
            :key="service.key"
            class="animate-service-row-in border-l-2 transition-[background-color,border-color] duration-300 motion-reduce:animate-none motion-reduce:transition-none"
            :class="[
              isServicePending(service.key) ? 'bg-muted/30' : undefined,
              isRecentlyUpdated(service.key) ? 'border-l-primary bg-primary/5' : 'border-l-transparent',
            ]"
            :style="{ animationDelay: rowEntranceDelay(index) }"
          >
            <TableCell>
              <div class="space-y-1">
                <div class="inline-flex items-center gap-2 font-medium">
                  {{ service.label }}
                  <span
                    v-if="isServicePending(service.key)"
                    class="inline-flex size-1.5 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
                    aria-hidden="true"
                  />
                  <span v-if="isServicePending(service.key)" class="sr-only">
                    Operation in progress
                  </span>
                </div>
                <p
                  v-if="!service.controllable"
                  class="max-w-xs text-xs leading-relaxed text-muted-foreground"
                >
                  Not systemd-managed. Use Daemons or deployments for process control.
                </p>
              </div>
            </TableCell>
            <TableCell>
              <Transition name="status-crossfade" mode="out-in">
                <StatusBadge
                  :key="`${service.key}-${service.status}`"
                  :status="service.status"
                  type="service"
                />
              </Transition>
            </TableCell>
            <TableCell class="hidden text-sm text-muted-foreground md:table-cell">
              <time
                v-if="service.statusCheckedAt !== null"
                :datetime="service.statusCheckedAt"
                :title="new Date(service.statusCheckedAt).toLocaleString()"
              >
                {{ formatLastChecked(service.statusCheckedAt) }}
              </time>
              <span v-else>{{ formatLastChecked(null) }}</span>
            </TableCell>
            <TableCell>
              <div
                v-if="service.actions.start || service.actions.stop || service.actions.restart"
                class="flex flex-wrap justify-end gap-1"
              >
                <Button
                  v-if="service.actions.start"
                  type="button"
                  size="sm"
                  variant="ghost"
                  class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
                  :disabled="isServicePending(service.key)"
                  :aria-label="`Start ${service.label}`"
                  @click="runAction('start', service)"
                >
                  Start
                </Button>
                <Button
                  v-if="service.actions.stop"
                  type="button"
                  size="sm"
                  variant="ghost"
                  class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
                  :disabled="isServicePending(service.key)"
                  :aria-label="`Stop ${service.label}`"
                  @click="runAction('stop', service)"
                >
                  Stop
                </Button>
                <Button
                  v-if="service.actions.restart"
                  type="button"
                  size="sm"
                  variant="ghost"
                  class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
                  :disabled="isServicePending(service.key)"
                  :aria-label="`Restart ${service.label}`"
                  @click="runAction('restart', service)"
                >
                  Restart
                </Button>
              </div>
              <span
                v-else-if="isObserveMode && service.controllable"
                class="text-sm text-muted-foreground"
              >
                Read-only in observe mode
              </span>
              <span v-else class="text-sm text-muted-foreground">—</span>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <ConfirmDestructiveDialog
      v-model:open="isConfirmOpen"
      :title="pendingAction === 'stop' ? 'Stop service' : 'Restart service'"
      :description="pendingService !== null && pendingAction !== null
        ? `This will ${pendingAction} ${pendingService.label} on a production server. Sites depending on this service may become unavailable.`
        : ''"
      :confirm-text="pendingService?.label ?? ''"
      :confirm-button-label="pendingAction === 'stop' ? 'Stop service' : 'Restart service'"
      @confirm="confirmDestructiveAction"
    />
  </section>
</template>
