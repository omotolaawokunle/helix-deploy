<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { toast } from 'vue-sonner'
import { PlusIcon, Trash2Icon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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
import {
  assignProjectDnsZone,
  fetchDnsProviderConnections,
  fetchDnsProviderZones,
  fetchProjectDnsZones,
  removeProjectDnsZone,
} from '@/features/integrations/api'
import {
  DNS_PROVIDER_LABELS,
  type DnsProvider,
  type DnsProviderZone,
  type ProjectDnsZone,
} from '@/features/integrations/types'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

interface Props {
  projectId: string
  canManage: boolean
}

const props = defineProps<Props>()

const { orgId } = useActiveOrg()

const assignedZones = ref<ProjectDnsZone[]>([])
const availableZonesByProvider = ref<Record<DnsProvider, DnsProviderZone[]>>({
  cloudflare: [],
  digitalocean: [],
})
const connectedProviders = ref<DnsProvider[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const isAssignOpen = ref(false)
const selectedProvider = ref<DnsProvider | undefined>(undefined)
const selectedZoneId = ref<string | undefined>(undefined)
const isAssigning = ref(false)
const removeTarget = ref<ProjectDnsZone | null>(null)
const isRemoveOpen = ref(false)
const isRemoving = ref(false)

const hasAnyProviderConnected = computed(() => connectedProviders.value.length > 0)

const availableZones = computed(
  () => (selectedProvider.value === undefined
    ? []
    : availableZonesByProvider.value[selectedProvider.value]),
)

const selectedZone = computed(
  () => availableZones.value.find((zone) => zone.id === selectedZoneId.value) ?? null,
)

const assignableZones = computed(() => {
  if (selectedProvider.value === undefined) {
    return []
  }

  const assignedKeys = new Set(
    assignedZones.value
      .filter((zone) => zone.dnsProvider === selectedProvider.value)
      .map((zone) => zone.zoneId),
  )

  return availableZones.value.filter((zone) => !assignedKeys.has(zone.id))
})

const canAssignZone = computed(() => {
  if (!hasAnyProviderConnected.value) {
    return false
  }

  return connectedProviders.value.some((provider) => {
    const assignedKeys = new Set(
      assignedZones.value
        .filter((zone) => zone.dnsProvider === provider)
        .map((zone) => zone.zoneId),
    )

    return availableZonesByProvider.value[provider].some(
      (zone) => !assignedKeys.has(zone.id),
    )
  })
})

function providerLabel(provider: DnsProvider): string {
  return DNS_PROVIDER_LABELS[provider]
}

async function load(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    assignedZones.value = await fetchProjectDnsZones(props.projectId)

    if (orgId.value !== null) {
      const connections = await fetchDnsProviderConnections(orgId.value)
      const providers: DnsProvider[] = []

      for (const provider of Object.keys(connections) as DnsProvider[]) {
        if (connections[provider].connected) {
          providers.push(provider)
          availableZonesByProvider.value[provider] = await fetchDnsProviderZones(orgId.value, provider)
        } else {
          availableZonesByProvider.value[provider] = []
        }
      }

      connectedProviders.value = providers
    }
  } catch {
    loadError.value = 'Unable to load DNS zones for this project.'
  } finally {
    isLoading.value = false
  }
}

function openAssignSheet(): void {
  selectedProvider.value = connectedProviders.value[0]
  selectedZoneId.value = assignableZones.value[0]?.id
  isAssignOpen.value = true
}

watch(selectedProvider, () => {
  selectedZoneId.value = assignableZones.value[0]?.id
})

async function handleAssign(): Promise<void> {
  const zone = selectedZone.value
  const provider = selectedProvider.value

  if (zone === null || provider === undefined) {
    return
  }

  isAssigning.value = true

  try {
    const created = await assignProjectDnsZone(props.projectId, {
      dnsProvider: provider,
      zoneId: zone.id,
      baseDomain: zone.name,
    })
    assignedZones.value = [...assignedZones.value, created].sort(
      (left, right) => left.baseDomain.localeCompare(right.baseDomain),
    )
    isAssignOpen.value = false
    toast.success(`${zone.name} assigned to project.`)
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)
    toast.error(
      fieldErrors === null
        ? 'Unable to assign zone.'
        : (firstFieldError(fieldErrors, 'zoneId') ?? 'Unable to assign zone.'),
    )
  } finally {
    isAssigning.value = false
  }
}

async function confirmRemove(): Promise<void> {
  if (removeTarget.value === null) {
    return
  }

  isRemoving.value = true

  try {
    await removeProjectDnsZone(props.projectId, removeTarget.value.id)
    assignedZones.value = assignedZones.value.filter(
      (zone) => zone.id !== removeTarget.value?.id,
    )
    removeTarget.value = null
    isRemoveOpen.value = false
    toast.success('Zone removed from project.')
  } catch {
    toast.error('Unable to remove zone from project.')
  } finally {
    isRemoving.value = false
  }
}

watch(
  () => props.projectId,
  () => {
    void load()
  },
)

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="panel space-y-4 p-6" data-testid="project-dns-zones-section">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="space-y-1">
        <h2 class="section-label">
          DNS zones
        </h2>
        <p class="text-sm text-muted-foreground">
          Approved DNS zones developers can use when creating sites on this project.
        </p>
      </div>
      <Button
        v-if="canManage && canAssignZone"
        type="button"
        size="sm"
        @click="openAssignSheet"
      >
        <PlusIcon class="mr-2 size-4" />
        Assign zone
      </Button>
    </div>

    <p
      v-if="!isLoading && !hasAnyProviderConnected"
      class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
    >
      Connect Cloudflare or DigitalOcean under
      <RouterLink to="/settings/integrations" class="text-primary hover:underline">
        Integrations
      </RouterLink>
      before assigning zones to this project.
    </p>

    <div v-else-if="isLoading" class="space-y-2">
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-10 w-full" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="rounded-lg border border-dashed p-4 text-center"
    >
      <p class="text-sm text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" size="sm" class="mt-3" @click="load">
        Try again
      </Button>
    </div>

    <div
      v-else-if="assignedZones.length === 0"
      class="rounded-lg border border-dashed p-6 text-center"
    >
      <p class="text-sm text-muted-foreground">
        No DNS zones assigned yet.
      </p>
      <p v-if="canManage && hasAnyProviderConnected" class="mt-1 text-xs text-muted-foreground">
        Assign a zone so developers can pick subdomains or apex domains when creating sites.
      </p>
    </div>

    <div v-else class="overflow-hidden rounded-lg border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Domain</TableHead>
            <TableHead>Provider</TableHead>
            <TableHead>Zone ID</TableHead>
            <TableHead v-if="canManage" class="w-16" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="zone in assignedZones" :key="zone.id">
            <TableCell class="font-medium">
              {{ zone.baseDomain }}
            </TableCell>
            <TableCell class="text-sm text-muted-foreground">
              {{ providerLabel(zone.dnsProvider) }}
            </TableCell>
            <TableCell class="font-mono text-xs text-muted-foreground">
              {{ zone.zoneId }}
            </TableCell>
            <TableCell v-if="canManage">
              <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground hover:text-destructive"
                @click="() => { removeTarget = zone; isRemoveOpen = true }"
              >
                <Trash2Icon class="size-4" />
                <span class="sr-only">Remove {{ zone.baseDomain }}</span>
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <Sheet v-model:open="isAssignOpen">
      <SheetContent side="right" class="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Assign DNS zone</SheetTitle>
          <SheetDescription>
            Developers on this project can create sites under the selected zone.
          </SheetDescription>
        </SheetHeader>

        <SheetBody class="space-y-4">
          <div class="space-y-2">
            <Label>DNS provider</Label>
            <Select v-model="selectedProvider">
              <SelectTrigger>
                <SelectValue placeholder="Select provider" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="provider in connectedProviders"
                  :key="provider"
                  :value="provider"
                >
                  {{ providerLabel(provider) }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>Zone</Label>
            <Select v-model="selectedZoneId">
              <SelectTrigger>
                <SelectValue placeholder="Select a zone" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="zone in assignableZones"
                  :key="zone.id"
                  :value="zone.id"
                >
                  {{ zone.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </SheetBody>

        <SheetFooter>
          <Button
            type="button"
            class="w-full"
            :disabled="isAssigning || selectedZone === null"
            @click="handleAssign"
          >
            {{ isAssigning ? 'Assigning…' : 'Assign zone' }}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <ConfirmDestructiveDialog
      v-model:open="isRemoveOpen"
      title="Remove DNS zone"
      :description="removeTarget === null
        ? ''
        : `Developers will no longer be able to auto-create DNS records for ${removeTarget.baseDomain} on this project.`"
      :confirm-text="removeTarget?.baseDomain ?? ''"
      confirm-button-label="Remove zone"
      :can-confirm="!isRemoving"
      @confirm="confirmRemove"
    />
  </section>
</template>
