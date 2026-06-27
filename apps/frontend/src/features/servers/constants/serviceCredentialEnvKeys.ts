export const LINKABLE_SERVICE_KEYS = ['postgresql', 'mysql', 'redis'] as const

export type LinkableServiceKey = typeof LINKABLE_SERVICE_KEYS[number]

export const DEFAULT_ENV_KEYS: Record<LinkableServiceKey, string> = {
  postgresql: 'DB_PASSWORD',
  mysql: 'DB_PASSWORD',
  redis: 'REDIS_PASSWORD',
}

export function isLinkableServiceKey(serviceKey: string): serviceKey is LinkableServiceKey {
  return (LINKABLE_SERVICE_KEYS as readonly string[]).includes(serviceKey)
}

export function defaultEnvKeyForService(serviceKey: string): string {
  if (isLinkableServiceKey(serviceKey)) {
    return DEFAULT_ENV_KEYS[serviceKey]
  }

  return ''
}
