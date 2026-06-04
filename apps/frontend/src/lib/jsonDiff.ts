export function formatJsonState(state: Record<string, unknown> | null | undefined): string {
  if (state === null || state === undefined) {
    return '—'
  }

  return JSON.stringify(state, null, 2)
}
