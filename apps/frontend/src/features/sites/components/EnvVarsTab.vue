<script setup lang="ts">
import { onUnmounted, ref } from 'vue'
import { EyeIcon, PencilIcon, Trash2Icon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Textarea } from '@/components/ui/textarea'
import {
  createEnvVar,
  deleteEnvVar,
  fetchEnvVars,
  revealEnvVar,
  syncEnvVars,
  updateEnvVar,
} from '@/features/sites/api'
import { parseEnvContent } from '@/lib/parseEnv'
import type { EnvVarListItem } from '@/types'

interface Props {
  siteId: string
}

const props = defineProps<Props>()

const envVars = ref<EnvVarListItem[]>([])
const isLoading = ref(true)
const newKey = ref('')
const newValue = ref('')
const isAdding = ref(false)

const revealedValues = ref<Record<string, string>>({})
const revealTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({})

const editingId = ref<string | null>(null)
const editingValue = ref('')

const deleteTarget = ref<EnvVarListItem | null>(null)
const isDeleteDialogOpen = ref(false)
const isSyncDialogOpen = ref(false)
const isBulkImportOpen = ref(false)
const bulkImportContent = ref('')
const isImporting = ref(false)

async function loadEnvVars(): Promise<void> {
  isLoading.value = true

  try {
    envVars.value = await fetchEnvVars(props.siteId)
  } catch {
    toast.error('Unable to load environment variables.')
  } finally {
    isLoading.value = false
  }
}

function maskValue(id: string): string {
  if (revealedValues.value[id] !== undefined) {
    return revealedValues.value[id]
  }

  return '••••••••'
}

async function handleReveal(envVar: EnvVarListItem): Promise<void> {
  try {
    const revealed = await revealEnvVar(props.siteId, envVar.id)
    revealedValues.value[envVar.id] = revealed.value

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
    await updateEnvVar(props.siteId, envVar.id, { value: editingValue.value })
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
  if (newKey.value.trim() === '' || newValue.value.trim() === '') {
    return
  }

  isAdding.value = true

  try {
    await createEnvVar(props.siteId, {
      key: newKey.value.trim(),
      value: newValue.value,
    })
    newKey.value = ''
    newValue.value = ''
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
    await deleteEnvVar(props.siteId, deleteTarget.value.id)
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
    await syncEnvVars(props.siteId)
    toast.success('Environment variable sync queued.')
  } catch {
    toast.error('Unable to sync environment variables.')
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
      await createEnvVar(props.siteId, entry)
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
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap gap-2">
      <Button type="button" variant="outline" @click="isSyncDialogOpen = true">
        Sync to Server
      </Button>
      <Button type="button" variant="outline" @click="isBulkImportOpen = true">
        Bulk Import
      </Button>
    </div>

    <div class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Key</TableHead>
            <TableHead>Value</TableHead>
            <TableHead class="w-40 text-right">
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
            <TableCell colspan="3" class="text-muted-foreground">
              No environment variables yet.
            </TableCell>
          </TableRow>
          <TableRow v-for="envVar in envVars" :key="envVar.id">
            <TableCell class="font-mono text-sm">
              {{ envVar.key }}
            </TableCell>
            <TableCell>
              <Input
                v-if="editingId === envVar.id"
                v-model="editingValue"
                class="font-mono"
                data-testid="env-var-edit-input"
              />
              <Input
                v-else
                :model-value="maskValue(envVar.id)"
                readonly
                class="font-mono"
                data-testid="env-var-masked-input"
              />
            </TableCell>
            <TableCell class="text-right">
              <div class="flex justify-end gap-1">
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  data-testid="env-var-reveal-button"
                  @click="handleReveal(envVar)"
                >
                  <EyeIcon class="size-4" />
                  Reveal
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
                  v-else
                  type="button"
                  size="sm"
                  variant="ghost"
                  @click="startEdit(envVar)"
                >
                  <PencilIcon class="size-4" />
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  @click="openDelete(envVar)"
                >
                  <Trash2Icon class="size-4" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div class="panel space-y-4 p-4">
      <h3 class="text-sm font-medium">
        Add Variable
      </h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="new-env-key">Key</Label>
          <Input id="new-env-key" v-model="newKey" class="font-mono" />
        </div>
        <div class="space-y-2">
          <Label for="new-env-value">Value</Label>
          <Input id="new-env-value" v-model="newValue" class="font-mono" />
        </div>
      </div>
      <Button type="button" :disabled="isAdding" @click="handleAdd">
        Add
      </Button>
    </div>

    <ConfirmDestructiveDialog
      v-model:open="isSyncDialogOpen"
      title="Sync environment variables"
      description="Push all environment variables to the server. Existing server values for managed keys may be overwritten."
      :confirm-text="siteId"
      confirm-button-label="Sync"
      @confirm="confirmSync"
    />

    <ConfirmDestructiveDialog
      v-model:open="isDeleteDialogOpen"
      title="Delete environment variable"
      description="This removes the variable from HelixDeploy. Sync to server to apply removal on the host."
      :confirm-text="deleteTarget?.key ?? ''"
      confirm-button-label="Delete"
      @confirm="confirmDelete"
    />

    <Sheet v-model:open="isBulkImportOpen">
      <SheetContent class="sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Bulk import</SheetTitle>
          <SheetDescription>
            Paste .env file contents. Each KEY=VALUE line will be created.
          </SheetDescription>
        </SheetHeader>
        <Textarea
          v-model="bulkImportContent"
          rows="12"
          class="mt-4 font-mono text-sm"
          placeholder="APP_ENV=production&#10;APP_KEY=base64:..."
        />
        <SheetFooter class="mt-4 flex-row justify-end gap-2 border-t pt-4">
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
