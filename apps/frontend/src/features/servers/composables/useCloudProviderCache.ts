import {
  fetchCloudInstances,
  fetchCloudProviders,
  type CloudInstance,
  type CloudProviderConnection,
  type CloudProviderType,
} from '@/features/servers/api'

const STATUS_CACHE_TTL_MS = 30_000
const INSTANCE_CACHE_TTL_MS = 60_000

interface Timestamped<T> {
  fetchedAt: number
  value: T
}

const providerStatusCache = new Map<string, Timestamped<CloudProviderConnection[]>>()
const providerStatusInflight = new Map<string, Promise<CloudProviderConnection[]>>()
const instanceCache = new Map<string, Timestamped<CloudInstance[]>>()
const instanceInflight = new Map<string, Promise<CloudInstance[]>>()

function instanceCacheKey(organizationId: string, provider: CloudProviderType): string {
  return `${organizationId}:${provider}`
}

export function invalidateCloudProviderStatusCache(organizationId: string): void {
  providerStatusCache.delete(organizationId)
  providerStatusInflight.delete(organizationId)

  for (const key of instanceCache.keys()) {
    if (key.startsWith(`${organizationId}:`)) {
      instanceCache.delete(key)
      instanceInflight.delete(key)
    }
  }
}

export async function fetchCloudProvidersCached(
  organizationId: string,
  options: { force?: boolean } = {},
): Promise<CloudProviderConnection[]> {
  const cached = providerStatusCache.get(organizationId)
  const isFresh = cached !== undefined && Date.now() - cached.fetchedAt < STATUS_CACHE_TTL_MS

  if (!options.force && isFresh) {
    return cached.value
  }

  const inflight = providerStatusInflight.get(organizationId)

  if (!options.force && inflight !== undefined) {
    return inflight
  }

  const request = fetchCloudProviders(organizationId)
    .then((providers) => {
      providerStatusCache.set(organizationId, {
        fetchedAt: Date.now(),
        value: providers,
      })

      return providers
    })
    .finally(() => {
      providerStatusInflight.delete(organizationId)
    })

  providerStatusInflight.set(organizationId, request)

  return request
}

export async function fetchCloudInstancesCached(
  organizationId: string,
  provider: CloudProviderType,
  options: { force?: boolean } = {},
): Promise<CloudInstance[]> {
  const cacheKey = instanceCacheKey(organizationId, provider)
  const cached = instanceCache.get(cacheKey)
  const isFresh = cached !== undefined && Date.now() - cached.fetchedAt < INSTANCE_CACHE_TTL_MS

  if (!options.force && isFresh) {
    return cached.value
  }

  const inflight = instanceInflight.get(cacheKey)

  if (!options.force && inflight !== undefined) {
    return inflight
  }

  const request = fetchCloudInstances(organizationId, provider)
    .then((instances) => {
      instanceCache.set(cacheKey, {
        fetchedAt: Date.now(),
        value: instances,
      })

      return instances
    })
    .finally(() => {
      instanceInflight.delete(cacheKey)
    })

  instanceInflight.set(cacheKey, request)

  return request
}
