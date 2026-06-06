import { computed, ref, shallowRef, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { fetchDnsProviderConnections } from '@/features/integrations/api'
import type { DnsProvider, DnsProviderConnection } from '@/features/integrations/types'

const DISCONNECTED: DnsProviderConnection = {
  connected: false,
  status: 'disconnected',
  connectedAt: null,
  connectedBy: null,
}

const cacheByOrg = new Map<string, Record<DnsProvider, DnsProviderConnection>>()
const inFlightByOrg = new Map<string, Promise<Record<DnsProvider, DnsProviderConnection>>>()

export function invalidateDnsProviderConnections(organizationId: string): void {
  cacheByOrg.delete(organizationId)
  inFlightByOrg.delete(organizationId)
}

export function useDnsProviderConnections(organizationId: MaybeRefOrGetter<string | null>) {
  const connections = shallowRef<Record<DnsProvider, DnsProviderConnection> | null>(null)
  const isLoading = ref(false)
  const loadError = ref<string | null>(null)

  const orgId = computed(() => toValue(organizationId))

  async function ensureLoaded(
    force = false,
  ): Promise<Record<DnsProvider, DnsProviderConnection> | null> {
    const id = orgId.value

    if (id === null) {
      connections.value = null
      return null
    }

    if (!force) {
      const cached = cacheByOrg.get(id)

      if (cached !== undefined) {
        connections.value = cached
        return cached
      }

      const inFlight = inFlightByOrg.get(id)

      if (inFlight !== undefined) {
        const result = await inFlight
        connections.value = result
        return result
      }
    } else {
      invalidateDnsProviderConnections(id)
    }

    isLoading.value = true
    loadError.value = null

    const promise = fetchDnsProviderConnections(id)
      .then((result) => {
        cacheByOrg.set(id, result)
        inFlightByOrg.delete(id)
        connections.value = result
        return result
      })
      .catch((error: unknown) => {
        inFlightByOrg.delete(id)
        loadError.value = error instanceof Error ? error.message : 'Unable to load DNS providers.'
        throw error
      })
      .finally(() => {
        isLoading.value = false
      })

    inFlightByOrg.set(id, promise)

    return promise
  }

  function updateProvider(provider: DnsProvider, connection: DnsProviderConnection): void {
    const id = orgId.value

    if (id === null) {
      return
    }

    const existing = cacheByOrg.get(id) ?? {
      cloudflare: DISCONNECTED,
      digitalocean: DISCONNECTED,
    }

    const next = {
      ...existing,
      [provider]: connection,
    }

    cacheByOrg.set(id, next)
    connections.value = next
  }

  const connectedProviders = computed((): DnsProvider[] => {
    const current = connections.value

    if (current === null) {
      return []
    }

    return (Object.keys(current) as DnsProvider[]).filter((provider) => current[provider].connected)
  })

  const connectionFlags = computed((): Record<DnsProvider, boolean> => ({
    cloudflare: connections.value?.cloudflare.connected === true,
    digitalocean: connections.value?.digitalocean.connected === true,
  }))

  watch(orgId, (next, previous) => {
    if (next !== previous) {
      connections.value = next === null ? null : (cacheByOrg.get(next) ?? null)
    }
  })

  return {
    connections,
    isLoading,
    loadError,
    ensureLoaded,
    updateProvider,
    connectedProviders,
    connectionFlags,
  }
}
