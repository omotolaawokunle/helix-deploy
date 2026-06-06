<script setup lang="ts">
import { computed, ref } from 'vue'
import { RefreshCwIcon } from '@lucide/vue'
import { toast } from 'vue-sonner'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { dnsRecordDescription } from '@/features/integrations/lib/dnsProviderConfig'
import { DNS_PROVIDER_LABELS, dnsProviderLabel, type DnsProvider } from '@/features/integrations/types'
import { retrySiteDns, retrySiteSsl } from '@/features/integrations/api'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import type { Site } from '@/types'

interface Props {
  site: Site
}

interface Emits {
  (event: 'updated', site: Site): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const authStore = useAuthStore()

const isRetryingDns = ref(false)
const isRetryingSsl = ref(false)

const canRetry = computed(() => authStore.isDeveloper)

const showDnsSection = computed(
  () => props.site.autoCreateDns || (props.site.dnsStatus !== null && props.site.dnsStatus !== 'none'),
)

const showSslSection = computed(
  () => props.site.enableSsl || (props.site.sslStatus !== null && props.site.sslStatus !== 'none'),
)

const dnsCanRetry = computed(
  () => canRetry.value
    && props.site.autoCreateDns
    && props.site.dnsStatus !== 'active'
    && props.site.dnsStatus !== 'pending',
)

const sslCanRetry = computed(
  () => canRetry.value
    && props.site.enableSsl
    && props.site.sslStatus !== 'active'
    && props.site.sslStatus !== 'pending',
)

const dnsDescription = computed(() =>
  dnsRecordDescription(props.site.dnsProvider as DnsProvider | null),
)

const sslChallengeLabel = computed(() => {
  if (props.site.sslChallenge === 'dns-01') {
    const provider = props.site.dnsProvider as DnsProvider | null

    if (provider !== null && provider in DNS_PROVIDER_LABELS) {
      return `DNS-01 (${DNS_PROVIDER_LABELS[provider]})`
    }

    return 'DNS-01 (DNS provider)'
  }

  if (props.site.sslChallenge === 'http-01') {
    return 'HTTP-01 (webroot)'
  }

  return '—'
})

async function handleRetryDns(): Promise<void> {
  isRetryingDns.value = true

  try {
    const updated = await retrySiteDns(props.site.id)
    emit('updated', updated)
    toast.success('DNS provisioning queued.')
  } catch (error: unknown) {
    const message = error instanceof Error && 'response' in error
      ? (error as { response?: { data?: { message?: string } } }).response?.data?.message
      : null
    toast.error(message ?? 'Unable to retry DNS provisioning.')
  } finally {
    isRetryingDns.value = false
  }
}

async function handleRetrySsl(): Promise<void> {
  isRetryingSsl.value = true

  try {
    const updated = await retrySiteSsl(props.site.id)
    emit('updated', updated)
    toast.success('SSL issuance queued.')
  } catch (error: unknown) {
    const message = error instanceof Error && 'response' in error
      ? (error as { response?: { data?: { message?: string } } }).response?.data?.message
      : null
    toast.error(message ?? 'Unable to retry SSL issuance.')
  } finally {
    isRetryingSsl.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="!showDnsSection && !showSslSection"
      class="panel border-dashed p-8 text-center"
    >
      <p class="text-sm text-muted-foreground">
        DNS and SSL automation were not enabled for this site.
      </p>
      <p class="mt-1 text-xs text-muted-foreground">
        Enable auto-create DNS or Let's Encrypt when creating a site, or configure records manually.
      </p>
    </div>

    <section
      v-if="showDnsSection"
      class="panel space-y-4 p-6"
      data-testid="site-dns-section"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
          <h2 class="section-label">
            DNS
          </h2>
          <p class="text-sm text-muted-foreground">
            {{ dnsDescription }}
          </p>
        </div>
        <StatusBadge
          :key="`dns-${site.dnsStatus ?? 'none'}`"
          :status="site.dnsStatus ?? 'none'"
          type="dns"
        />
      </div>

      <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt class="text-muted-foreground">
            Auto-create
          </dt>
          <dd class="font-medium">
            {{ site.autoCreateDns ? 'Enabled' : 'Disabled' }}
          </dd>
        </div>
        <div v-if="site.isApex">
          <dt class="text-muted-foreground">
            Record type
          </dt>
          <dd class="font-medium">
            Apex (@)
          </dd>
        </div>
        <div v-if="site.dnsProvider !== null">
          <dt class="text-muted-foreground">
            Provider
          </dt>
          <dd class="font-medium">
            {{ dnsProviderLabel(site.dnsProvider) }}
          </dd>
        </div>
        <div v-if="site.dnsRecordIds.length > 0">
          <dt class="text-muted-foreground">
            Managed records
          </dt>
          <dd class="font-mono text-xs">
            {{ site.dnsRecordIds.length }} record{{ site.dnsRecordIds.length === 1 ? '' : 's' }}
          </dd>
        </div>
      </dl>

      <Transition name="fade-up">
        <div
          v-if="site.dnsError !== null && site.dnsError !== ''"
          key="dns-error"
          class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {{ site.dnsError }}
        </div>
      </Transition>

      <div v-if="dnsCanRetry" class="flex items-center gap-3">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="isRetryingDns"
          @click="handleRetryDns"
        >
          <RefreshCwIcon class="mr-2 size-4" :class="{ 'animate-spin': isRetryingDns }" />
          {{ isRetryingDns ? 'Retrying…' : 'Retry DNS' }}
        </Button>
      </div>
    </section>

    <section
      v-if="showSslSection"
      class="panel space-y-4 p-6"
      data-testid="site-ssl-section"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
          <h2 class="section-label">
            SSL
          </h2>
          <p class="text-sm text-muted-foreground">
            Let's Encrypt certificate issued via certbot on the server.
          </p>
        </div>
        <StatusBadge
          :key="`ssl-${site.sslStatus ?? 'none'}`"
          :status="site.sslStatus ?? 'none'"
          type="ssl"
        />
      </div>

      <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt class="text-muted-foreground">
            Enabled
          </dt>
          <dd class="font-medium">
            {{ site.enableSsl ? 'Yes' : 'No' }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">
            Challenge
          </dt>
          <dd class="font-medium">
            {{ sslChallengeLabel }}
          </dd>
        </div>
        <div v-if="site.sslProvider !== null">
          <dt class="text-muted-foreground">
            Provider
          </dt>
          <dd class="font-medium">
            {{ site.sslProvider }}
          </dd>
        </div>
      </dl>

      <Transition name="fade-up">
        <div
          v-if="site.sslError !== null && site.sslError !== ''"
          key="ssl-error"
          class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {{ site.sslError }}
        </div>
      </Transition>

      <div v-if="sslCanRetry" class="flex items-center gap-3">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="isRetryingSsl"
          @click="handleRetrySsl"
        >
          <RefreshCwIcon class="mr-2 size-4" :class="{ 'animate-spin': isRetryingSsl }" />
          {{ isRetryingSsl ? 'Retrying…' : 'Retry SSL' }}
        </Button>
      </div>

      <Transition name="fade-up">
        <p
          v-if="site.sslStatus === 'active'"
          key="ssl-active-hint"
          class="text-xs text-muted-foreground"
        >
          HTTP requests redirect to HTTPS when the certificate is active.
        </p>
      </Transition>
    </section>
  </div>
</template>
