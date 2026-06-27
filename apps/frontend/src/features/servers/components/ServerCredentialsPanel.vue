<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue'
import { EyeIcon, EyeOffIcon, KeyRoundIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import EmptyState from '@/components/common/EmptyState.vue'
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
import { useCredentialReveal } from '@/composables/useCredentialReveal'
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
const serverId = toRef(props, 'serverId')

const credentials = ref<ServerServiceCredentialRecord[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)

const {
  revealedValues,
  isPending,
  isRevealed,
  hide,
  hideAll,
  setRevealed,
  markPending,
  clearPending,
} = useCredentialReveal()

const isEmpty = computed((): boolean => !isLoading.value && loadError.value === null && credentials.value.length === 0)

function rowEntranceDelay(index: number): string {
  return `${Math.min(index, 8) * 40}ms`
}

async function loadCredentials(): Promise<void> {
  isLoading.value = true
  loadError.value = null
  hideAll()

  try {
    credentials.value = await fetchServerServiceCredentials(serverId.value)
  } catch {
    loadError.value = 'Unable to load service credentials.'
    credentials.value = []
  } finally {
    isLoading.value = false
  }
}

async function toggleReveal(credential: ServerServiceCredentialRecord): Promise<void> {
  if (!props.canReveal) {
    return
  }

  if (isRevealed(credential.id)) {
    hide(credential.id)

    return
  }

  markPending(credential.id)

  try {
    const value = await revealServerServiceCredential(serverId.value, credential.id)
    setRevealed(credential.id, value)
  } catch {
    toast.error('Unable to reveal credential.')
  } finally {
    clearPending(credential.id)
  }
}

watch(serverId, () => {
  void loadCredentials()
})

onMounted(() => {
  void loadCredentials()
})

onBeforeUnmount(() => {
  hideAll()
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
      <Button
        type="button"
        size="sm"
        variant="outline"
        class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
        @click="loadCredentials"
      >
        Retry
      </Button>
    </div>

    <EmptyState
      v-else-if="isEmpty"
      title="No service credentials"
      description="Install PostgreSQL, MySQL, or Redis to generate server secrets during provisioning."
      :icon="KeyRoundIcon"
    />

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
          <template v-if="isLoading">
            <TableRow v-for="index in 3" :key="`skeleton-${index}`">
              <TableCell colspan="3">
                <Skeleton class="h-4 w-full max-w-xs motion-reduce:animate-none" />
              </TableCell>
            </TableRow>
          </template>
          <TableRow
            v-for="(credential, index) in credentials"
            v-else
            :key="credential.id"
            class="credential-row animate-env-row-in motion-reduce:animate-none"
            :style="{ animationDelay: rowEntranceDelay(index) }"
          >
            <TableCell class="font-medium">
              {{ credential.label }}
            </TableCell>
            <TableCell>
              <Badge variant="outline" class="font-mono text-[10px] font-normal capitalize">
                {{ credential.serviceKey }}
              </Badge>
            </TableCell>
            <TableCell class="text-right">
              <div class="flex items-center justify-end gap-2">
                <code
                  v-if="isRevealed(credential.id)"
                  class="max-w-[14rem] truncate rounded bg-muted px-2 py-1 font-mono text-xs"
                  :title="revealedValues[credential.id]"
                >
                  {{ revealedValues[credential.id] }}
                </code>
                <span v-else class="font-mono text-sm tracking-widest text-muted-foreground" aria-hidden="true">
                  ••••••••
                </span>
                <span v-if="!isRevealed(credential.id)" class="sr-only">Hidden</span>
                <Button
                  v-if="canReveal"
                  type="button"
                  size="sm"
                  variant="ghost"
                  class="min-h-9 min-w-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
                  :disabled="isPending(credential.id)"
                  :aria-label="isRevealed(credential.id) ? `Hide ${credential.label}` : `Reveal ${credential.label}`"
                  @click="toggleReveal(credential)"
                >
                  <EyeOffIcon
                    v-if="isRevealed(credential.id)"
                    class="size-4"
                    aria-hidden="true"
                  />
                  <EyeIcon
                    v-else
                    class="size-4 motion-reduce:animate-none"
                    :class="{ 'animate-pulse': isPending(credential.id) }"
                    aria-hidden="true"
                  />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </section>
</template>

<style scoped>
.credential-row {
  content-visibility: auto;
  contain-intrinsic-size: auto 2.75rem;
}
</style>
