<script setup lang="ts">
import { computed, onMounted, ref, toRef, watch } from 'vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useDatabaseBrowser } from '@/composables/useDatabaseBrowser'
import { useDatabaseRowQuery } from '@/composables/useDatabaseRowQuery'
import { useServerDatabasesChannel } from '@/composables/useServerDatabasesChannel'
import DatabaseBrowserPanel from '@/features/databases/components/DatabaseBrowserPanel.vue'
import type {
  DatabaseBrowseKind,
  DatabaseEngine,
  DatabaseRowFilter,
  ServerDatabaseBrowseReadyPayload,
} from '@/features/databases/types'
import {
  fetchServerDatabaseRows,
  fetchServerDatabases,
  fetchServerDatabaseTables,
  fetchServerServices,
} from '@/features/servers/api'

interface Props {
  serverId: string
  isProduction?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isProduction: false,
})

const serverId = toRef(props, 'serverId')

type BrowseStep = 'databases' | 'tables' | 'rows'

const step = ref<BrowseStep>('databases')
const selectedEngine = ref<DatabaseEngine>('postgresql')
const selectedDatabase = ref<string | null>(null)
const selectedTable = ref<string | null>(null)
const availableEngines = ref<DatabaseEngine[]>([])

const { rowPage, rowFilters, resetRowQuery, rowQueryKey, matchesRowPayload } = useDatabaseRowQuery()

const currentKind = computed((): DatabaseBrowseKind => step.value)

function matchesReadyPayload(payload: ServerDatabaseBrowseReadyPayload): boolean {
  if (payload.kind !== currentKind.value) {
    return false
  }

  if (step.value !== 'rows') {
    return true
  }

  return matchesRowPayload(payload.page, payload.filters)
}

const {
  data,
  isLoading,
  isFetching,
  errorMessage,
  showReadyFlash,
  load,
  handleRefresh,
  handleBrowseReady,
  stopPolling,
} = useDatabaseBrowser({
  buildRequestKey: () => [
    serverId.value,
    selectedEngine.value,
    step.value,
    selectedDatabase.value ?? '',
    selectedTable.value ?? '',
    rowQueryKey(),
  ].join(':'),
  fetchBrowse: async (refresh) => {
    if (step.value === 'databases') {
      return fetchServerDatabases(serverId.value, { engine: selectedEngine.value, refresh })
    }

    if (step.value === 'tables' && selectedDatabase.value !== null) {
      return fetchServerDatabaseTables(serverId.value, selectedDatabase.value, {
        engine: selectedEngine.value,
        refresh,
      })
    }

    if (step.value === 'rows' && selectedDatabase.value !== null && selectedTable.value !== null) {
      return fetchServerDatabaseRows(serverId.value, selectedDatabase.value, selectedTable.value, {
        engine: selectedEngine.value,
        refresh,
        page: rowPage.value,
        limit: 50,
        filter: rowFilters.value,
      })
    }

    throw new Error('Invalid browse state')
  },
  matchesReadyPayload: payload => matchesReadyPayload(payload as ServerDatabaseBrowseReadyPayload),
  defaultErrorMessage: 'Unable to browse database.',
})

useServerDatabasesChannel(serverId, {
  onServerDatabaseReady: handleBrowseReady,
})

async function loadEngines(): Promise<void> {
  const services = await fetchServerServices(serverId.value)
  const engines: DatabaseEngine[] = []

  if (services.some(service => service.key === 'postgresql' && service.installed)) {
    engines.push('postgresql')
  }

  if (services.some(service => service.key === 'mysql' && service.installed)) {
    engines.push('mysql')
  }

  availableEngines.value = engines

  if (engines.length > 0 && ! engines.includes(selectedEngine.value)) {
    selectedEngine.value = engines[0]
  }
}

function silentRowLoad(): void {
  void load(false, data.value?.status === 'ready' && step.value === 'rows')
}

function navigateDatabases(): void {
  step.value = 'databases'
  selectedDatabase.value = null
  selectedTable.value = null
  resetRowQuery()
  stopPolling()
  void load(false)
}

function navigateTables(): void {
  step.value = 'tables'
  selectedTable.value = null
  resetRowQuery()
  stopPolling()
  void load(false)
}

function selectDatabase(name: string): void {
  selectedDatabase.value = name
  step.value = 'tables'
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
  silentRowLoad()
}

function handleApplyFilters(filters: DatabaseRowFilter[]): void {
  rowFilters.value = filters
  rowPage.value = 1
  stopPolling()
  silentRowLoad()
}

watch(selectedEngine, () => {
  navigateDatabases()
})

onMounted(async () => {
  await loadEngines()

  if (availableEngines.value.length > 0) {
    void load(false)
  }
})
</script>

<template>
  <div class="space-y-4 animate-page-in motion-reduce:animate-none">
    <ProductionWarningBanner
      v-if="isProduction"
      resource-name="server database"
      :is-production="isProduction"
      message="You are browsing databases on a production server. Queries are read-only but may expose sensitive data."
    />

    <div v-if="availableEngines.length === 0" class="panel p-4 text-sm text-muted-foreground">
      Install PostgreSQL or MySQL on this server to browse databases from the control plane.
    </div>

    <template v-else>
      <div v-if="availableEngines.length > 1" class="flex max-w-xs flex-col gap-2">
        <Label for="db-engine">Engine</Label>
        <Select v-model="selectedEngine">
          <SelectTrigger id="db-engine">
            <SelectValue placeholder="Select engine" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="postgresql">
              PostgreSQL
            </SelectItem>
            <SelectItem value="mysql">
              MySQL
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <DatabaseBrowserPanel
        :data="data"
        :is-loading="isLoading"
        :is-fetching="isFetching"
        :error-message="errorMessage"
        :show-ready-flash="showReadyFlash"
        :kind="currentKind"
        :database-label="selectedDatabase"
        :table-label="selectedTable"
        :row-page="rowPage"
        :row-filters="rowFilters"
        @refresh="handleRefresh"
        @select-database="selectDatabase"
        @select-table="selectTable"
        @navigate-databases="navigateDatabases"
        @navigate-tables="navigateTables"
        @change-page="handleChangePage"
        @apply-filters="handleApplyFilters"
      />
    </template>
  </div>
</template>
