import { ref, type Ref } from 'vue'
import type { DatabaseRowFilter } from '@/features/databases/types'
import { filtersMatch, MAX_DATABASE_ROW_FILTERS, serializeRowFilters } from '@/features/databases/types'

export { MAX_DATABASE_ROW_FILTERS }

export function useDatabaseRowQuery(): {
  rowPage: Ref<number>
  rowFilters: Ref<DatabaseRowFilter[]>
  resetRowQuery: () => void
  rowQueryKey: () => string
  matchesRowPayload: (page: number | null | undefined, filters: DatabaseRowFilter[] | undefined) => boolean
} {
  const rowPage = ref(1)
  const rowFilters = ref<DatabaseRowFilter[]>([])

  function resetRowQuery(): void {
    rowPage.value = 1
    rowFilters.value = []
  }

  function rowQueryKey(): string {
    return `${rowPage.value}:${serializeRowFilters(rowFilters.value)}`
  }

  function matchesRowPayload(
    page: number | null | undefined,
    filters: DatabaseRowFilter[] | undefined,
  ): boolean {
    return (page ?? 1) === rowPage.value && filtersMatch(filters, rowFilters.value)
  }

  return {
    rowPage,
    rowFilters,
    resetRowQuery,
    rowQueryKey,
    matchesRowPayload,
  }
}
