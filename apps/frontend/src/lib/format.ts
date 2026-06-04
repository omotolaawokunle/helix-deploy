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
