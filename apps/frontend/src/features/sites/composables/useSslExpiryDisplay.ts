export function expiryClass(daysUntilExpiry: number | null): string {
  if (daysUntilExpiry === null) {
    return 'text-muted-foreground'
  }

  if (daysUntilExpiry <= 7) {
    return 'font-medium text-destructive'
  }

  if (daysUntilExpiry <= 30) {
    return 'font-medium text-amber-600 dark:text-amber-400'
  }

  return 'text-foreground'
}

export function formatExpiryDate(isoDate: string): string {
  return new Date(isoDate).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export function daysUntil(isoDate: string): number {
  const expiresAt = new Date(isoDate)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  expiresAt.setHours(0, 0, 0, 0)

  return Math.round((expiresAt.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
}

export function expiryLabel(daysUntilExpiry: number | null, sslExpiresAt: string | null): string {
  if (sslExpiresAt === null) {
    return '—'
  }

  const formatted = formatExpiryDate(sslExpiresAt)

  if (daysUntilExpiry === null) {
    return formatted
  }

  if (daysUntilExpiry < 0) {
    const elapsed = Math.abs(daysUntilExpiry)

    return `${formatted} (expired ${elapsed} day${elapsed === 1 ? '' : 's'} ago)`
  }

  if (daysUntilExpiry === 0) {
    return `${formatted} (expires today)`
  }

  return `${formatted} (${daysUntilExpiry} day${daysUntilExpiry === 1 ? '' : 's'} left)`
}

export function daysLabel(daysUntilExpiry: number | null): string {
  if (daysUntilExpiry === null) {
    return '—'
  }

  if (daysUntilExpiry < 0) {
    return 'Expired'
  }

  return `${daysUntilExpiry}d`
}

export function formatSslExpiryRelative(sslExpiresAt: string | null): string | null {
  if (sslExpiresAt === null) {
    return null
  }

  const remaining = daysUntil(sslExpiresAt)
  const formatted = formatExpiryDate(sslExpiresAt)

  if (remaining < 0) {
    const elapsed = Math.abs(remaining)

    return `${formatted} (expired ${elapsed} day${elapsed === 1 ? '' : 's'} ago)`
  }

  if (remaining === 0) {
    return `${formatted} (expires today)`
  }

  return `${formatted} (expires in ${remaining} day${remaining === 1 ? '' : 's'})`
}
