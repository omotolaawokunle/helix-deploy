export function formatRelativeTime(isoDate: string | null): string {
  if (isoDate === null) {
    return '—'
  }

  const date = new Date(isoDate)
  const now = Date.now()
  const diffSeconds = Math.round((date.getTime() - now) / 1000)
  const absSeconds = Math.abs(diffSeconds)
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

  if (absSeconds < 60) {
    return formatter.format(diffSeconds, 'second')
  }

  const diffMinutes = Math.round(diffSeconds / 60)

  if (Math.abs(diffMinutes) < 60) {
    return formatter.format(diffMinutes, 'minute')
  }

  const diffHours = Math.round(diffMinutes / 60)

  if (Math.abs(diffHours) < 24) {
    return formatter.format(diffHours, 'hour')
  }

  const diffDays = Math.round(diffHours / 24)

  return formatter.format(diffDays, 'day')
}

export function formatDurationSeconds(duration: number | null): string {
  if (duration === null) {
    return '—'
  }

  return `${Math.round(duration)}s`
}

export function shortCommitHash(hash: string | null): string {
  if (hash === null || hash === '') {
    return '—'
  }

  return hash.slice(0, 8)
}

export function formatMetricPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${Math.round(value)}%`
}

export function metricUsageClass(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return ''
  }

  if (value >= 95) {
    return 'font-medium text-destructive'
  }

  if (value >= 85) {
    return 'font-medium text-amber-600 dark:text-amber-500'
  }

  return ''
}
