<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, toRef, watch } from 'vue'
import { ArrowDownToLineIcon, CheckCircle2Icon, EyeIcon, EyeOffIcon, LinkIcon, PencilIcon, RefreshCwIcon, Trash2Icon, XIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import { useRotatingStatusMessage } from '@/composables/useRotatingStatusMessage'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Badge } from '@/components/ui/badge'
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
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
import {
  applyEnvVarsPull,
  createEnvVar,
  deleteEnvVar,
  fetchEnvVars,
  fetchLinkableCredentials,
  revealEnvVar,
  syncEnvVars,
  updateEnvVar,
} from '@/features/sites/api'
import { useEnvVarsPullPreview } from '@/features/sites/composables/useEnvVarsPullPreview'
import { useEnvVarsSiteChannel } from '@/features/sites/composables/useEnvVarsSiteChannel'
import { parseEnvContent } from '@/lib/parseEnv'
import { defaultEnvKeyForService } from '@/features/servers/constants/serviceCredentialEnvKeys'
import type { ServerServiceCredentialRecord } from '@/features/servers/types'
import type { EnvVarListItem, EnvVarPullStrategy } from '@/types'

interface Props {
  siteId: string
  serverId?: string
}

const props = defineProps<Props>()

const PULL_LOADING_MESSAGES = [
  'Reading server .env over SSH…',
  'Parsing variables from the remote file…',
  'Diffing against Helix credentials…',
] as const

const siteId = toRef(props, 'siteId')
const serverId = toRef(props, 'serverId')

const envVars = ref<EnvVarListItem[]>([])
const isLoading = ref(true)
const addMode = ref<'literal' | 'reference'>('literal')
const newKey = ref('')
const newValue = ref('')
const selectedCredentialId = ref('')
const linkableCredentials = ref<ServerServiceCredentialRecord[]>([])
const linkableCredentialsLoaded = ref(false)
const isLoadingLinkable = ref(false)
const isAdding = ref(false)

const revealedValues = ref<Record<string, string>>({})
const revealFlashIds = ref<Record<string, boolean>>({})
const revealTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({})
const showDiffReadyFlash = ref(false)

let diffReadyFlashTimer: ReturnType<typeof setTimeout> | null = null

const editingId = ref<string | null>(null)
const editingValue = ref('')

const deleteTarget = ref<EnvVarListItem | null>(null)
const isDeleteDialogOpen = ref(false)
const isSyncDialogOpen = ref(false)
const isBulkImportOpen = ref(false)
const bulkImportContent = ref('')
const isImporting = ref(false)

const isPullSheetOpen = ref(false)
const isMirrorConfirmOpen = ref(false)
const reopenPullAfterMirrorCancel = ref(false)
const pullStrategy = ref<EnvVarPullStrategy>('add_new')
const isApplyingPull = ref(false)
const showEmptyStateBanner = ref(false)
const isEmptyBannerDismissed = ref(false)
const hasProbedEmptyServerEnv = ref(false)

const {
  pullPreview,
  isLoading: isPullPreviewLoading,
  errorMessage: pullPreviewError,
  loadPreview: loadPullPreview,
  handlePreviewReady,
  startPolling: startPullPolling,
  stopPolling: stopPullPolling,
  reset: resetPullPreview,
} = useEnvVarsPullPreview({
  siteId,
  defaultErrorMessage: 'Unable to load pull preview.',
})

const pullLoadingMessage = useRotatingStatusMessage(
  PULL_LOADING_MESSAGES,
  isPullPreviewLoading,
)

const canLinkCredentials = computed((): boolean => serverId.value !== undefined && serverId.value !== '')

async function loadLinkableCredentials(force = false): Promise<void> {
  if (!canLinkCredentials.value) {
    linkableCredentials.value = []
    linkableCredentialsLoaded.value = false

    return
  }

  if (linkableCredentialsLoaded.value && !force) {
    return
  }

  isLoadingLinkable.value = true

  try {
    linkableCredentials.value = await fetchLinkableCredentials(siteId.value)
    linkableCredentialsLoaded.value = true
  } catch {
    linkableCredentials.value = []
    linkableCredentialsLoaded.value = false
    toast.error('Unable to load server credentials.')
  } finally {
    isLoadingLinkable.value = false
  }
}

watch(selectedCredentialId, (credentialId) => {
  if (credentialId === '' || newKey.value.trim() !== '') {
    return
  }

  const credential = linkableCredentials.value.find(item => item.id === credentialId)

  if (credential !== undefined) {
    newKey.value = defaultEnvKeyForService(credential.serviceKey)
  }
})

const canAddEnvVar = computed((): boolean => {
  if (isAdding.value || newKey.value.trim() === '') {
    return false
  }

  if (addMode.value === 'literal') {
    return newValue.value.trim() !== ''
  }

  return selectedCredentialId.value !== '' && !isLoadingLinkable.value
})

watch(addMode, (mode) => {
  if (mode === 'reference') {
    void loadLinkableCredentials()
  } else {
    selectedCredentialId.value = ''
  }
})

const pullStrategyOptions: Array<{ value: EnvVarPullStrategy; label: string; description: string }> = [
  {
    value: 'add_new',
    label: 'Import missing only',
    description: 'Add keys that exist on the server but not in Helix.',
  },
  {
    value: 'overwrite_changed',
    label: 'Import missing and overwrite changed',
    description: 'Add new keys and update keys with different values.',
  },
  {
    value: 'mirror_server',
    label: 'Mirror server',
    description: 'Make Helix match the server exactly, including removing Helix-only keys.',
  },
]

const hasPullDiff = computed((): boolean => {
  const preview = pullPreview.value

  if (preview === null || preview.status !== 'ready' || ! preview.serverFileExists) {
    return false
  }

  return preview.new.length > 0
    || preview.changed.length > 0
    || preview.helixOnly.length > 0
    || preview.skipped.length > 0
})

const isAlreadyInSync = computed((): boolean => {
  const preview = pullPreview.value

  return preview !== null
    && preview.status === 'ready'
    && preview.serverFileExists
    && ! hasPullDiff.value
    && preview.unchanged.length > 0
})

const canApplyPull = computed((): boolean => {
  if (pullPreview.value === null || pullPreview.value.status !== 'ready') {
    return false
  }

  if (! pullPreview.value.serverFileExists) {
    return false
  }

  const preview = pullPreview.value

  if (pullStrategy.value === 'add_new') {
    return preview.new.length > 0
  }

  if (pullStrategy.value === 'overwrite_changed') {
    return preview.new.length > 0 || preview.changed.length > 0
  }

  return preview.new.length > 0
    || preview.changed.length > 0
    || preview.helixOnly.length > 0
})

function strategyCardClass(value: EnvVarPullStrategy): string {
  const base = 'flex cursor-pointer gap-3 rounded-md border p-3 transition-all duration-200 motion-reduce:transition-none'

  if (pullStrategy.value === value) {
    return `${base} border-primary bg-primary/5 ring-1 ring-primary/20`
  }

  return `${base} border-border hover:border-primary/40`
}

function rowEntranceDelay(index: number): string {
  return `${Math.min(index, 8) * 45}ms`
}

function triggerDiffReadyFlash(): void {
  if (diffReadyFlashTimer !== null) {
    clearTimeout(diffReadyFlashTimer)
  }

  showDiffReadyFlash.value = true
  diffReadyFlashTimer = setTimeout(() => {
    showDiffReadyFlash.value = false
    diffReadyFlashTimer = null
  }, 520)
}

function dismissEmptyBanner(): void {
  isEmptyBannerDismissed.value = true
  showEmptyStateBanner.value = false
}

function updateEmptyStateBanner(): void {
  if (isEmptyBannerDismissed.value || envVars.value.length > 0 || isPullSheetOpen.value) {
    showEmptyStateBanner.value = false

    return
  }

  const preview = pullPreview.value

  showEmptyStateBanner.value = preview !== null
    && preview.status === 'ready'
    && preview.serverFileExists
    && preview.new.length > 0
}

async function probeEmptyServerEnv(): Promise<void> {
  if (hasProbedEmptyServerEnv.value || envVars.value.length > 0) {
    return
  }

  hasProbedEmptyServerEnv.value = true
  await loadPullPreview(false, true)
  updateEmptyStateBanner()
}

async function openPullSheet(): Promise<void> {
  isPullSheetOpen.value = true
  resetPullPreview()
  pullStrategy.value = 'add_new'
  isPullPreviewLoading.value = true

  await loadPullPreview(true)
  startPullPolling()
}

function closePullSheet(): void {
  isPullSheetOpen.value = false
  stopPullPolling()
}

function reopenPullSheet(): void {
  isPullSheetOpen.value = true
  startPullPolling()
}

function handlePullSheetOpenChange(open: boolean): void {
  isPullSheetOpen.value = open

  if (! open) {
    stopPullPolling()
  }
}

function requestPullApply(): void {
  if (pullStrategy.value === 'mirror_server') {
    reopenPullAfterMirrorCancel.value = true
    closePullSheet()
    isMirrorConfirmOpen.value = true

    return
  }

  void confirmPullApply()
}

watch(isMirrorConfirmOpen, (open) => {
  if (open || ! reopenPullAfterMirrorCancel.value || isApplyingPull.value) {
    return
  }

  reopenPullAfterMirrorCancel.value = false
  reopenPullSheet()
})

async function confirmPullApply(): Promise<void> {
  isApplyingPull.value = true
  reopenPullAfterMirrorCancel.value = false

  try {
    await applyEnvVarsPull(siteId.value, { strategy: pullStrategy.value })
    toast.success('Environment variable pull queued.')
    closePullSheet()
    isMirrorConfirmOpen.value = false
    showEmptyStateBanner.value = false
    hasProbedEmptyServerEnv.value = false
  } catch {
    toast.error('Unable to pull environment variables.')
  } finally {
    isApplyingPull.value = false
  }
}

async function refreshPullPreview(): Promise<void> {
  stopPullPolling()
  await loadPullPreview(true)
  startPullPolling()
}

async function loadEnvVars(silent = false): Promise<void> {
  if (! silent) {
    isLoading.value = true
  }

  try {
    envVars.value = await fetchEnvVars(siteId.value)

    if (envVars.value.length === 0) {
      await probeEmptyServerEnv()
    } else {
      showEmptyStateBanner.value = false
      hasProbedEmptyServerEnv.value = false
    }
  } catch {
    toast.error('Unable to load environment variables.')
  } finally {
    if (! silent) {
      isLoading.value = false
    }
  }
}

function handleEnvVarsPulled(payload: { siteId: string; created: number; updated: number; deleted: number }): void {
  if (payload.siteId !== siteId.value) {
    return
  }

  if (payload.created === 0 && payload.updated === 0 && payload.deleted === 0) {
    return
  }

  void loadEnvVars(true)
  toast.success('Environment variables updated from server.')
}

if (serverId.value !== undefined && serverId.value !== '') {
  useEnvVarsSiteChannel(serverId, {
    onPullPreviewReady: handlePreviewReady,
    onPulled: handleEnvVarsPulled,
  })
}

watch(isPullSheetOpen, (open) => {
  if (! open) {
    stopPullPolling()
  }
})

watch(pullPreview, () => {
  updateEmptyStateBanner()
})

watch(isPullPreviewLoading, (loading, wasLoading) => {
  if (wasLoading === true && loading === false && pullPreview.value?.status === 'ready') {
    void nextTick(() => {
      if (hasPullDiff.value || isAlreadyInSync.value) {
        triggerDiffReadyFlash()
      }
    })
  }
})

function maskValue(id: string): string {
  if (revealedValues.value[id] !== undefined) {
    return revealedValues.value[id]
  }

  return '••••••••'
}

function isValueRevealed(id: string): boolean {
  return revealedValues.value[id] !== undefined
}

function hideRevealedValue(id: string): void {
  if (revealTimeouts.value[id] !== undefined) {
    clearTimeout(revealTimeouts.value[id])
    delete revealTimeouts.value[id]
  }

  const next = { ...revealedValues.value }
  delete next[id]
  revealedValues.value = next
}

async function handleReveal(envVar: EnvVarListItem): Promise<void> {
  try {
    const revealed = await revealEnvVar(siteId.value, envVar.id)
    revealedValues.value[envVar.id] = revealed.value
    revealFlashIds.value = { ...revealFlashIds.value, [envVar.id]: true }

    setTimeout(() => {
      const next = { ...revealFlashIds.value }
      delete next[envVar.id]
      revealFlashIds.value = next
    }, 480)

    if (revealTimeouts.value[envVar.id] !== undefined) {
      clearTimeout(revealTimeouts.value[envVar.id])
    }

    revealTimeouts.value[envVar.id] = setTimeout(() => {
      delete revealedValues.value[envVar.id]
      delete revealTimeouts.value[envVar.id]
    }, 30_000)
  } catch {
    toast.error('Unable to reveal value.')
  }
}

function startEdit(envVar: EnvVarListItem): void {
  editingId.value = envVar.id
  editingValue.value = revealedValues.value[envVar.id] ?? ''
}

async function saveEdit(envVar: EnvVarListItem): Promise<void> {
  try {
    await updateEnvVar(siteId.value, envVar.id, { value: editingValue.value })
    editingId.value = null
    editingValue.value = ''
    delete revealedValues.value[envVar.id]
    await loadEnvVars()
    toast.success('Environment variable updated.')
  } catch {
    toast.error('Unable to update environment variable.')
  }
}

async function handleAdd(): Promise<void> {
  if (newKey.value.trim() === '') {
    return
  }

  if (addMode.value === 'literal' && newValue.value.trim() === '') {
    return
  }

  if (addMode.value === 'reference' && selectedCredentialId.value === '') {
    return
  }

  isAdding.value = true

  try {
    if (addMode.value === 'reference') {
      await createEnvVar(siteId.value, {
        key: newKey.value.trim(),
        referencedCredentialId: selectedCredentialId.value,
      })
    } else {
      await createEnvVar(siteId.value, {
        key: newKey.value.trim(),
        value: newValue.value,
      })
    }

    newKey.value = ''
    newValue.value = ''
    selectedCredentialId.value = ''
    await loadEnvVars()
    toast.success('Environment variable added.')
  } catch {
    toast.error('Unable to add environment variable.')
  } finally {
    isAdding.value = false
  }
}

function openDelete(envVar: EnvVarListItem): void {
  deleteTarget.value = envVar
  isDeleteDialogOpen.value = true
}

async function confirmDelete(): Promise<void> {
  if (deleteTarget.value === null) {
    return
  }

  try {
    await deleteEnvVar(siteId.value, deleteTarget.value.id)
    await loadEnvVars()
    toast.success('Environment variable deleted.')
  } catch {
    toast.error('Unable to delete environment variable.')
  } finally {
    deleteTarget.value = null
  }
}

async function confirmSync(): Promise<void> {
  try {
    await syncEnvVars(siteId.value)
    toast.success('Environment variable push queued.')
  } catch {
    toast.error('Unable to push environment variables.')
  } finally {
    isSyncDialogOpen.value = false
  }
}

async function handleBulkImport(): Promise<void> {
  const entries = parseEnvContent(bulkImportContent.value)

  if (entries.length === 0) {
    toast.error('No valid variables found in pasted content.')

    return
  }

  isImporting.value = true

  try {
    for (const entry of entries) {
      await createEnvVar(siteId.value, entry)
    }

    bulkImportContent.value = ''
    isBulkImportOpen.value = false
    await loadEnvVars()
    toast.success(`Imported ${entries.length} environment variables.`)
  } catch {
    toast.error('Bulk import failed.')
  } finally {
    isImporting.value = false
  }
}

void loadEnvVars()

onUnmounted(() => {
  Object.values(revealTimeouts.value).forEach(timeout => clearTimeout(timeout))

  if (diffReadyFlashTimer !== null) {
    clearTimeout(diffReadyFlashTimer)
  }

  stopPullPolling()
})
</script>

<template>
  <div class="space-y-6">
    <Transition name="fade-up">
      <div
        v-if="showEmptyStateBanner"
        class="panel flex flex-col gap-3 border-primary/30 bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between"
        data-testid="env-var-empty-banner"
        role="status"
      >
        <div class="flex items-start gap-3">
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-full border border-primary/20 bg-primary/10"
            aria-hidden="true"
          >
            <ArrowDownToLineIcon class="size-4 text-primary" />
          </div>
          <p class="text-sm text-foreground">
            Variables found on the server — pull them into Helix to manage from the control plane.
          </p>
        </div>
        <div class="flex flex-wrap gap-2 sm:shrink-0">
          <Button
            type="button"
            variant="outline"
            class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
            @click="openPullSheet"
          >
            Pull from server
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label="Dismiss"
            class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
            @click="dismissEmptyBanner"
          >
            <XIcon class="size-4" />
          </Button>
        </div>
      </div>
    </Transition>

    <div class="flex flex-wrap gap-2">
      <Button
        type="button"
        variant="outline"
        class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
        data-testid="env-var-pull-button"
        @click="openPullSheet"
      >
        Pull from server
      </Button>
      <Button
        type="button"
        variant="outline"
        class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
        @click="isSyncDialogOpen = true"
      >
        Push to server
      </Button>
      <Button
        type="button"
        variant="outline"
        class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
        @click="isBulkImportOpen = true"
      >
        Bulk import
      </Button>
    </div>

    <div class="panel animate-panel-in overflow-hidden motion-reduce:animate-none">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Key</TableHead>
            <TableHead>Value</TableHead>
            <TableHead class="min-w-44 text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="3" class="text-muted-foreground">
              Loading…
            </TableCell>
          </TableRow>
          <TableRow v-else-if="envVars.length === 0">
            <TableCell colspan="3">
              <div class="flex flex-col gap-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-muted-foreground">
                  No variables in Helix yet. Import from the server or add one manually.
                </p>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
                  @click="openPullSheet"
                >
                  Pull from server
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableRow
            v-for="(envVar, index) in envVars"
            :key="envVar.id"
            class="animate-env-row-in motion-reduce:animate-none"
            :style="{ animationDelay: rowEntranceDelay(index) }"
          >
            <TableCell class="font-mono text-sm">
              <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span>{{ envVar.key }}</span>
                  <Badge
                    v-if="envVar.isReference"
                    variant="secondary"
                    class="gap-1 font-sans text-[10px] font-normal"
                  >
                    <LinkIcon class="size-3" aria-hidden="true" />
                    Linked
                  </Badge>
                </div>
                <p
                  v-if="envVar.isReference && envVar.referencedCredentialLabel"
                  class="font-sans text-xs text-muted-foreground"
                >
                  {{ envVar.referencedCredentialLabel }}
                </p>
              </div>
            </TableCell>
            <TableCell>
              <Input
                v-if="editingId === envVar.id"
                v-model="editingValue"
                class="font-mono"
                data-testid="env-var-edit-input"
              />
              <p
                v-else-if="envVar.isReference"
                class="rounded-md border border-dashed border-border bg-muted/30 px-3 py-2 font-sans text-xs text-muted-foreground"
              >
                Resolves from server credential at deploy and push time.
              </p>
              <Input
                v-else
                :model-value="maskValue(envVar.id)"
                readonly
                class="font-mono transition-colors duration-200 motion-reduce:transition-none"
                :class="{ 'env-value-revealed motion-reduce:animate-none': revealFlashIds[envVar.id] }"
                data-testid="env-var-masked-input"
              />
            </TableCell>
            <TableCell class="text-right">
              <div class="flex flex-wrap justify-end gap-1">
                <Button
                  v-if="editingId !== envVar.id && !isValueRevealed(envVar.id)"
                  type="button"
                  size="sm"
                  variant="ghost"
                  data-testid="env-var-reveal-button"
                  @click="handleReveal(envVar)"
                >
                  <EyeIcon class="size-4" aria-hidden="true" />
                  Reveal
                </Button>
                <Button
                  v-else-if="editingId !== envVar.id && isValueRevealed(envVar.id)"
                  type="button"
                  size="sm"
                  variant="ghost"
                  data-testid="env-var-hide-button"
                  @click="hideRevealedValue(envVar.id)"
                >
                  <EyeOffIcon class="size-4" aria-hidden="true" />
                  Hide
                </Button>
                <Button
                  v-if="editingId === envVar.id"
                  type="button"
                  size="sm"
                  variant="ghost"
                  @click="saveEdit(envVar)"
                >
                  Save
                </Button>
                <Button
                  v-else-if="!envVar.isReference"
                  type="button"
                  size="sm"
                  variant="ghost"
                  :aria-label="`Edit ${envVar.key}`"
                  @click="startEdit(envVar)"
                >
                  <PencilIcon class="size-4" aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  :aria-label="`Delete ${envVar.key}`"
                  @click="openDelete(envVar)"
                >
                  <Trash2Icon class="size-4" aria-hidden="true" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="panel space-y-4 p-4">
      <h3 class="text-sm font-medium">
        Add variable
      </h3>
      <div v-if="canLinkCredentials" class="flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          :variant="addMode === 'literal' ? 'default' : 'outline'"
          @click="addMode = 'literal'"
        >
          Literal value
        </Button>
        <Button
          type="button"
          size="sm"
          :variant="addMode === 'reference' ? 'default' : 'outline'"
          @click="addMode = 'reference'"
        >
          Link server credential
        </Button>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="new-env-key">Key</Label>
          <Input id="new-env-key" v-model="newKey" class="font-mono" />
        </div>
        <div v-if="addMode === 'literal'" class="space-y-2">
          <Label for="new-env-value">Value</Label>
          <Input id="new-env-value" v-model="newValue" class="font-mono" />
        </div>
        <div v-else class="space-y-2">
          <Label for="new-env-credential">Server credential</Label>
          <Select v-model="selectedCredentialId" :disabled="isLoadingLinkable">
            <SelectTrigger id="new-env-credential">
              <SelectValue :placeholder="isLoadingLinkable ? 'Loading…' : 'Select credential'" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="credential in linkableCredentials"
                :key="credential.id"
                :value="credential.id"
              >
                {{ credential.label }} · {{ credential.serviceKey }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p
            v-if="!isLoadingLinkable && linkableCredentials.length === 0"
            class="text-xs text-muted-foreground"
          >
            No linkable credentials on this server. Provision PostgreSQL, MySQL, or Redis first.
          </p>
        </div>
      </div>
      <Button type="button" :disabled="!canAddEnvVar" @click="handleAdd">
        {{ isAdding ? 'Adding…' : 'Add' }}
      </Button>
    </div>

    <ConfirmDestructiveDialog
      v-model:open="isSyncDialogOpen"
      title="Push environment variables"
      description="Push all Helix environment variables to the server. Existing server values for matching keys will be overwritten."
      :confirm-text="siteId"
      confirm-button-label="Push"
      @confirm="confirmSync"
    />

    <ConfirmDestructiveDialog
      v-model:open="isDeleteDialogOpen"
      title="Delete environment variable"
      description="This removes the variable from HelixDeploy. Push to server to apply removal on the host."
      :confirm-text="deleteTarget?.key ?? ''"
      confirm-button-label="Delete"
      @confirm="confirmDelete"
    />

    <ConfirmDestructiveDialog
      v-model:open="isMirrorConfirmOpen"
      title="Mirror server environment variables"
      description="Helix will match the server exactly. Keys that exist only in Helix will be deleted."
      :confirm-text="siteId"
      confirm-button-label="Mirror"
      @confirm="confirmPullApply"
    />

    <Sheet :open="isPullSheetOpen" @update:open="handlePullSheetOpenChange">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
        <SheetHeader>
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <SheetTitle>Pull from server</SheetTitle>
              <SheetDescription>
                Compare server environment variables with Helix before importing.
              </SheetDescription>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="shrink-0 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
              :disabled="isPullPreviewLoading"
              aria-label="Refresh preview"
              data-testid="env-var-pull-refresh"
              @click="refreshPullPreview"
            >
              <RefreshCwIcon
                class="size-4 motion-reduce:animate-none"
                :class="{ 'animate-spin': isPullPreviewLoading }"
              />
            </Button>
          </div>
        </SheetHeader>
        <SheetBody class="space-y-6">
          <Transition name="status-crossfade" mode="out-in">
            <div v-if="isPullPreviewLoading" key="loading" class="space-y-3" data-testid="env-var-pull-loading">
              <div class="flex items-center gap-2 text-muted-foreground" role="status" aria-live="polite" aria-busy="true">
                <span
                  class="inline-flex size-1.5 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
                  aria-hidden="true"
                />
                <span
                  :key="pullLoadingMessage"
                  class="log-loading-message text-sm"
                >
                  {{ pullLoadingMessage }}
                </span>
              </div>
              <Skeleton class="h-4 w-40 motion-reduce:animate-none" />
              <Skeleton class="h-20 w-full motion-reduce:animate-none" />
              <Skeleton class="h-20 w-full motion-reduce:animate-none" />
            </div>
            <p
              v-else-if="pullPreviewError !== null"
              key="error"
              class="text-sm text-destructive animate-panel-in motion-reduce:animate-none"
              role="alert"
            >
              {{ pullPreviewError }}
            </p>
            <div
              v-else-if="pullPreview?.status === 'ready'"
              key="ready"
              class="space-y-6"
              :class="{ 'env-diff-ready motion-reduce:animate-none': showDiffReadyFlash }"
            >
              <p
                v-if="!pullPreview.serverFileExists"
                class="text-sm text-muted-foreground animate-panel-in motion-reduce:animate-none"
              >
                No .env file was found on the server for this site.
              </p>
              <div
                v-else-if="isAlreadyInSync"
                class="flex items-start gap-3 rounded-md border border-primary/20 bg-primary/5 p-3 animate-panel-in motion-reduce:animate-none"
                data-testid="env-var-pull-in-sync"
                role="status"
              >
                <CheckCircle2Icon class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                <p class="text-sm text-foreground">
                  Helix matches the server for {{ pullPreview.unchanged.length }} variable{{ pullPreview.unchanged.length === 1 ? '' : 's' }}.
                </p>
              </div>
              <template v-else-if="hasPullDiff">
                <div
                  v-if="pullPreview.new.length > 0"
                  class="space-y-2 animate-panel-in motion-reduce:animate-none"
                  data-testid="env-var-pull-new"
                >
                  <h4 class="text-sm font-medium">
                    New on server ({{ pullPreview.new.length }})
                  </h4>
                  <ul class="max-h-32 space-y-1 overflow-y-auto rounded-md border bg-muted/30 p-2 font-mono text-sm text-muted-foreground">
                    <li v-for="key in pullPreview.new" :key="key">
                      {{ key }}
                    </li>
                  </ul>
                </div>
                <div
                  v-if="pullPreview.changed.length > 0"
                  class="space-y-2 animate-panel-in animate-panel-in-delay-1 motion-reduce:animate-none"
                  data-testid="env-var-pull-changed"
                >
                  <h4 class="text-sm font-medium">
                    Changed ({{ pullPreview.changed.length }})
                  </h4>
                  <ul class="max-h-32 space-y-1 overflow-y-auto rounded-md border bg-muted/30 p-2 font-mono text-sm text-muted-foreground">
                    <li v-for="key in pullPreview.changed" :key="key">
                      {{ key }}
                    </li>
                  </ul>
                </div>
                <div
                  v-if="pullPreview.helixOnly.length > 0"
                  class="space-y-2 animate-panel-in animate-panel-in-delay-2 motion-reduce:animate-none"
                  data-testid="env-var-pull-helix-only"
                >
                  <h4 class="text-sm font-medium">
                    Helix only ({{ pullPreview.helixOnly.length }})
                  </h4>
                  <ul class="max-h-32 space-y-1 overflow-y-auto rounded-md border bg-muted/30 p-2 font-mono text-sm text-muted-foreground">
                    <li v-for="key in pullPreview.helixOnly" :key="key">
                      {{ key }}
                    </li>
                  </ul>
                </div>
                <div
                  v-if="pullPreview.skipped.length > 0"
                  class="space-y-2 animate-panel-in animate-panel-in-delay-3 motion-reduce:animate-none"
                >
                  <h4 class="text-sm font-medium">
                    Skipped ({{ pullPreview.skipped.length }})
                  </h4>
                  <ul class="max-h-32 space-y-1 overflow-y-auto rounded-md border bg-muted/30 p-2 text-sm text-muted-foreground">
                    <li v-for="item in pullPreview.skipped" :key="item.key">
                      <span class="font-mono">{{ item.key }}</span> — {{ item.reason }}
                    </li>
                  </ul>
                </div>
              </template>

              <fieldset v-if="pullPreview.serverFileExists" class="space-y-3 animate-panel-in animate-panel-in-delay-2 motion-reduce:animate-none">
                <legend class="text-sm font-medium">
                  Import strategy
                </legend>
                <label
                  v-for="option in pullStrategyOptions"
                  :key="option.value"
                  :class="strategyCardClass(option.value)"
                >
                  <input
                    v-model="pullStrategy"
                    type="radio"
                    class="mt-1 accent-primary"
                    :value="option.value"
                  >
                  <span class="space-y-1">
                    <span class="block text-sm font-medium">{{ option.label }}</span>
                    <span class="block text-sm text-muted-foreground">{{ option.description }}</span>
                  </span>
                </label>
              </fieldset>
            </div>
          </Transition>
        </SheetBody>
        <SheetFooter>
          <Button variant="outline" type="button" @click="closePullSheet">
            Cancel
          </Button>
          <Button
            type="button"
            :disabled="isApplyingPull || !canApplyPull"
            data-testid="env-var-pull-apply"
            @click="requestPullApply"
          >
            {{ isApplyingPull ? 'Applying…' : 'Apply pull' }}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <Sheet v-model:open="isBulkImportOpen">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Bulk import</SheetTitle>
          <SheetDescription>
            Paste .env file contents. Each KEY=VALUE line will be created.
          </SheetDescription>
        </SheetHeader>
        <SheetBody>
          <Textarea
            v-model="bulkImportContent"
            rows="12"
            class="font-mono text-sm"
            placeholder="APP_ENV=production&#10;APP_KEY=base64:..."
          />
        </SheetBody>
        <SheetFooter>
          <Button variant="outline" type="button" @click="isBulkImportOpen = false">
            Cancel
          </Button>
          <Button type="button" :disabled="isImporting" @click="handleBulkImport">
            Import
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
