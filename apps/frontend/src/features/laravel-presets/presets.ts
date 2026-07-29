export type LaravelDaemonPresetType = 'custom' | 'horizon' | 'queue'

export type LaravelCronPresetType = 'custom' | 'scheduler'

export type LaravelWorkerType = 'horizon' | 'queue'

export interface LaravelDaemonPresetValues {
  name: string
  command: string
  directory: string
  processes: number
}

export interface LaravelCronPresetValues {
  expression: string
  command: string
}

const WEBROOT_SUFFIXES = ['/current/public', '/current', '/public'] as const

export function deployBaseFromWebroot(webroot: string): string {
  const normalized = webroot.replace(/\/+$/, '')

  for (const suffix of WEBROOT_SUFFIXES) {
    if (normalized.endsWith(suffix)) {
      return normalized.slice(0, -suffix.length)
    }
  }

  return normalized
}

export function currentPathFromWebroot(webroot: string): string {
  return `${deployBaseFromWebroot(webroot)}/current`
}

export function slugFromDomain(domain: string, fallbackId?: string): string {
  let slug = domain.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')

  if (slug === '' || !/^[a-z0-9]/.test(slug)) {
    slug = fallbackId ? `site-${fallbackId.slice(0, 8)}` : 'site'
  }

  return slug
}

export function buildHorizonDaemonPreset(
  webroot: string,
  domain: string,
  siteId?: string,
): LaravelDaemonPresetValues {
  const currentPath = currentPathFromWebroot(webroot)
  const slug = slugFromDomain(domain, siteId)

  return {
    name: `${slug}-horizon`,
    command: 'php artisan horizon',
    directory: currentPath,
    processes: 1,
  }
}

export function buildQueueDaemonPreset(
  webroot: string,
  domain: string,
  siteId?: string,
): LaravelDaemonPresetValues {
  const currentPath = currentPathFromWebroot(webroot)
  const slug = slugFromDomain(domain, siteId)

  return {
    name: `${slug}-queue`,
    command: 'php artisan queue:work --sleep=3 --tries=3 --max-time=3600',
    directory: currentPath,
    processes: 1,
  }
}

export function buildSchedulerCronPreset(webroot: string): LaravelCronPresetValues {
  const currentPath = currentPathFromWebroot(webroot)

  return {
    expression: '* * * * *',
    command: `cd ${currentPath} && php artisan schedule:run >> /dev/null 2>&1`,
  }
}

export function buildDaemonPreset(
  preset: LaravelDaemonPresetType,
  webroot: string,
  domain: string,
  siteId?: string,
): LaravelDaemonPresetValues | null {
  switch (preset) {
    case 'horizon':
      return buildHorizonDaemonPreset(webroot, domain, siteId)
    case 'queue':
      return buildQueueDaemonPreset(webroot, domain, siteId)
    case 'custom':
      return null
    default: {
      const exhaustive: never = preset
      return exhaustive
    }
  }
}

export function buildCronPreset(
  preset: LaravelCronPresetType,
  webroot: string,
): LaravelCronPresetValues | null {
  switch (preset) {
    case 'scheduler':
      return buildSchedulerCronPreset(webroot)
    case 'custom':
      return null
    default: {
      const exhaustive: never = preset
      return exhaustive
    }
  }
}
