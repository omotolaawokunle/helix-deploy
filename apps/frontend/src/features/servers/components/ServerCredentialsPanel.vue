<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { EyeIcon, EyeOffIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
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
import {
  fetchServerServiceCredentials,
  revealServerServiceCredential,
} from '@/features/servers/api'
import type { ServerServiceCredentialRecord } from '@/features/servers/types'

interface Props {
  serverId: string
  canReveal: boolean
}

const props = defineProps<Props>()

const credentials = ref<ServerServiceCredentialRecord[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const revealedValues = ref<Record<string, string>>({})
const pendingRevealIds = ref<string[]>([])

const isEmpty = computed((): boolean => !isLoading.value && loadError.value === null && credentials.value.length === 0)

function isPending(credentialId: string): boolean {
  return pendingRevealIds.value.includes(credentialId)
}

function isRevealed(credentialId: string): boolean {
  return revealedValues.value[credentialId] !== undefined
}

async function loadCredentials(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    credentials.value = await fetchServerServiceCredentials(props.serverId)
  } catch {
    loadError.value = 'Unable to load service credentials.'
  } finally {
    isLoading.value = false
  }
}

async function toggleReveal(credential: ServerServiceCredentialRecord): Promise<void> {
  if (!props.canReveal) {
    return
  }

  if (isRevealed(credential.id)) {
    const next = { ...revealedValues.value }
    delete next[credential.id]
    revealedValues.value = next

    return
  }

  pendingRevealIds.value = [...pendingRevealIds.value, credential.id]

  try {
    const value = await revealServerServiceCredential(props.serverId, credential.id)
    revealedValues.value = {
      ...revealedValues.value,
      [credential.id]: value,
    }
  } catch {
    toast.error('Unable to reveal credential.')
  } finally {
    pendingRevealIds.value = pendingRevealIds.value.filter(id => id !== credential.id)
  }
}

onMounted(() => {
  void loadCredentials()
})
</script>

<template>
  <section aria-labelledby="server-credentials-heading">
    <div class="mb-3 space-y-1">
      <h2 id="server-credentials-heading" class="section-label !mb-0">
        Service credentials
      </h2>
      <p class="text-xs text-muted-foreground">
        Passwords generated during provisioning. Reveal actions are audit logged.
      </p>
    </div>

    <div
      v-if="loadError !== null"
      class="panel flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
      role="alert"
    >
      <p class="text-sm text-destructive">
        {{ loadError }}
      </p>
      <Button type="button" size="sm" variant="outline" @click="loadCredentials">
        Retry
      </Button>
    </div>

    <div
      v-else-if="isEmpty"
      class="panel px-4 py-6 text-sm text-muted-foreground"
    >
      No provisioned service credentials yet. Install PostgreSQL, MySQL, or Redis to generate secrets.
    </div>

    <div v-else class="panel overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Credential</TableHead>
            <TableHead>Service</TableHead>
            <TableHead class="text-right">
              Value
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="isLoading">
            <TableCell colspan="3">
              <Skeleton class="h-4 w-48" />
            </TableCell>
          </TableRow>
          <TableRow v-for="credential in credentials" v-else :key="credential.id">
            <TableCell class="font-medium">
              {{ credential.label }}
            </TableCell>
            <TableCell class="text-sm text-muted-foreground capitalize">
              {{ credential.serviceKey }}
            </TableCell>
            <TableCell class="text-right">
              <div class="flex items-center justify-end gap-2">
                <code
                  v-if="isRevealed(credential.id)"
                  class="max-w-[12rem] truncate rounded bg-muted px-2 py-1 text-xs"
                >
                  {{ revealedValues[credential.id] }}
                </code>
                <span v-else class="text-sm text-muted-foreground">••••••••</span>
                <Button
                  v-if="canReveal"
                  type="button"
                  size="sm"
                  variant="ghost"
                  class="min-h-9"
                  :disabled="isPending(credential.id)"
                  :aria-label="isRevealed(credential.id) ? `Hide ${credential.label}` : `Reveal ${credential.label}`"
                  @click="toggleReveal(credential)"
                >
                  <EyeOffIcon v-if="isRevealed(credential.id)" class="size-4" aria-hidden="true" />
                  <EyeIcon v-else class="size-4" aria-hidden="true" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </section>
</template>
