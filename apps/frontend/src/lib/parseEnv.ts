export interface ParsedEnvEntry {
  key: string
  value: string
}

export function parseEnvContent(content: string): ParsedEnvEntry[] {
  const entries: ParsedEnvEntry[] = []

  for (const rawLine of content.split('\n')) {
    const line = rawLine.trim()

    if (line === '' || line.startsWith('#')) {
      continue
    }

    const exportPrefix = line.startsWith('export ') ? 7 : 0
    const normalized = line.slice(exportPrefix)
    const separatorIndex = normalized.indexOf('=')

    if (separatorIndex <= 0) {
      continue
    }

    const key = normalized.slice(0, separatorIndex).trim()
    let value = normalized.slice(separatorIndex + 1).trim()

    if (
      (value.startsWith('"') && value.endsWith('"'))
      || (value.startsWith('\'') && value.endsWith('\''))
    ) {
      value = value.slice(1, -1)
    }

    entries.push({ key, value })
  }

  return entries
}
