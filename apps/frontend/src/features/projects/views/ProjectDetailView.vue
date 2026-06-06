<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { PlusIcon, Trash2Icon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EnvironmentBadge from '@/components/common/EnvironmentBadge.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import BackLink from '@/components/layout/BackLink.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
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
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import {
  createEnvironment,
  deleteEnvironment,
  deleteProject,
  fetchProject,
  fetchProjectEnvironments,
  updateEnvironment,
  updateProject,
} from '@/features/projects/api'
import ProjectDnsZonesSection from '@/features/integrations/components/ProjectDnsZonesSection.vue'
import type { EnvironmentRecord, ProjectRecord } from '@/features/projects/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const project = ref<ProjectRecord | null>(null)
const environments = ref<EnvironmentRecord[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const projectName = ref('')
const projectDescription = ref('')
const isSavingProject = ref(false)

const isEnvSheetOpen = ref(false)
const editingEnvironmentId = ref<string | null>(null)
const envName = ref('')
const envLabel = ref('')
const envIsProduction = ref(false)
const isSavingEnvironment = ref(false)

const isDeleteProjectOpen = ref(false)
const isDeletingProject = ref(false)
const deleteEnvironmentTarget = ref<EnvironmentRecord | null>(null)
const isDeletingEnvironment = ref(false)

const projectId = computed(() => String(route.params.id))
const canManage = computed(() => authStore.isAdmin)
const hasProductionEnvironment = computed(
  () => environments.value.some((environment) => environment.isProduction),
)

const environmentSheetTitle = computed(
  () => (editingEnvironmentId.value === null ? 'Add environment' : 'Edit environment'),
)

const isProjectNameEmpty = computed(
  () => (projectName.value ?? '').trim() === '',
)

const isEnvNameEmpty = computed(
  () => (envName.value ?? '').trim() === '',
)

function environmentDisplayName(environment: EnvironmentRecord): string {
  return environment.label ?? environment.name
}

async function load(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    const [projectData, environmentData] = await Promise.all([
      fetchProject(projectId.value),
      fetchProjectEnvironments(projectId.value),
    ])

    project.value = projectData
    environments.value = environmentData
    projectName.value = projectData.name ?? ''
    projectDescription.value = projectData.description ?? ''
  } catch {
    loadError.value = 'Unable to load this project.'
  } finally {
    isLoading.value = false
  }
}

async function saveProject(): Promise<void> {
  const name = (projectName.value ?? '').trim()

  if (project.value === null || name === '') {
    return
  }

  isSavingProject.value = true

  try {
    project.value = await updateProject(projectId.value, {
      name,
      description: (projectDescription.value ?? '').trim() === ''
        ? null
        : (projectDescription.value ?? '').trim(),
    })
    projectName.value = project.value.name ?? name
    toast.success('Project updated.')
  } catch {
    toast.error('Unable to update project.')
  } finally {
    isSavingProject.value = false
  }
}

function openEnvironmentSheet(environment?: EnvironmentRecord): void {
  if (environment === undefined) {
    editingEnvironmentId.value = null
    envName.value = ''
    envLabel.value = ''
    envIsProduction.value = false
  } else {
    editingEnvironmentId.value = environment.id
    envName.value = environment.name
    envLabel.value = environment.label ?? ''
    envIsProduction.value = environment.isProduction
  }

  isEnvSheetOpen.value = true
}

function applyEnvironmentPreset(name: string, label: string, isProduction: boolean): void {
  envName.value = name
  envLabel.value = label
  envIsProduction.value = isProduction
}

async function saveEnvironment(): Promise<void> {
  const name = (envName.value ?? '').trim()

  if (name === '') {
    return
  }

  isSavingEnvironment.value = true

  const payload = {
    name: name.toLowerCase(),
    label: (envLabel.value ?? '').trim() === '' ? null : (envLabel.value ?? '').trim(),
    isProduction: envIsProduction.value,
  }

  try {
    if (editingEnvironmentId.value === null) {
      const created = await createEnvironment(projectId.value, payload)
      environments.value = [...environments.value, created].sort(
        (left, right) => left.name.localeCompare(right.name),
      )
      toast.success('Environment created.')
    } else {
      const updated = await updateEnvironment(
        projectId.value,
        editingEnvironmentId.value,
        payload,
      )
      environments.value = environments.value.map(
        (environment) => (environment.id === updated.id ? updated : environment),
      )
      toast.success('Environment updated.')
    }

    isEnvSheetOpen.value = false
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null
      ? null
      : (firstFieldError(fieldErrors, 'name') ?? firstFieldError(fieldErrors, 'environment'))

    toast.error(message ?? 'Unable to save environment.')
  } finally {
    isSavingEnvironment.value = false
  }
}

async function confirmDeleteProject(): Promise<void> {
  isDeletingProject.value = true

  try {
    await deleteProject(projectId.value)
    toast.success('Project deleted.')
    await router.push('/projects')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    toast.error(
      fieldErrors === null
        ? 'Unable to delete project.'
        : (firstFieldError(fieldErrors, 'project') ?? 'Unable to delete project.'),
    )
    isDeleteProjectOpen.value = false
  } finally {
    isDeletingProject.value = false
  }
}

async function confirmDeleteEnvironment(): Promise<void> {
  if (deleteEnvironmentTarget.value === null) {
    return
  }

  isDeletingEnvironment.value = true

  try {
    await deleteEnvironment(projectId.value, deleteEnvironmentTarget.value.id)
    environments.value = environments.value.filter(
      (environment) => environment.id !== deleteEnvironmentTarget.value?.id,
    )
    deleteEnvironmentTarget.value = null
    toast.success('Environment deleted.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    toast.error(
      fieldErrors === null
        ? 'Unable to delete environment.'
        : (firstFieldError(fieldErrors, 'environment') ?? 'Unable to delete environment.'),
    )
  } finally {
    isDeletingEnvironment.value = false
  }
}

watch(projectId, () => {
  void load()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-8">
    <BackLink to="/projects" label="Projects" />

    <div v-if="isLoading" class="space-y-4" data-testid="project-detail-loading">
      <Skeleton class="h-10 w-64" />
      <Skeleton class="h-32 w-full rounded-lg" />
      <Skeleton class="h-48 w-full rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel border-dashed p-8 text-center"
      data-testid="project-detail-error"
    >
      <p class="text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="load">
        Try again
      </Button>
    </div>

    <template v-else-if="project !== null">
      <PageHeader
        :title="project.name"
        :description="project.description ?? 'No description provided.'"
      >
        <template v-if="canManage" #actions>
          <Button type="button" variant="outline" @click="isDeleteProjectOpen = true">
            <Trash2Icon class="mr-2 size-4" />
            Delete
          </Button>
        </template>
      </PageHeader>

      <ProductionWarningBanner
        v-if="hasProductionEnvironment"
        :resource-name="project.name"
        :is-production="true"
        message="This project includes production environments. Treat server and deployment changes with extra care."
      />

      <section v-if="canManage" class="panel space-y-4 p-6" data-testid="project-settings">
        <div>
          <h2 class="text-sm font-medium text-foreground">
            Project settings
          </h2>
          <p class="mt-1 text-sm text-muted-foreground">
            Update how this project appears across servers and sites.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label for="edit-project-name">Name</Label>
            <Input id="edit-project-name" v-model="projectName" />
          </div>
          <div class="space-y-2 md:col-span-2">
            <Label for="edit-project-description">Description</Label>
            <Textarea
              id="edit-project-description"
              v-model="projectDescription"
              rows="2"
            />
          </div>
        </div>

        <div class="flex justify-end">
          <Button
            type="button"
            :disabled="isSavingProject || isProjectNameEmpty"
            @click="saveProject"
          >
            Save changes
          </Button>
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-sm font-medium text-foreground">
              Environments
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Map staging and production targets for servers and sites in this project.
            </p>
          </div>

          <Button
            v-if="canManage"
            type="button"
            variant="outline"
            @click="openEnvironmentSheet()"
          >
            <PlusIcon class="mr-2 size-4" />
            Add environment
          </Button>
        </div>

        <div
          v-if="environments.length === 0"
          class="panel border-dashed p-8 text-center"
          data-testid="environments-empty"
        >
          <p class="text-sm text-muted-foreground">
            No environments yet. Add development, staging, or production before assigning servers.
          </p>
          <Button
            v-if="canManage"
            type="button"
            class="mt-4"
            @click="openEnvironmentSheet()"
          >
            Add environment
          </Button>
        </div>

        <div v-else class="panel overflow-hidden" data-testid="environments-table">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Environment</TableHead>
                <TableHead class="hidden sm:table-cell">
                  Identifier
                </TableHead>
                <TableHead v-if="canManage" class="w-28 text-right">
                  Actions
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="environment in environments" :key="environment.id">
                <TableCell>
                  <EnvironmentBadge
                    :environment="environmentDisplayName(environment)"
                    :is-production="environment.isProduction"
                  />
                </TableCell>
                <TableCell class="hidden font-mono text-sm text-muted-foreground sm:table-cell">
                  {{ environment.name }}
                </TableCell>
                <TableCell v-if="canManage" class="text-right">
                  <div class="flex justify-end gap-2">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      @click="openEnvironmentSheet(environment)"
                    >
                      Edit
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="text-destructive hover:text-destructive"
                      @click="deleteEnvironmentTarget = environment"
                    >
                      Delete
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </section>

      <ProjectDnsZonesSection
        :project-id="projectId"
        :can-manage="canManage"
      />
    </template>

    <Sheet v-model:open="isEnvSheetOpen">
      <SheetContent side="right" class="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{{ environmentSheetTitle }}</SheetTitle>
          <SheetDescription>
            Environments control production guards and deployment confirmations for linked resources.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-5">
          <div v-if="editingEnvironmentId === null" class="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              @click="applyEnvironmentPreset('development', 'Development', false)"
            >
              Development
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              @click="applyEnvironmentPreset('staging', 'Staging', false)"
            >
              Staging
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              @click="applyEnvironmentPreset('production', 'Production', true)"
            >
              Production
            </Button>
          </div>

          <div class="space-y-2">
            <Label for="env-name">Identifier</Label>
            <Input
              id="env-name"
              v-model="envName"
              placeholder="staging"
              autocomplete="off"
            />
            <p class="text-xs text-muted-foreground">
              Lowercase slug used in API and server assignment.
            </p>
          </div>

          <div class="space-y-2">
            <Label for="env-label">Display label</Label>
            <Input
              id="env-label"
              v-model="envLabel"
              placeholder="Staging"
              autocomplete="off"
            />
          </div>

          <div class="flex items-start gap-3 rounded-md border border-border p-3">
            <input
              id="env-production"
              v-model="envIsProduction"
              type="checkbox"
              class="mt-0.5 size-4 rounded border border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
            <div class="space-y-1">
              <Label for="env-production" class="cursor-pointer">
                Production environment
              </Label>
              <p class="text-xs text-muted-foreground">
                Enables mandatory confirmations for deploys, rollbacks, and remote commands.
              </p>
            </div>
          </div>
        </SheetBody>

        <SheetFooter>
          <Button
            type="button"
            :disabled="isSavingEnvironment || isEnvNameEmpty"
            @click="saveEnvironment"
          >
            Save environment
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <ConfirmDestructiveDialog
      v-model:open="isDeleteProjectOpen"
      title="Delete project"
      :description="`This permanently removes ${project?.name ?? 'this project'}. Remove all servers and sites first.`"
      :confirm-text="project?.name ?? ''"
      confirm-button-label="Delete project"
      :can-confirm="!isDeletingProject"
      @confirm="confirmDeleteProject"
    />

    <ConfirmDestructiveDialog
      v-if="deleteEnvironmentTarget !== null"
      :open="deleteEnvironmentTarget !== null"
      title="Delete environment"
      :description="`Remove ${environmentDisplayName(deleteEnvironmentTarget)} from this project. Unassign servers and sites first.`"
      :confirm-text="deleteEnvironmentTarget.name"
      confirm-button-label="Delete environment"
      :can-confirm="!isDeletingEnvironment"
      @update:open="(value) => { if (!value) deleteEnvironmentTarget = null }"
      @confirm="confirmDeleteEnvironment"
    />
  </div>
</template>
