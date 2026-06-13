<script setup lang="ts">
import { computed, onMounted, ref, toRef } from 'vue'
import { DownloadIcon, RefreshCwIcon, ShieldCheckIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadErrorPanel from '@/components/common/LoadErrorPanel.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
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
  adoptServerSslCertificates,
  renewServerSslCertificates,
  syncServerSslCertificates,
} from '@/features/servers/api'
import { useServerSslOverview } from '@/features/servers/composables/useServerSslOverview'
import {
  sslRowEntranceDelay,
  useSslRefreshFeedback,
} from '@/features/servers/composables/useSslRefreshFeedback'
import {
  daysLabel,
  expiryClass,
  expiryLabel,
  formatExpiryDate,
} from '@/features/sites/composables/useSslExpiryDisplay'
import { formatRelativeTime } from '@/lib/format'
import type { SslStatus } from '@/types'

interface Props {
  serverId: string
  isProduction: boolean
  canManage: boolean
}

const props = defineProps<Props>()

const serverId = toRef(props, 'serverId')

const {
  overview,
  isLoading,
  isRefreshing,
  loadError,
  lastCheckedAt,
  hasSites,
  hasUnadoptedSites,
  isBackgroundUpdatePending,
  loadOverview,
  refreshOverview,
  beginBackgroundRefresh,
} = useServerSslOverview(serverId)

const isSyncing = ref(false)
const isAdopting = ref(false)
const isRenewing = ref(false)
const isRenewDialogOpen = ref(false)

const isBackgroundRefreshing = computed(
  (): boolean => overview.value?.syncQueued === true || isBackgroundUpdatePending.value,
)

const { refreshHint, showRefreshComplete } = useSslRefreshFeedback(isBackgroundRefreshing)

function isCertExpiringSoon(daysUntilExpiry: number | null): boolean {
  return daysUntilExpiry !== null && daysUntilExpiry >= 0 && daysUntilExpiry <= 30
}

const nearestExpiryLabel = computed((): string | null => {
  const expiry = overview.value?.nearestExpiryAt

  if (expiry === null || expiry === undefined) {
    return null
  }

  return formatExpiryDate(expiry)
})

async function handleSync(): Promise<void> {
  isSyncing.value = true

  try {
    await syncServerSslCertificates(serverId.value)
    toast.success('SSL certificate sync queued.')
    beginBackgroundRefresh()
    await refreshOverview()
  } catch {
    toast.error('Unable to queue SSL sync.')
  } finally {
    isSyncing.value = false
  }
}

async function handleAdopt(): Promise<void> {
  isAdopting.value = true

  try {
    await adoptServerSslCertificates(serverId.value)
    toast.success('Adopting existing SSL certificates queued.')
    beginBackgroundRefresh()
    await refreshOverview()
  } catch {
    toast.error('Unable to queue SSL adoption.')
  } finally {
    isAdopting.value = false
  }
}

async function handleRenewConfirm(): Promise<void> {
  isRenewing.value = true

  try {
    await renewServerSslCertificates(serverId.value)
    isRenewDialogOpen.value = false
    toast.success('SSL certificate renewal queued for all active sites.')
    beginBackgroundRefresh()
  } catch {
    toast.error('Unable to queue SSL renewal.')
  } finally {
    isRenewing.value = false
  }
}

onMounted(() => {
  void loadOverview()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="space-y-1">
        <h2 class="section-label">
          SSL certificates
        </h2>
        <p class="text-sm text-muted-foreground">
          Let's Encrypt certificates on this server. Adopt detects existing certbot certs for imported sites.
        </p>
      </div>

      <div v-if="canManage" class="flex flex-wrap gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
          :disabled="isAdopting || isSyncing || isRenewing || isRefreshing"
          :aria-busy="isAdopting"
          data-testid="server-ssl-adopt-button"
          @click="handleAdopt"
        >
          <DownloadIcon
            class="mr-2 size-4 motion-reduce:animate-none"
            :class="{ 'animate-spin': isAdopting }"
            aria-hidden="true"
          />
          {{ isAdopting ? 'Adopting…' : 'Adopt existing SSL' }}
        </Button>
        <template v-if="hasSites">
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
            :disabled="isSyncing || isRenewing || isAdopting || isRefreshing"
            :aria-busy="isSyncing || isRefreshing"
            @click="handleSync"
          >
            <RefreshCwIcon
              class="mr-2 size-4 motion-reduce:animate-none"
              :class="{ 'animate-spin': isSyncing || (isRefreshing && !isSyncing) }"
              aria-hidden="true"
            />
            {{ isSyncing ? 'Syncing…' : 'Sync now' }}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="min-h-9 transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
            :disabled="isSyncing || isRenewing || isAdopting || (overview?.activeCertificateCount ?? 0) === 0"
            @click="isRenewDialogOpen = true"
          >
            <ShieldCheckIcon class="mr-2 size-4" aria-hidden="true" />
            Renew all
          </Button>
        </template>
      </div>
    </div>

    <div v-if="isLoading && overview === null" class="space-y-3">
      <Skeleton class="h-24 w-full motion-reduce:animate-none" />
      <Skeleton class="h-48 w-full motion-reduce:animate-none" />
    </div>

    <LoadErrorPanel
      v-else-if="loadError !== null && overview === null"
      :message="loadError"
      @retry="loadOverview"
    />

    <template v-else-if="overview !== null">
      <Transition name="fade-up">
        <div
          v-if="isBackgroundRefreshing"
          class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          <span
            class="mt-1.5 inline-flex size-1.5 shrink-0 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
            aria-hidden="true"
          />
          <Transition name="status-crossfade" mode="out-in">
            <p :key="refreshHint" class="log-loading-message motion-reduce:animate-none">
              {{ refreshHint }}
            </p>
          </Transition>
        </div>
      </Transition>

      <div
        class="panel grid gap-4 p-4 transition-opacity duration-200 motion-reduce:transition-none sm:grid-cols-2 lg:grid-cols-4 animate-panel-in motion-reduce:animate-none"
        :class="{ 'opacity-60': isRefreshing && !showRefreshComplete }"
      >
        <div class="animate-page-in motion-reduce:animate-none">
          <p class="text-xs text-muted-foreground">
            Certbot
          </p>
          <p class="mt-1 text-sm font-medium">
            {{ overview.hasCertbot ? 'Installed' : 'Not detected' }}
          </p>
        </div>
        <div class="animate-page-in animate-page-in-delay-1 motion-reduce:animate-none">
          <p class="text-xs text-muted-foreground">
            Active certificates
          </p>
          <p class="mt-1 text-sm font-medium">
            {{ overview.activeCertificateCount }}
          </p>
        </div>
        <div class="animate-page-in animate-page-in-delay-1 motion-reduce:animate-none">
          <p class="text-xs text-muted-foreground">
            Expiring within 30 days
          </p>
          <p
            class="mt-1 text-sm font-medium"
            :class="overview.expiringSoonCount > 0 ? 'text-amber-600 dark:text-amber-400' : ''"
          >
            <span
              v-if="overview.expiringSoonCount > 0"
              class="mr-1.5 inline-flex size-1.5 animate-pulse rounded-full bg-amber-500 motion-reduce:animate-none"
              aria-hidden="true"
            />
            {{ overview.expiringSoonCount }}
          </p>
        </div>
        <div class="animate-page-in animate-page-in-delay-1 motion-reduce:animate-none">
          <p class="text-xs text-muted-foreground">
            Nearest expiry
          </p>
          <p class="mt-1 text-sm font-medium">
            {{ nearestExpiryLabel ?? '—' }}
          </p>
        </div>
      </div>

      <EmptyState
        v-if="!hasSites"
        title="No sites on this server"
        description="Sites are discovered when the server connects or when you add them manually. Use Adopt existing SSL after discovery if certbot certificates are already installed."
        data-testid="server-ssl-empty"
      />

      <Transition name="fade-up">
        <div
          v-if="hasUnadoptedSites"
          class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-800 dark:text-amber-300"
          role="status"
        >
          Some sites may have SSL on the server that Helix has not recorded yet. Click
          <strong>Adopt existing SSL</strong>
          to detect Let's Encrypt certificates under
          <code class="text-xs">/etc/letsencrypt/live/{domain}/</code>.
        </div>
      </Transition>

      <div
        v-if="hasSites"
        class="panel overflow-hidden transition-opacity duration-200 motion-reduce:transition-none animate-panel-in animate-panel-in-delay-1 motion-reduce:animate-none"
        :class="{
          'opacity-60': isRefreshing && !showRefreshComplete,
          'ssl-refresh-ready motion-reduce:animate-none': showRefreshComplete,
        }"
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Domain</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Expires</TableHead>
              <TableHead class="text-right">
                Days left
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="(cert, index) in overview.certificates"
              :key="cert.siteId"
              data-testid="server-ssl-row"
              class="animate-service-row-in border-l-2 transition-[background-color,border-color] duration-300 motion-reduce:animate-none motion-reduce:transition-none"
              :class="isCertExpiringSoon(cert.daysUntilExpiry)
                ? 'border-l-amber-500/70 bg-amber-500/5'
                : 'border-l-transparent'"
              :style="{ animationDelay: sslRowEntranceDelay(index) }"
            >
              <TableCell class="max-w-[12rem] truncate font-medium sm:max-w-none">
                {{ cert.domain }}
              </TableCell>
              <TableCell>
                <Transition name="status-crossfade" mode="out-in">
                  <StatusBadge
                    :key="`${cert.siteId}-${cert.sslStatus}`"
                    :status="cert.sslStatus as SslStatus"
                    type="ssl"
                  />
                </Transition>
              </TableCell>
              <TableCell
                class="text-sm"
                :class="expiryClass(cert.daysUntilExpiry)"
              >
                <Transition name="status-crossfade" mode="out-in">
                  <span :key="`${cert.siteId}-${cert.sslExpiresAt ?? 'none'}`">
                    {{ expiryLabel(cert.daysUntilExpiry, cert.sslExpiresAt) }}
                  </span>
                </Transition>
              </TableCell>
              <TableCell
                class="text-right text-sm"
                :class="expiryClass(cert.daysUntilExpiry)"
              >
                {{ daysLabel(cert.daysUntilExpiry) }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <Transition name="fade-up">
        <p
          v-if="lastCheckedAt !== null"
          class="text-xs text-muted-foreground"
        >
          Last checked {{ formatRelativeTime(lastCheckedAt) }}
        </p>
      </Transition>
    </template>

    <ConfirmDestructiveDialog
      v-model:open="isRenewDialogOpen"
      title="Renew all SSL certificates"
      :description="isProduction
        ? `This will renew every active Let's Encrypt certificate on this production server. Sites may briefly reload TLS during renewal.`
        : `This will renew every active Let's Encrypt certificate on this server via certbot.`"
      confirm-text="renew"
      confirm-button-label="Renew all certificates"
      :can-confirm="!isRenewing"
      @confirm="handleRenewConfirm"
    />
  </div>
</template>
