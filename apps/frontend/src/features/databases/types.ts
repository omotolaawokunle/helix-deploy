export const SERVER_DATABASE_BROADCAST_EVENTS = {
  serverReady: 'server.database.ready',
  siteReady: 'site.database.ready',
} as const

export function privateServerDatabasesChannel(serverId: string): string {
  return `server.${serverId}.databases`
}

export type DatabaseEngine = 'postgresql' | 'mysql'

export type DatabaseBrowseKind = 'databases' | 'tables' | 'rows'

export type DatabaseBrowseStatus = 'loading' | 'ready' | 'failed'

export type DatabaseRowFilterOperator =
  | 'eq'
  | 'neq'
  | 'contains'
  | 'starts_with'
  | 'ends_with'
  | 'gt'
  | 'gte'
  | 'lt'
  | 'lte'
  | 'is_null'
  | 'is_not_null'

export interface DatabaseRowFilter {
  column: string
  operator: DatabaseRowFilterOperator
  value: string | null
}

export interface DatabaseRowQueryParams {
  page?: number
  limit?: number
  filter?: DatabaseRowFilter[]
}

export interface DatabaseBrowseResponse {
  status: DatabaseBrowseStatus
  kind: DatabaseBrowseKind | null
  engine: DatabaseEngine | null
  database: string | null
  table: string | null
  databases: string[]
  tables: string[]
  columns: string[]
  rows: string[][]
  rowCount: number
  hasMore: boolean
  page: number
  offset: number
  limit: number | null
  filters: DatabaseRowFilter[]
  message?: string | null
}

export interface ServerDatabaseBrowseReadyPayload {
  serverId: string
  organizationId: string
  engine: DatabaseEngine
  kind: DatabaseBrowseKind
  database: string | null
  table: string | null
  limit: number
  page?: number | null
  filters?: DatabaseRowFilter[]
  status: 'ready' | 'failed'
  message?: string | null
}

export interface SiteDatabaseBrowseReadyPayload {
  serverId: string
  organizationId: string
  siteId: string
  kind: DatabaseBrowseKind
  table: string | null
  limit: number
  page?: number | null
  filters?: DatabaseRowFilter[]
  status: 'ready' | 'failed'
  message?: string | null
}

export const DATABASE_ROW_FILTER_OPERATORS: Array<{
  value: DatabaseRowFilterOperator
  label: string
  requiresValue: boolean
}> = [
  { value: 'eq', label: 'equals', requiresValue: true },
  { value: 'neq', label: 'not equals', requiresValue: true },
  { value: 'contains', label: 'contains', requiresValue: true },
  { value: 'starts_with', label: 'starts with', requiresValue: true },
  { value: 'ends_with', label: 'ends with', requiresValue: true },
  { value: 'gt', label: 'greater than', requiresValue: true },
  { value: 'gte', label: 'greater or equal', requiresValue: true },
  { value: 'lt', label: 'less than', requiresValue: true },
  { value: 'lte', label: 'less or equal', requiresValue: true },
  { value: 'is_null', label: 'is null', requiresValue: false },
  { value: 'is_not_null', label: 'is not null', requiresValue: false },
]

export function serializeRowFilters(filters: DatabaseRowFilter[]): string {
  return JSON.stringify(filters.map(filter => ({
    column: filter.column,
    operator: filter.operator,
    value: filter.value,
  })))
}

export function filtersMatch(
  left: DatabaseRowFilter[] | undefined,
  right: DatabaseRowFilter[],
): boolean {
  return serializeRowFilters(left ?? []) === serializeRowFilters(right)
}

export const DATABASE_LOADING_MESSAGES = {
  databases: [
    'Connecting with deploy credentials…',
    'Listing databases on the host…',
    'Scanning cluster catalogs…',
  ],
  tables: [
    'Opening schema catalog…',
    'Listing tables in this database…',
    'Reading public schema metadata…',
  ],
  rows: [
    'Running read-only SELECT…',
    'Fetching page of rows…',
    'Streaming result set from the server…',
  ],
} as const
