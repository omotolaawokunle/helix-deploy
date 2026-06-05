<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { exportAuditLogs, fetchOrganizationAuditLogs } from '@/features/audit/api'
import { fetchOrganizationMembers } from '@/features/organizations/api'
import { auditOperationBadgeClass } from '@/lib/auditOperationBadge'
import { formatJsonState } from '@/lib/jsonDiff'
import { formatRelativeTime } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { AuditLogEntry, OrganizationMemberRecord } from '@/types'
const authStore = useAuthStore()

const logs = ref<AuditLogEntry[]>([])
const members = ref<OrganizationMemberRecord[]>([])
const isLoading = ref(true)
const expandedId = ref<string | null>(null)

const operationFilter = ref('')
const actorFilter = ref('')
const resourceTypeFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const canExport = computed(() => authStore.isOwner)
const canViewSensitive = computed(() => authStore.isOwner)

async function loadAuditLogs(): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  isLoading.value = true

  try {
    const response = await fetchOrganizationAuditLogs(orgId, {
      operation: operationFilter.value || undefined,
      actor_id: actorFilter.value === '__all__' || actorFilter.value === ''
        ? undefined
        : actorFilter.value,
      resource_type: resourceTypeFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      per_page: 50,
    })

    logs.value = response.data
  } catch {
    toast.error('Unable to load audit logs.')
  } finally {
    isLoading.value = false
  }
}

async function loadMembers(): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  members.value = await fetchOrganizationMembers(orgId)
}

async function handleExport(): Promise<void> {
  const orgId = authStore.currentOrg?.id

  if (orgId === undefined) {
    return
  }

  try {
    const result = await exportAuditLogs(orgId, {
      operation: operationFilter.value || undefined,
      actor_id: actorFilter.value === '__all__' || actorFilter.value === ''
        ? undefined
        : actorFilter.value,
      resource_type: resourceTypeFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })

    if (result.status === 'queued') {
      toast.success('Export queued. You will be notified when ready.')
    } else {
      toast.success('Export started.')
    }
  } catch {
    toast.error('Unable to export audit logs.')
  }
}

function toggleExpanded(entry: AuditLogEntry): void {
  expandedId.value = expandedId.value === entry.id ? null : entry.id
}

function handleRowKeydown(event: KeyboardEvent, entry: AuditLogEntry): void {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    toggleExpanded(entry)
  }
}

onMounted(() => {
  void Promise.all([loadMembers(), loadAuditLogs()])
})
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Audit Log"
      description="Immutable record of sensitive operations across your organization."
    >
      <template v-if="canExport" #actions>
        <Button type="button" variant="outline" @click="handleExport">
          Export CSV
        </Button>
      </template>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
      <aside class="panel space-y-4 p-4">
        <h2 class="text-sm font-medium">
          Filters
        </h2>
        <div class="space-y-2">
          <Label for="filter-operation">Operation</Label>
          <Input id="filter-operation" v-model="operationFilter" placeholder="e.g. deploy" />
        </div>
        <div class="space-y-2">
          <Label>Actor</Label>
          <Select v-model="actorFilter">
            <SelectTrigger>
              <SelectValue placeholder="All users" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">
                All users
              </SelectItem>
              <SelectItem
                v-for="member in members"
                :key="member.id"
                :value="member.id"
              >
                {{ member.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-2">
          <Label for="filter-resource">Resource type</Label>
          <Input id="filter-resource" v-model="resourceTypeFilter" />
        </div>
        <div class="space-y-2">
          <Label for="filter-from">From</Label>
          <Input id="filter-from" v-model="dateFrom" type="date" />
        </div>
        <div class="space-y-2">
          <Label for="filter-to">To</Label>
          <Input id="filter-to" v-model="dateTo" type="date" />
        </div>
        <Button type="button" class="w-full" @click="loadAuditLogs">
          Apply filters
        </Button>
      </aside>

      <div class="panel overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Operation</TableHead>
              <TableHead>Actor</TableHead>
              <TableHead>Resource</TableHead>
              <TableHead>IP</TableHead>
              <TableHead>Timestamp</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="isLoading">
              <TableCell colspan="5" class="text-muted-foreground">
                Loading audit logs…
              </TableCell>
            </TableRow>
            <TableRow v-else-if="logs.length === 0">
              <TableCell colspan="5" class="text-muted-foreground">
                No audit entries match your filters.
              </TableCell>
            </TableRow>
            <template v-else>
              <template v-for="entry in logs" :key="entry.id">
              <TableRow
                tabindex="0"
                role="button"
                :aria-expanded="expandedId === entry.id"
                class="cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                data-testid="audit-log-row"
                @click="toggleExpanded(entry)"
                @keydown="handleRowKeydown($event, entry)"
              >
                <TableCell>
                  <Badge
                    :class="cn('capitalize', auditOperationBadgeClass(entry.operation))"
                    variant="outline"
                  >
                    {{ entry.operation }}
                  </Badge>
                </TableCell>
                <TableCell>{{ entry.actor?.name ?? '—' }}</TableCell>
                <TableCell class="font-mono text-xs">
                  {{ entry.resourceType.split('\\').pop() }} / {{ entry.resourceId }}
                </TableCell>
                <TableCell>{{ entry.ipAddress ?? '—' }}</TableCell>
                <TableCell>{{ formatRelativeTime(entry.createdAt) }}</TableCell>
              </TableRow>
              <TableRow v-if="expandedId === entry.id">
                <TableCell colspan="5" data-testid="audit-log-expanded">
                  <div
                    v-if="canViewSensitive && entry.beforeState !== undefined"
                    class="grid gap-4 md:grid-cols-2"
                  >
                    <div>
                      <p class="mb-2 text-xs font-medium text-muted-foreground">
                        before_state
                      </p>
                      <pre class="log-panel overflow-auto p-3">{{ formatJsonState(entry.beforeState) }}</pre>
                    </div>
                    <div>
                      <p class="mb-2 text-xs font-medium text-muted-foreground">
                        after_state
                      </p>
                      <pre class="log-panel overflow-auto p-3">{{ formatJsonState(entry.afterState) }}</pre>
                    </div>
                  </div>
                  <p v-else class="text-sm text-muted-foreground">
                    State details are only visible to organization owners.
                  </p>
                </TableCell>
              </TableRow>
            </template>
            </template>
          </TableBody>
        </Table>
      </div>
    </div>
  </div>
</template>
