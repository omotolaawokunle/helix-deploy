<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import {
  deleteOrganization,
  fetchOrganization,
  fetchOrganizationMembers,
  inviteOrganizationMember,
  removeOrganizationMember,
  updateMemberRole,
  updateOrganization,
} from '@/features/organizations/api'
import type { Organization, OrganizationMemberRecord } from '@/types'
import { TeamRole } from '@/types'

const authStore = useAuthStore()
const router = useRouter()

const organization = ref<Organization | null>(null)
const members = ref<OrganizationMemberRecord[]>([])
const isLoading = ref(true)
const orgName = ref('')
const inviteEmail = ref('')
const inviteRole = ref<TeamRole>(TeamRole.Developer)
const isDeleteOrgOpen = ref(false)
const isDeletingOrg = ref(false)

const canDeleteOrganization = computed(
  () => authStore.organizations.length > 1,
)

const inviteRoleOptions = [
  TeamRole.Admin,
  TeamRole.Developer,
  TeamRole.Viewer,
]

const memberRoleOptions = [
  TeamRole.Owner,
  TeamRole.Admin,
  TeamRole.Developer,
  TeamRole.Viewer,
]

async function load(): Promise<void> {
  if (!authStore.canManageOrgSettings) {
    await router.replace('/dashboard')

    return
  }

  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  isLoading.value = true

  try {
    const [organizationData, membersData] = await Promise.all([
      fetchOrganization(orgId),
      fetchOrganizationMembers(orgId),
    ])

    organization.value = organizationData
    orgName.value = organizationData.name
    members.value = membersData
  } catch {
    toast.error('Unable to load organization settings.')
  } finally {
    isLoading.value = false
  }
}

async function saveOrganization(): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  try {
    organization.value = await updateOrganization(orgId, { name: orgName.value })
    toast.success('Organization updated.')
  } catch {
    toast.error('Unable to update organization.')
  }
}

async function sendInvite(): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined || inviteEmail.value.trim() === '') {
    return
  }

  try {
    const url = await inviteOrganizationMember(orgId, {
      email: inviteEmail.value.trim(),
      role: inviteRole.value,
    })
    inviteEmail.value = ''
    await load()
    toast.success(`Invitation created: ${url}`)
  } catch {
    toast.error('Unable to send invitation.')
  }
}

async function changeRole(memberId: string, role: TeamRole): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  try {
    await updateMemberRole(orgId, memberId, role)
    await load()
    toast.success('Member role updated.')
  } catch {
    toast.error('Unable to update member role.')
  }
}

async function removeMember(memberId: string): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  try {
    await removeOrganizationMember(orgId, memberId)
    await load()
    toast.success('Member removed.')
  } catch {
    toast.error('Unable to remove member.')
  }
}

async function handleDeleteOrg(): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  isDeletingOrg.value = true

  try {
    await deleteOrganization(orgId)
    isDeleteOrgOpen.value = false
    toast.success('Organization deleted.')
    await authStore.loadOrganizations()
    await router.push('/dashboard')
    window.location.reload()
  } catch {
    toast.error('Unable to delete organization.')
  } finally {
    isDeletingOrg.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Organization settings"
      description="Manage your organization name, members, and access."
    />

    <div v-if="isLoading" class="space-y-4">
      <Skeleton class="h-48 w-full max-w-lg rounded-lg" />
      <Skeleton class="h-64 w-full rounded-lg" />
    </div>

    <template v-else>
    <form class="panel max-w-lg space-y-4 p-6" @submit.prevent="saveOrganization">
      <div class="space-y-2">
        <Label for="org-name">Organization name</Label>
        <Input id="org-name" v-model="orgName" />
      </div>
      <Button type="submit">
        Save
      </Button>
    </form>

    <section class="space-y-4">
      <h2 class="section-label">
        Members
      </h2>
      <div class="panel overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Role</TableHead>
              <TableHead>Joined</TableHead>
              <TableHead class="text-right">
                Actions
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="member in members" :key="member.id">
              <TableCell>{{ member.name }}</TableCell>
              <TableCell>{{ member.email }}</TableCell>
              <TableCell>
                <Badge variant="secondary" class="capitalize">
                  {{ member.role }}
                </Badge>
              </TableCell>
              <TableCell>
                {{ member.joinedAt !== null ? new Date(member.joinedAt).toLocaleDateString() : '—' }}
              </TableCell>
              <TableCell class="text-right">
                <Select
                  :model-value="member.role"
                  @update:model-value="(value) => changeRole(member.id, value as TeamRole)"
                >
                  <SelectTrigger class="w-32">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="role in memberRoleOptions"
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
                  @click="removeMember(member.id)"
                >
                  Remove
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <div class="panel flex flex-wrap items-end gap-4 p-4">
        <div class="min-w-[200px] flex-1 space-y-2">
          <Label for="invite-email">Invite email</Label>
          <Input id="invite-email" v-model="inviteEmail" type="email" />
        </div>
        <div class="w-40 space-y-2">
          <Label>Role</Label>
          <Select v-model="inviteRole">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="role in inviteRoleOptions"
                :key="role"
                :value="role"
              >
                {{ role }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Button type="button" @click="sendInvite">
          Send Invite
        </Button>
      </div>
    </section>

    <section
      v-if="organization !== null"
      class="rounded-lg border border-destructive/40 bg-destructive/5 p-6"
    >
      <h2 class="text-sm font-semibold text-destructive">
        Danger Zone
      </h2>
      <p class="mt-2 text-sm text-muted-foreground">
        Delete this organization and all associated data permanently.
      </p>
      <p v-if="!canDeleteOrganization" class="mt-2 text-sm text-muted-foreground">
        You must belong to at least one other organization before deleting this one.
      </p>
      <Button
        type="button"
        variant="destructive"
        class="mt-4"
        :disabled="!canDeleteOrganization || isDeletingOrg"
        @click="isDeleteOrgOpen = true"
      >
        Delete organization
      </Button>
    </section>

    <ConfirmDestructiveDialog
      v-if="organization !== null"
      v-model:open="isDeleteOrgOpen"
      title="Delete organization"
      description="This permanently deletes the organization and cannot be undone."
      :confirm-text="organization.slug"
      confirm-button-label="Delete organization"
      @confirm="handleDeleteOrg"
    />
    </template>
  </div>
</template>
