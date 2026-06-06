<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { PlusIcon, UsersIcon } from '@lucide/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
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
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { createTeam, fetchTeams } from '@/features/teams/api'
import type { TeamRecord } from '@/features/teams/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const teams = ref<TeamRecord[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isCreateOpen = ref(false)
const isSubmitting = ref(false)
const teamName = ref('')

const canManage = computed(() => authStore.isAdmin)

const isEmpty = computed(
  () => !isLoading.value && loadError.value === null && teams.value.length === 0,
)

function projectScopeLabel(team: TeamRecord): string {
  if (team.projectIds.length === 0) {
    return 'All projects'
  }

  return `${team.projectIds.length} project${team.projectIds.length === 1 ? '' : 's'}`
}

async function loadTeams(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    teams.value = []

    return
  }

  isLoading.value = true
  loadError.value = null

  try {
    teams.value = await fetchTeams(activeOrgId)
  } catch {
    teams.value = []
    loadError.value = 'Unable to load teams.'
  } finally {
    isLoading.value = false
  }
}

async function submitCreate(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null || teamName.value.trim() === '') {
    return
  }

  isSubmitting.value = true

  try {
    const created = await createTeam(activeOrgId, { name: teamName.value.trim() })
    teams.value = [created, ...teams.value]
    isCreateOpen.value = false
    teamName.value = ''
    toast.success('Team created.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to create team.')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  void loadTeams()
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Teams"
      description="Group members and optionally limit which projects and servers they can access."
    >
      <template v-if="canManage" #actions>
        <Button type="button" data-testid="create-team-button" @click="isCreateOpen = true">
          <PlusIcon class="mr-2 size-4" aria-hidden="true" />
          Create team
        </Button>
      </template>
    </PageHeader>

    <div
      v-if="isLoading"
      class="space-y-3"
      data-testid="teams-loading"
    >
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel border-dashed p-8 text-center"
      data-testid="teams-error"
    >
      <p class="text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="loadTeams">
        Try again
      </Button>
    </div>

    <EmptyState
      v-else-if="isEmpty"
      :icon="UsersIcon"
      title="No teams yet"
      :description="canManage
        ? 'Create a team to assign members and optionally scope them to specific projects.'
        : 'You are not assigned to any teams in this organization.'"
      data-testid="teams-empty"
      @action="isCreateOpen = true"
    >
      <template v-if="canManage">
        <PlusIcon class="mr-2 size-4" aria-hidden="true" />
        Create team
      </template>
    </EmptyState>

    <div
      v-else
      class="panel overflow-hidden"
      data-testid="teams-table"
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Members</TableHead>
            <TableHead>Project access</TableHead>
            <TableHead class="hidden sm:table-cell">
              Updated
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="team in teams"
            :key="team.id"
            class="cursor-pointer"
            @click="$router.push(`/settings/teams/${team.id}`)"
          >
            <TableCell class="font-medium">
              <RouterLink
                :to="`/settings/teams/${team.id}`"
                class="text-foreground hover:text-primary"
                @click.stop
              >
                {{ team.name }}
              </RouterLink>
            </TableCell>
            <TableCell>
              {{ team.memberCount }}
            </TableCell>
            <TableCell>
              <Badge variant="secondary" class="font-normal">
                {{ projectScopeLabel(team) }}
              </Badge>
            </TableCell>
            <TableCell class="hidden text-muted-foreground sm:table-cell">
              {{ new Date(team.updatedAt).toLocaleDateString() }}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isCreateOpen">
      <SheetContent side="right" class="flex w-full flex-col sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Create team</SheetTitle>
          <SheetDescription>
            Teams group organization members. Leave project access empty to grant full visibility.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label for="team-name">Team name</Label>
            <Input
              id="team-name"
              v-model="teamName"
              placeholder="Platform engineering"
              data-testid="team-name-input"
            />
          </div>
        </SheetBody>

        <SheetFooter>
          <Button type="button" variant="outline" @click="isCreateOpen = false">
            Cancel
          </Button>
          <Button
            type="button"
            :disabled="isSubmitting || teamName.trim() === ''"
            data-testid="team-create-submit"
            @click="submitCreate"
          >
            {{ isSubmitting ? 'Creating…' : 'Create team' }}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </div>
</template>
