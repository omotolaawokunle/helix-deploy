import { ref, type Ref } from 'vue'

export function useCredentialReveal(): {
  revealedValues: Ref<Record<string, string>>
  pendingIds: Ref<Set<string>>
  isPending: (id: string) => boolean
  isRevealed: (id: string) => boolean
  hide: (id: string) => void
  hideAll: () => void
  setRevealed: (id: string, value: string) => void
  markPending: (id: string) => void
  clearPending: (id: string) => void
} {
  const revealedValues = ref<Record<string, string>>({})
  const pendingIds = ref<Set<string>>(new Set())

  function isPending(id: string): boolean {
    return pendingIds.value.has(id)
  }

  function isRevealed(id: string): boolean {
    return revealedValues.value[id] !== undefined
  }

  function hide(id: string): void {
    if (revealedValues.value[id] === undefined) {
      return
    }

    const next = { ...revealedValues.value }
    delete next[id]
    revealedValues.value = next
  }

  function hideAll(): void {
    revealedValues.value = {}
  }

  function setRevealed(id: string, value: string): void {
    revealedValues.value = {
      ...revealedValues.value,
      [id]: value,
    }
  }

  function markPending(id: string): void {
    pendingIds.value = new Set([...pendingIds.value, id])
  }

  function clearPending(id: string): void {
    const next = new Set(pendingIds.value)
    next.delete(id)
    pendingIds.value = next
  }

  return {
    revealedValues,
    pendingIds,
    isPending,
    isRevealed,
    hide,
    hideAll,
    setRevealed,
    markPending,
    clearPending,
  }
}
