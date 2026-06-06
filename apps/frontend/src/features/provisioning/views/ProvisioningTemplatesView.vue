<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { LayersIcon, PlusIcon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
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
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import {
  createProvisioningTemplate,
  deleteProvisioningTemplate,
  fetchProvisioningTemplates,
  updateProvisioningTemplate,
} from '@/features/provisioning/api'
import {
  formatProvisioningTemplateName,
  servicesSummary,
} from '@/features/provisioning/constants'
import type {
  ProvisioningTemplateFormState,
  ProvisioningTemplateRecord,
} from '@/features/provisioning/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const ProvisioningTemplateSheet = defineAsyncComponent(
  () => import('@/features/provisioning/components/ProvisioningTemplateSheet.vue'),
)

const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const templates = ref<ProvisioningTemplateRecord[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isSheetOpen = ref(false)
const sheetMode = ref<'create' | 'edit' | 'view'>('create')
const activeTemplate = ref<ProvisioningTemplateRecord | null>(null)
const isSubmitting = ref(false)
const isDeleteDialogOpen = ref(false)
const deletingTemplate = ref<ProvisioningTemplateRecord | null>(null)
const isDeleting = ref(false)

const canManage = computed(() => authStore.isAdmin)

const customTemplates = computed(() => templates.value.filter(template => !template.isSystem))
const isEmpty = computed(
  () => !isLoading.value && loadError.value === null && templates.value.length === 0,
)

function buildPayload(state: ProvisioningTemplateFormState): {
  name: string
  description: string | null
  services: string[]
  options: { phpVersion?: string; nodeVersion?: number }
} {
  const options: { phpVersion?: string; nodeVersion?: number } = {}

  if (state.services.includes('php')) {
    options.phpVersion = state.phpVersion
  }

  if (state.services.includes('nodejs')) {
    options.nodeVersion = state.nodeVersion
  }

  return {
    name: state.name.trim(),
    description: state.description.trim() === '' ? null : state.description.trim(),
    services: state.services,
    options,
  }
}

async function loadTemplates(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    templates.value = []

    return
  }

  isLoading.value = true
  loadError.value = null

  try {
    templates.value = await fetchProvisioningTemplates(activeOrgId)
  } catch {
    templates.value = []
    loadError.value = 'Unable to load provisioning templates.'
  } finally {
    isLoading.value = false
  }
}

function openCreateSheet(): void {
  activeTemplate.value = null
  sheetMode.value = 'create'
  isSheetOpen.value = true
}

function openViewSheet(template: ProvisioningTemplateRecord): void {
  activeTemplate.value = template
  sheetMode.value = 'view'
  isSheetOpen.value = true
}

function openEditSheet(template: ProvisioningTemplateRecord): void {
  activeTemplate.value = template
  sheetMode.value = 'edit'
  isSheetOpen.value = true
}

function openDeleteDialog(template: ProvisioningTemplateRecord): void {
  deletingTemplate.value = template
  isDeleteDialogOpen.value = true
}

async function handleSheetSubmit(state: ProvisioningTemplateFormState): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isSubmitting.value = true

  try {
    if (sheetMode.value === 'create') {
      const created = await createProvisioningTemplate(activeOrgId, buildPayload(state))
      templates.value = [created, ...templates.value]
      toast.success('Template created.')
    } else if (activeTemplate.value !== null) {
      const updated = await updateProvisioningTemplate(
        activeTemplate.value.id,
        buildPayload(state),
      )
      templates.value = templates.value.map(template =>
        template.id === updated.id ? updated : template,
      )
      toast.success('Template updated.')
    }

    isSheetOpen.value = false
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to save template.')
  } finally {
    isSubmitting.value = false
  }
}

async function handleDeleteTemplate(): Promise<void> {
  if (deletingTemplate.value === null) {
    return
  }

  const templateId = deletingTemplate.value.id
  isDeleting.value = true

  try {
    await deleteProvisioningTemplate(templateId)
    templates.value = templates.value.filter(template => template.id !== templateId)
    deletingTemplate.value = null
    isDeleteDialogOpen.value = false
    toast.success('Template deleted.')
  } catch {
    toast.error('Unable to delete template.')
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  void loadTemplates()
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Provisioning templates"
      description="Reusable service stacks for server provisioning. System templates are built in; create custom templates for your organization."
    >
      <template v-if="canManage" #actions>
        <Button type="button" data-testid="create-template-button" @click="openCreateSheet">
          <PlusIcon class="mr-2 size-4" aria-hidden="true" />
          Create template
        </Button>
      </template>
    </PageHeader>

    <div
      v-if="isLoading"
      class="space-y-3"
      data-testid="provisioning-templates-loading"
    >
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel border-dashed p-8 text-center"
      data-testid="provisioning-templates-error"
    >
      <p class="text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="loadTemplates">
        Try again
      </Button>
    </div>

    <EmptyState
      v-else-if="isEmpty"
      :icon="LayersIcon"
      title="No templates available"
      description="System templates should appear after seeding. Admins can create organization-specific stacks."
      data-testid="provisioning-templates-empty"
    />

    <div
      v-else
      class="panel overflow-hidden"
      data-testid="provisioning-templates-table"
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Services</TableHead>
            <TableHead class="hidden md:table-cell">
              Description
            </TableHead>
            <TableHead class="text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="template in templates"
            :key="template.id"
            class="cursor-pointer transition-colors hover:bg-muted/50"
            @click="openViewSheet(template)"
          >
            <TableCell class="font-medium">
              {{ formatProvisioningTemplateName(template.name) }}
              <span class="mt-0.5 block font-mono text-xs text-muted-foreground">
                {{ template.name }}
              </span>
            </TableCell>
            <TableCell>
              <Badge :variant="template.isSystem ? 'secondary' : 'outline'">
                {{ template.isSystem ? 'System' : 'Custom' }}
              </Badge>
            </TableCell>
            <TableCell>
              {{ servicesSummary(template.services) }}
            </TableCell>
            <TableCell class="hidden max-w-xs truncate text-muted-foreground md:table-cell">
              {{ template.description ?? '—' }}
            </TableCell>
            <TableCell class="text-right">
              <Button
                type="button"
                size="sm"
                variant="ghost"
                @click.stop="openViewSheet(template)"
              >
                View
              </Button>
              <Button
                v-if="canManage && !template.isSystem"
                type="button"
                size="sm"
                variant="ghost"
                data-testid="edit-provisioning-template"
                @click.stop="openEditSheet(template)"
              >
                Edit
              </Button>
              <Button
                v-if="canManage && !template.isSystem"
                type="button"
                size="sm"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                data-testid="delete-provisioning-template"
                @click.stop="openDeleteDialog(template)"
              >
                Delete
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <p
      v-if="!isLoading && loadError === null && customTemplates.length === 0 && templates.length > 0"
      class="text-sm text-muted-foreground"
    >
      Only system templates exist. Create a custom template to save your organization's default stack.
    </p>

    <ProvisioningTemplateSheet
      v-model:open="isSheetOpen"
      :mode="sheetMode"
      :template="activeTemplate"
      :is-submitting="isSubmitting"
      @submit="handleSheetSubmit"
    />

    <ConfirmDestructiveDialog
      v-if="deletingTemplate !== null"
      v-model:open="isDeleteDialogOpen"
      title="Delete provisioning template"
      :description="`This permanently removes ${formatProvisioningTemplateName(deletingTemplate.name)}. Existing servers are not affected.`"
      :confirm-text="deletingTemplate.name"
      confirm-button-label="Delete template"
      :can-confirm="!isDeleting"
      @confirm="handleDeleteTemplate"
    />
  </div>
</template>
