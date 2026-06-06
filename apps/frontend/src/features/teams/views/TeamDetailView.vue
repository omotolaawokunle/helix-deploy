<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { PlusIcon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import BackLink from '@/components/layout/BackLink.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
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
import { useActiveOrg } from '@/composables/useActiveOrg'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { fetchOrganizationMembers } from '@/features/organizations/api'
import { fetchProjects } from '@/features/projects/api'
import type { ProjectRecord } from '@/features/projects/types'
import {
  addTeamMember,
  deleteTeam,
  fetchTeam,
  fetchTeamMembers,
  removeTeamMember,
  syncTeamProjects,
  updateTeam,
  updateTeamMemberRole,
} from '@/features/teams/api'
import {
  TEAM_MEMBER_ROLE_OPTIONS,
  type TeamMemberRecord,
  type TeamRecord,
} from '@/features/teams/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'
import { TeamRole, type OrganizationMemberRecord } from '@/types'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { orgId } = useActiveOrg()

const team = ref<TeamRecord | null>(null)
const members = ref<TeamMemberRecord[]>([])
const orgMembers = ref<OrganizationMemberRecord[]>([])
const projects = ref<ProjectRecord[]>([])
const selectedProjectIds = ref<string[]>([])
const teamName = ref('')
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isSavingName = ref(false)
const isSavingProjects = ref(false)
const isDeleteDialogOpen = ref(false)
const isDeleting = ref(false)
const addMemberUserId = ref<string | undefined>(undefined)
const addMemberRole = ref<TeamRole>(TeamRole.Developer)
const isAddingMember = ref(false)

const teamId = computed(() => String(route.params.id))

const canManage = computed(() => authStore.isAdmin)

const isProjectScopeUnrestricted = computed(() => selectedProjectIds.value.length === 0)

const availableOrgMembers = computed(() => {
  const memberIds = new Set(members.value.map(member => member.id))

  return orgMembers.value.filter(member => !memberIds.has(member.id))
})

const projectScopeSummary = computed((): string => {
  if (team.value === null) {
    return ''
  }

  if (team.value.projectIds.length === 0) {
    return 'All projects'
  }

  return `${team.value.projectIds.length} project${team.value.projectIds.length === 1 ? '' : 's'}`
})

function toggleProject(projectId: string): void {
  if (selectedProjectIds.value.includes(projectId)) {
    selectedProjectIds.value = selectedProjectIds.value.filter(id => id !== projectId)

    return
  }

  selectedProjectIds.value = [...selectedProjectIds.value, projectId]
}

async function loadTeamDetail(): Promise<void> {
  const activeOrgId = orgId.value

  if (activeOrgId === null) {
    return
  }

  isLoading.value = true
  loadError.value = null

  try {
    const [teamData, membersData, projectsData, organizationMembers] = await Promise.all([
      fetchTeam(teamId.value),
      fetchTeamMembers(teamId.value),
      fetchProjects(activeOrgId),
      fetchOrganizationMembers(activeOrgId),
    ])

    team.value = teamData
    members.value = membersData
    projects.value = projectsData
    orgMembers.value = organizationMembers
    teamName.value = teamData.name
    selectedProjectIds.value = [...teamData.projectIds]
  } catch {
    team.value = null
    loadError.value = 'Unable to load team.'
  } finally {
    isLoading.value = false
  }
}

async function saveTeamName(): Promise<void> {
  if (team.value === null || teamName.value.trim() === '') {
    return
  }

  isSavingName.value = true

  try {
    team.value = await updateTeam(team.value.id, { name: teamName.value.trim() })
    toast.success('Team updated.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to update team.')
  } finally {
    isSavingName.value = false
  }
}

async function saveProjectScope(): Promise<void> {
  if (team.value === null) {
    return
  }

  isSavingProjects.value = true

  try {
    team.value = await syncTeamProjects(team.value.id, selectedProjectIds.value)
    selectedProjectIds.value = [...team.value.projectIds]
    toast.success('Project access updated.')
  } catch {
    toast.error('Unable to update project access.')
  } finally {
    isSavingProjects.value = false
  }
}

async function handleAddMember(): Promise<void> {
  if (team.value === null || addMemberUserId.value === undefined) {
    return
  }

  isAddingMember.value = true

  try {
    await addTeamMember(team.value.id, {
      userId: addMemberUserId.value,
      role: addMemberRole.value,
    })
    members.value = await fetchTeamMembers(team.value.id)
    if (team.value !== null) {
      team.value = { ...team.value, memberCount: members.value.length }
    }
    addMemberUserId.value = undefined
    addMemberRole.value = TeamRole.Developer
    toast.success('Member added.')
  } catch {
    toast.error('Unable to add member.')
  } finally {
    isAddingMember.value = false
  }
}

async function changeMemberRole(memberId: string, role: TeamRole): Promise<void> {
  if (team.value === null) {
    return
  }

  try {
    await updateTeamMemberRole(team.value.id, memberId, role)
    members.value = await fetchTeamMembers(team.value.id)
    toast.success('Member role updated.')
  } catch {
    toast.error('Unable to update member role.')
  }
}

async function handleRemoveMember(memberId: string): Promise<void> {
  if (team.value === null) {
    return
  }

  try {
    await removeTeamMember(team.value.id, memberId)
    members.value = await fetchTeamMembers(team.value.id)
    if (team.value !== null) {
      team.value = { ...team.value, memberCount: members.value.length }
    }
    toast.success('Member removed.')
  } catch {
    toast.error('Unable to remove member.')
  }
}

async function handleDeleteTeam(): Promise<void> {
  if (team.value === null) {
    return
  }

  isDeleting.value = true

  try {
    await deleteTeam(team.value.id)
    toast.success('Team deleted.')
    await router.push('/settings/teams')
  } catch {
    toast.error('Unable to delete team.')
  } finally {
    isDeleting.value = false
  }
}

watch(
  () => route.params.id,
  () => {
    void loadTeamDetail()
  },
)

onMounted(() => {
  void loadTeamDetail()
})
</script>

<template>
  <div class="space-y-8">
    <BackLink to="/settings/teams" label="Back to teams" />

    <div
      v-if="isLoading"
      class="space-y-4"
      data-testid="team-detail-loading"
    >
      <Skeleton class="h-10 w-64 rounded-lg" />
      <Skeleton class="h-48 w-full rounded-lg" />
      <Skeleton class="h-64 w-full rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null || team === null"
      class="panel border-dashed p-8 text-center"
      data-testid="team-detail-error"
    >
      <p class="text-muted-foreground">
        {{ loadError ?? 'Team not found.' }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="loadTeamDetail">
        Try again
      </Button>
    </div>

    <template v-else>
      <PageHeader
        :title="team.name"
        :description="`Slug: ${team.slug} · ${team.memberCount} member${team.memberCount === 1 ? '' : 's'} · ${projectScopeSummary}`"
      />

      <form
        v-if="canManage"
        class="panel max-w-lg space-y-4 p-6"
        data-testid="team-rename-form"
        @submit.prevent="saveTeamName"
      >
        <h2 class="section-label">
          Team details
        </h2>
        <div class="space-y-2">
          <Label for="team-rename">Name</Label>
          <Input id="team-rename" v-model="teamName" />
        </div>
        <Button type="submit" :disabled="isSavingName || teamName.trim() === ''">
          {{ isSavingName ? 'Saving…' : 'Save name' }}
        </Button>
      </form>

      <section class="space-y-4">
        <div class="flex items-center justify-between gap-4">
          <h2 class="section-label">
            Members
          </h2>
          <Badge variant="outline">
            {{ members.length }} total
          </Badge>
        </div>

        <div class="panel overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Role</TableHead>
                <TableHead class="hidden sm:table-cell">
                  Joined
                </TableHead>
                <TableHead v-if="canManage" class="text-right">
                  Actions
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-if="members.length === 0">
                <TableCell :colspan="canManage ? 5 : 4" class="py-8 text-center text-muted-foreground">
                  No members assigned yet.
                </TableCell>
              </TableRow>
              <TableRow
                v-for="member in members"
                :key="member.id"
              >
                <TableCell>{{ member.name }}</TableCell>
                <TableCell>{{ member.email }}</TableCell>
                <TableCell>
                  <Badge variant="secondary" class="capitalize">
                    {{ member.role }}
                  </Badge>
                </TableCell>
                <TableCell class="hidden text-muted-foreground sm:table-cell">
                  {{ member.joinedAt !== null ? new Date(member.joinedAt).toLocaleDateString() : '—' }}
                </TableCell>
                <TableCell v-if="canManage" class="text-right">
                  <Select
                    :model-value="member.role"
                    @update:model-value="(value) => changeMemberRole(member.id, value as TeamRole)"
                  >
                    <SelectTrigger class="w-32">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="role in TEAM_MEMBER_ROLE_OPTIONS"
                        :key="role"
                        :value="role"
                      >
                        {{ role }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="ml-2"
                    @click="handleRemoveMember(member.id)"
                  >
                    Remove
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <div
          v-if="canManage"
          class="panel flex flex-wrap items-end gap-4 p-4"
          data-testid="add-team-member-form"
        >
          <div class="min-w-[220px] flex-1 space-y-2">
            <Label for="add-member">Organization member</Label>
            <Select
              :model-value="addMemberUserId"
              @update:model-value="(value) => { addMemberUserId = value === undefined ? undefined : String(value) }"
            >
              <SelectTrigger id="add-member">
                <SelectValue placeholder="Select member" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="member in availableOrgMembers"
                  :key="member.id"
                  :value="member.id"
                >
                  {{ member.name }} ({{ member.email }})
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="w-40 space-y-2">
            <Label>Team role</Label>
            <Select v-model="addMemberRole">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="role in TEAM_MEMBER_ROLE_OPTIONS"
                  :key="role"
                  :value="role"
                >
                  {{ role }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Button
            type="button"
            :disabled="isAddingMember || addMemberUserId === undefined || availableOrgMembers.length === 0"
            @click="handleAddMember"
          >
            <PlusIcon class="mr-2 size-4" aria-hidden="true" />
            {{ isAddingMember ? 'Adding…' : 'Add member' }}
          </Button>
        </div>
      </section>

      <section class="space-y-4">
        <h2 class="section-label">
          Project access
        </h2>

        <p class="max-w-2xl text-sm text-muted-foreground">
          <template v-if="isProjectScopeUnrestricted">
            No projects selected — members on this team can see all organization projects and servers.
          </template>
          <template v-else>
            Members on this team can only access the selected projects and servers assigned within them.
          </template>
        </p>

        <div
          v-if="canManage"
          class="panel space-y-4 p-6"
          data-testid="team-project-scope"
        >
          <div
            v-if="projects.length === 0"
            class="text-sm text-muted-foreground"
          >
            No projects in this organization yet.
            <RouterLink to="/projects" class="text-primary hover:underline">
              Create a project
            </RouterLink>
            to scope team access.
          </div>
          <div
            v-else
            class="space-y-3"
          >
            <label
              v-for="project in projects"
              :key="project.id"
              class="flex items-start gap-3 rounded-md border border-transparent px-2 py-2 transition-colors hover:bg-muted/40"
            >
              <input
                type="checkbox"
                class="mt-1 size-4 rounded border-input"
                :checked="selectedProjectIds.includes(project.id)"
                @change="toggleProject(project.id)"
              >
              <span>
                <span class="block text-sm font-medium">{{ project.name }}</span>
                <span
                  v-if="project.description !== null && project.description !== ''"
                  class="block text-xs text-muted-foreground"
                >
                  {{ project.description }}
                </span>
              </span>
            </label>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button
              type="button"
              :disabled="isSavingProjects"
              @click="saveProjectScope"
            >
              {{ isSavingProjects ? 'Saving…' : 'Save project access' }}
            </Button>
            <Button
              v-if="selectedProjectIds.length > 0"
              type="button"
              variant="outline"
              @click="selectedProjectIds = []"
            >
              Clear selection (all projects)
            </Button>
          </div>
        </div>

        <div
          v-else
          class="panel p-6"
        >
          <Badge variant="secondary">
            {{ projectScopeSummary }}
          </Badge>
          <ul
            v-if="team.projectIds.length > 0"
            class="mt-4 space-y-1 text-sm"
          >
            <li
              v-for="projectId in team.projectIds"
              :key="projectId"
            >
              {{ projects.find(project => project.id === projectId)?.name ?? projectId }}
            </li>
          </ul>
        </div>
      </section>

      <section
        v-if="canManage"
        class="rounded-lg border border-destructive/40 bg-destructive/5 p-6"
        data-testid="team-danger-zone"
      >
        <h2 class="text-sm font-semibold text-destructive">
          Danger zone
        </h2>
        <p class="mt-2 text-sm text-muted-foreground">
          Permanently delete this team. Members keep their organization access; only the team grouping is removed.
        </p>
        <Button
          type="button"
          variant="destructive"
          class="mt-4"
          @click="isDeleteDialogOpen = true"
        >
          Delete team
        </Button>
      </section>

      <ConfirmDestructiveDialog
        v-model:open="isDeleteDialogOpen"
        title="Delete team"
        :description="`This permanently removes ${team.name} and its member assignments.`"
        :confirm-text="team.name"
        confirm-button-label="Delete team"
        :can-confirm="!isDeleting"
        @confirm="handleDeleteTeam"
      />
    </template>
  </div>
</template>
