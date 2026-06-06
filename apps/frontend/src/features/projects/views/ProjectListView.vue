<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { FolderKanbanIcon, PlusIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadErrorPanel from '@/components/common/LoadErrorPanel.vue'
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
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { createProject, fetchProjects } from '@/features/projects/api'
import type { ProjectRecord } from '@/features/projects/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const projects = ref<ProjectRecord[]>([])
const isLoading = ref(true)
const fetchError = ref<string | null>(null)
const isCreateOpen = ref(false)
const isSubmitting = ref(false)
const projectName = ref('')
const projectDescription = ref('')

const canManage = computed(() => authStore.isAdmin)

const isEmpty = computed(
  () => !isLoading.value && fetchError.value === null && projects.value.length === 0,
)

async function loadProjects(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isLoading.value = true
  fetchError.value = null

  try {
    projects.value = await fetchProjects(activeOrgId)
  } catch {
    fetchError.value = 'Unable to load projects.'
  } finally {
    isLoading.value = false
  }
}

async function submitCreate(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || projectName.value.trim() === '') {
    return
  }

  isSubmitting.value = true

  try {
    const created = await createProject(activeOrgId, {
      name: projectName.value.trim(),
      description: projectDescription.value.trim() === ''
        ? null
        : projectDescription.value.trim(),
    })

    projects.value = [created, ...projects.value]
    isCreateOpen.value = false
    projectName.value = ''
    projectDescription.value = ''
    toast.success('Project created.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to create project.')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  void loadProjects()
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Projects"
      description="Group servers and sites by application, client, or product line."
    >
      <template v-if="canManage" #actions>
        <Button type="button" @click="isCreateOpen = true">
          <PlusIcon class="mr-2 size-4" />
          New Project
        </Button>
      </template>
    </PageHeader>

    <div v-if="isLoading" class="space-y-2" data-testid="projects-loading">
      <Skeleton v-for="index in 4" :key="index" class="h-12 w-full rounded-md" />
    </div>

    <LoadErrorPanel
      v-else-if="fetchError !== null"
      :message="fetchError"
      data-testid="projects-error"
      @retry="loadProjects"
    />

    <EmptyState
      v-else-if="isEmpty"
      title="No projects yet"
      description="Create a project to organize servers and environments before you deploy."
      :icon="FolderKanbanIcon"
      data-testid="projects-empty"
      @action="canManage ? (isCreateOpen = true) : undefined"
    >
      <template v-if="canManage">
        <PlusIcon class="mr-2 size-4" />
        New Project
      </template>
    </EmptyState>

    <div v-else class="panel overflow-hidden" data-testid="projects-table">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead class="hidden md:table-cell">
              Description
            </TableHead>
            <TableHead class="w-28 text-right">
              Environments
            </TableHead>
            <TableHead class="w-24 text-right">
              Servers
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="project in projects" :key="project.id">
            <TableCell class="font-medium">
              <RouterLink
                :to="`/projects/${project.id}`"
                class="text-foreground transition-colors hover:text-primary"
              >
                {{ project.name }}
              </RouterLink>
            </TableCell>
            <TableCell class="hidden max-w-md truncate text-muted-foreground md:table-cell">
              {{ project.description ?? '—' }}
            </TableCell>
            <TableCell class="text-right tabular-nums text-muted-foreground">
              {{ project.environmentsCount ?? 0 }}
            </TableCell>
            <TableCell class="text-right tabular-nums text-muted-foreground">
              {{ project.serversCount ?? 0 }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isCreateOpen">
      <SheetContent side="right" class="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>New project</SheetTitle>
          <SheetDescription>
            Projects separate infrastructure by application or client. Add environments on the next screen.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label for="project-name">Name</Label>
            <Input
              id="project-name"
              v-model="projectName"
              placeholder="Billing API"
              autocomplete="off"
            />
          </div>

          <div class="space-y-2">
            <Label for="project-description">Description</Label>
            <Textarea
              id="project-description"
              v-model="projectDescription"
              rows="3"
              placeholder="Optional context for your team"
            />
          </div>
        </SheetBody>

        <SheetFooter>
          <Button
            type="button"
            :disabled="isSubmitting || projectName.trim() === ''"
            @click="submitCreate"
          >
            Create project
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
