<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { KeyRoundIcon, PlusIcon } from '@lucide/vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
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
import { createApiToken, fetchApiTokens, revokeApiToken } from '@/features/auth/api'
import ApiTokenCreatedSheet from '@/features/auth/components/ApiTokenCreatedSheet.vue'
import { API_TOKEN_ABILITY_OPTIONS, apiTokenAbilityLabel } from '@/features/auth/constants'
import type { ApiTokenAbility, ApiTokenRecord } from '@/features/auth/types'
import { formatRelativeTime } from '@/lib/format'
import { extractFieldErrors, firstFieldError } from '@/lib/validation-errors'

const tokens = ref<ApiTokenRecord[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isCreating = ref(false)
const tokenName = ref('')
const tokenAbility = ref<ApiTokenAbility>('read')
const isCreatedSheetOpen = ref(false)
const createdTokenName = ref('')
const createdPlainTextToken = ref('')
const isRevokeDialogOpen = ref(false)
const revokingToken = ref<ApiTokenRecord | null>(null)
const isRevoking = ref(false)

const isEmpty = computed(
  () => !isLoading.value && loadError.value === null && tokens.value.length === 0,
)

const selectedAbilityDescription = computed(
  () => API_TOKEN_ABILITY_OPTIONS.find(option => option.value === tokenAbility.value)?.description ?? '',
)

async function loadTokens(): Promise<void> {
  isLoading.value = true
  loadError.value = null

  try {
    tokens.value = await fetchApiTokens()
  } catch {
    tokens.value = []
    loadError.value = 'Unable to load API tokens.'
  } finally {
    isLoading.value = false
  }
}

async function handleCreateToken(): Promise<void> {
  if (tokenName.value.trim() === '') {
    return
  }

  isCreating.value = true

  try {
    const response = await createApiToken({
      name: tokenName.value.trim(),
      ability: tokenAbility.value,
    })

    tokens.value = [response.token, ...tokens.value]
    createdTokenName.value = response.token.name
    createdPlainTextToken.value = response.plainTextToken
    isCreatedSheetOpen.value = true
    tokenName.value = ''
    tokenAbility.value = 'read'
    toast.success('API token created.')
  } catch (error) {
    const fieldErrors = extractFieldErrors(error)
    const message = fieldErrors === null ? null : firstFieldError(fieldErrors, 'name')

    toast.error(message ?? 'Unable to create API token.')
  } finally {
    isCreating.value = false
  }
}

function openRevokeDialog(token: ApiTokenRecord): void {
  revokingToken.value = token
  isRevokeDialogOpen.value = true
}

async function handleRevokeToken(): Promise<void> {
  if (revokingToken.value === null) {
    return
  }

  const tokenId = revokingToken.value.id
  isRevoking.value = true

  try {
    await revokeApiToken(tokenId)
    tokens.value = tokens.value.filter(token => token.id !== tokenId)
    revokingToken.value = null
    isRevokeDialogOpen.value = false
    toast.success('API token revoked.')
  } catch {
    toast.error('Unable to revoke API token.')
  } finally {
    isRevoking.value = false
  }
}

function clearCreatedToken(): void {
  createdPlainTextToken.value = ''
  createdTokenName.value = ''
}

onMounted(() => {
  void loadTokens()
})
</script>

<template>
  <section class="space-y-4" data-testid="api-tokens-section">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="section-label">
          API tokens
        </h2>
        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
          Personal access tokens for scripts, CI, and integrations. Use read-only tokens when possible.
        </p>
      </div>
    </div>

    <form
      class="panel flex flex-wrap items-end gap-4 p-4"
      data-testid="create-api-token-form"
      @submit.prevent="handleCreateToken"
    >
      <div class="min-w-[200px] flex-1 space-y-2">
        <Label for="token-name">Token name</Label>
        <Input
          id="token-name"
          v-model="tokenName"
          placeholder="GitHub Actions deploy"
          autocomplete="off"
        />
      </div>
      <div class="w-full min-w-[180px] sm:w-48 space-y-2">
        <Label for="token-ability">Access</Label>
        <Select v-model="tokenAbility">
          <SelectTrigger id="token-ability">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in API_TOKEN_ABILITY_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <Button
        type="submit"
        :disabled="isCreating || tokenName.trim() === ''"
        data-testid="create-api-token-submit"
      >
        <PlusIcon class="mr-2 size-4" aria-hidden="true" />
        {{ isCreating ? 'Creating…' : 'Create token' }}
      </Button>
      <p class="w-full text-sm text-muted-foreground">
        {{ selectedAbilityDescription }}
      </p>
    </form>

    <div
      v-if="isLoading"
      class="space-y-2"
      data-testid="api-tokens-loading"
    >
      <Skeleton class="h-12 w-full rounded-lg" />
      <Skeleton class="h-12 w-full rounded-lg" />
    </div>

    <div
      v-else-if="loadError !== null"
      class="panel border-dashed p-8 text-center"
      data-testid="api-tokens-error"
    >
      <p class="text-muted-foreground">
        {{ loadError }}
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="loadTokens">
        Try again
      </Button>
    </div>

    <EmptyState
      v-else-if="isEmpty"
      :icon="KeyRoundIcon"
      title="No API tokens"
      description="Use the form above to create a token for scripts, CI, and integrations."
      data-testid="api-tokens-empty"
    />

    <div
      v-else
      class="panel overflow-hidden"
      data-testid="api-tokens-table"
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Access</TableHead>
            <TableHead class="hidden sm:table-cell">
              Last used
            </TableHead>
            <TableHead class="hidden md:table-cell">
              Created
            </TableHead>
            <TableHead class="text-right">
              Actions
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="token in tokens"
            :key="token.id"
          >
            <TableCell class="font-medium">
              {{ token.name }}
            </TableCell>
            <TableCell>
              <Badge variant="secondary">
                {{ apiTokenAbilityLabel(token.ability) }}
              </Badge>
            </TableCell>
            <TableCell class="hidden text-muted-foreground sm:table-cell">
              {{ token.lastUsedAt !== null ? formatRelativeTime(token.lastUsedAt) : 'Never' }}
            </TableCell>
            <TableCell class="hidden text-muted-foreground md:table-cell">
              {{ formatRelativeTime(token.createdAt) }}
            </TableCell>
            <TableCell class="text-right">
              <Button
                type="button"
                size="sm"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                data-testid="revoke-api-token"
                @click="openRevokeDialog(token)"
              >
                Revoke
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <ApiTokenCreatedSheet
      v-if="createdPlainTextToken !== ''"
      v-model:open="isCreatedSheetOpen"
      :token-name="createdTokenName"
      :plain-text-token="createdPlainTextToken"
      @closed="clearCreatedToken"
    />

    <ConfirmDestructiveDialog
      v-if="revokingToken !== null"
      v-model:open="isRevokeDialogOpen"
      title="Revoke API token"
      :description="`Revoking ${revokingToken.name} immediately invalidates it. Any automation using this token will stop working.`"
      :confirm-text="revokingToken.name"
      confirm-button-label="Revoke token"
      :can-confirm="!isRevoking"
      @confirm="handleRevokeToken"
    />
  </section>
</template>
