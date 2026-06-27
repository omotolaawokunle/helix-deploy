<script setup lang="ts">
import { computed, ref, toRef } from 'vue'
import { RouterLink } from 'vue-router'
import { SettingsIcon } from '@lucide/vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Button } from '@/components/ui/button'
import { useDatabaseBrowser } from '@/composables/useDatabaseBrowser'
import { useServerDatabasesChannel } from '@/composables/useServerDatabasesChannel'
import DatabaseBrowserPanel from '@/features/databases/components/DatabaseBrowserPanel.vue'
import type {
  DatabaseBrowseKind,
  DatabaseRowFilter,
  SiteDatabaseBrowseReadyPayload,
} from '@/features/databases/types'
import { filtersMatch, serializeRowFilters } from '@/features/databases/types'
import { fetchSiteDatabaseRows, fetchSiteDatabaseTables } from '@/features/sites/api'

interface Props {
  siteId: string
  serverId: string
  isProduction?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isProduction: false,
})

const siteId = toRef(props, 'siteId')
const serverId = toRef(props, 'serverId')

type BrowseStep = 'tables' | 'rows'

const step = ref<BrowseStep>('tables')
const selectedTable = ref<string | null>(null)
const rowPage = ref(1)
const rowFilters = ref<DatabaseRowFilter[]>([])

const showConfigHelper = computed((): boolean => {
  const message = errorMessage.value

  return message !== null && (
    message.includes('Missing required environment variable')
    || message.includes('Unsupported database connection type')
  )
})

const currentKind = computed((): DatabaseBrowseKind => step.value)

function matchesReadyPayload(payload: SiteDatabaseBrowseReadyPayload): boolean {
  if (payload.kind !== currentKind.value || payload.siteId !== siteId.value) {
    return false
  }

  if (step.value !== 'rows') {
    return true
  }

  return (payload.page ?? 1) === rowPage.value
    && filtersMatch(payload.filters, rowFilters.value)
}

const {
  data,
  isLoading,
  errorMessage,
  showReadyFlash,
  load,
  handleRefresh,
  handleBrowseReady,
  stopPolling,
} = useDatabaseBrowser({
  buildRequestKey: () => [
    siteId.value,
    step.value,
    selectedTable.value ?? '',
    rowPage.value,
    serializeRowFilters(rowFilters.value),
  ].join(':'),
  fetchBrowse: async (refresh) => {
    if (step.value === 'tables') {
      return fetchSiteDatabaseTables(siteId.value, { refresh })
    }

    if (step.value === 'rows' && selectedTable.value !== null) {
      return fetchSiteDatabaseRows(siteId.value, selectedTable.value, {
        refresh,
        page: rowPage.value,
        limit: 50,
        filter: rowFilters.value,
      })
    }

    throw new Error('Invalid browse state')
  },
  matchesReadyPayload: payload => matchesReadyPayload(payload as SiteDatabaseBrowseReadyPayload),
  defaultErrorMessage: 'Unable to browse site database.',
})

useServerDatabasesChannel(serverId, {
  onSiteDatabaseReady: handleBrowseReady,
})

function resetRowQuery(): void {
  rowPage.value = 1
  rowFilters.value = []
}

function navigateTables(): void {
  step.value = 'tables'
  selectedTable.value = null
  resetRowQuery()
  stopPolling()
  void load(false)
}

function selectTable(name: string): void {
  selectedTable.value = name
  step.value = 'rows'
  resetRowQuery()
  stopPolling()
  void load(false)
}

function handleChangePage(page: number): void {
  rowPage.value = page
  stopPolling()
  void load(false)
}

function handleApplyFilters(filters: DatabaseRowFilter[]): void {
  rowFilters.value = filters
  rowPage.value = 1
  stopPolling()
  void load(false)
}

async function initialLoad(): Promise<void> {
  await load(false)
}

void initialLoad()
</script>

<template>
  <div class="space-y-4 animate-page-in motion-reduce:animate-none">
    <ProductionWarningBanner
      v-if="isProduction"
      resource-name="site database"
      :is-production="isProduction"
      message="You are browsing this site's database on a production server. Queries are read-only but may expose sensitive data."
    />

    <div
      v-if="showConfigHelper"
      class="panel flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="space-y-1">
        <p class="text-sm font-medium">
          Database connection not configured
        </p>
        <p class="text-sm text-muted-foreground">
          Set <span class="font-mono">DB_HOST</span>, <span class="font-mono">DB_DATABASE</span>,
          <span class="font-mono">DB_USERNAME</span>, and <span class="font-mono">DB_PASSWORD</span> in environment variables.
        </p>
      </div>
      <Button as-child size="sm" variant="outline">
        <RouterLink :to="{ name: 'server-site-detail', params: { id: serverId, siteId }, query: { tab: 'env-vars' } }">
          <SettingsIcon class="mr-2 size-4" aria-hidden="true" />
          Open Env Vars
        </RouterLink>
      </Button>
    </div>

    <DatabaseBrowserPanel
      v-else
      :data="data"
      :is-loading="isLoading"
      :error-message="errorMessage"
      :show-ready-flash="showReadyFlash"
      :kind="currentKind"
      :database-label="data?.database ?? null"
      :table-label="selectedTable"
      :row-page="rowPage"
      :row-filters="rowFilters"
      @refresh="handleRefresh"
      @select-table="selectTable"
      @navigate-tables="navigateTables"
      @change-page="handleChangePage"
      @apply-filters="handleApplyFilters"
    />
  </div>
</template>
