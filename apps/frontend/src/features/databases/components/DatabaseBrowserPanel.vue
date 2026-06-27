<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronRightIcon as BreadcrumbChevronIcon,
  DatabaseIcon,
  FilterIcon,
  LockIcon,
  RefreshCwIcon,
  TableIcon,
  XIcon,
} from '@lucide/vue'
import { useRotatingStatusMessage } from '@/composables/useRotatingStatusMessage'
import EmptyState from '@/components/common/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type {
  DatabaseBrowseKind,
  DatabaseBrowseResponse,
  DatabaseRowFilter,
  DatabaseRowFilterOperator,
} from '@/features/databases/types'
import {
  DATABASE_LOADING_MESSAGES,
  DATABASE_ROW_FILTER_OPERATORS,
} from '@/features/databases/types'

interface Props {
  data: DatabaseBrowseResponse | null
  isLoading: boolean
  errorMessage: string | null
  showReadyFlash: boolean
  kind: DatabaseBrowseKind
  databaseLabel?: string | null
  tableLabel?: string | null
  rowPage?: number
  rowFilters?: DatabaseRowFilter[]
}

const props = withDefaults(defineProps<Props>(), {
  databaseLabel: null,
  tableLabel: null,
  rowPage: 1,
  rowFilters: () => [],
})

const emit = defineEmits<{
  refresh: []
  selectDatabase: [name: string]
  selectTable: [name: string]
  navigateDatabases: []
  navigateTables: []
  changePage: [page: number]
  applyFilters: [filters: DatabaseRowFilter[]]
}>()

const isLoadingRef = computed(() => props.isLoading)

const loadingMessage = useRotatingStatusMessage(
  DATABASE_LOADING_MESSAGES[props.kind],
  isLoadingRef,
)

const draftColumn = ref('')
const draftOperator = ref<DatabaseRowFilterOperator>('eq')
const draftValue = ref('')

const operatorRequiresValue = computed((): boolean => {
  const match = DATABASE_ROW_FILTER_OPERATORS.find(operator => operator.value === draftOperator.value)

  return match?.requiresValue ?? true
})

const availableColumns = computed((): string[] => props.data?.columns ?? [])

watch(availableColumns, (columns) => {
  if (columns.length > 0 && ! columns.includes(draftColumn.value)) {
    draftColumn.value = columns[0]
  }
})

function rowEntranceDelay(index: number): string {
  return `${Math.min(index, 12) * 40}ms`
}

function operatorLabel(operator: DatabaseRowFilterOperator): string {
  return DATABASE_ROW_FILTER_OPERATORS.find(item => item.value === operator)?.label ?? operator
}

function addDraftFilter(): void {
  if (draftColumn.value === '') {
    return
  }

  const next: DatabaseRowFilter = {
    column: draftColumn.value,
    operator: draftOperator.value,
    value: operatorRequiresValue.value ? draftValue.value : null,
  }

  emit('applyFilters', [...props.rowFilters, next])
  draftValue.value = ''
}

function removeFilter(index: number): void {
  const next = props.rowFilters.filter((_, filterIndex) => filterIndex !== index)
  emit('applyFilters', next)
}

function clearFilters(): void {
  emit('applyFilters', [])
}

const breadcrumbItems = computed((): Array<{ label: string; action?: () => void }> => {
  const items: Array<{ label: string; action?: () => void }> = []

  if (props.kind !== 'databases') {
    items.push({ label: 'Databases', action: () => emit('navigateDatabases') })
  }

  if (props.kind === 'rows' && props.databaseLabel !== null && props.databaseLabel !== undefined) {
    items.push({
      label: props.databaseLabel,
      action: () => emit('navigateTables'),
    })
  }

  if (props.kind === 'tables' && props.databaseLabel) {
    items.push({ label: props.databaseLabel })
  }

  if (props.kind === 'rows' && props.tableLabel) {
    items.push({ label: props.tableLabel })
  }

  return items
})

const canGoPrevious = computed((): boolean => props.rowPage > 1)
const canGoNext = computed((): boolean => props.data?.hasMore === true)
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <Badge variant="outline" class="gap-1 font-normal">
          <LockIcon class="size-3" aria-hidden="true" />
          Read-only
        </Badge>
        <nav
          v-if="breadcrumbItems.length > 0"
          class="flex flex-wrap items-center gap-1 text-muted-foreground"
          aria-label="Database breadcrumb"
        >
          <template v-for="(item, index) in breadcrumbItems" :key="`${item.label}-${index}`">
            <BreadcrumbChevronIcon v-if="index > 0" class="size-3.5 shrink-0" aria-hidden="true" />
            <button
              v-if="item.action !== undefined"
              type="button"
              class="font-mono text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline motion-reduce:transition-none"
              @click="item.action"
            >
              {{ item.label }}
            </button>
            <span v-else class="font-mono text-foreground">{{ item.label }}</span>
          </template>
        </nav>
      </div>
      <Button
        type="button"
        size="sm"
        variant="outline"
        class="transition-transform duration-100 active:scale-[0.98] motion-reduce:transform-none"
        :disabled="isLoading"
        @click="emit('refresh')"
      >
        <RefreshCwIcon
          class="mr-2 size-4 motion-reduce:animate-none"
          :class="{ 'animate-spin': isLoading }"
          aria-hidden="true"
        />
        Refresh
      </Button>
    </div>

    <div
      v-if="kind === 'rows' && availableColumns.length > 0"
      class="panel space-y-3 p-4"
    >
      <div class="flex items-center gap-2 text-sm font-medium">
        <FilterIcon class="size-4 text-muted-foreground" aria-hidden="true" />
        Filter rows
      </div>

      <div v-if="rowFilters.length > 0" class="flex flex-wrap gap-2">
        <Badge
          v-for="(filter, index) in rowFilters"
          :key="`${filter.column}-${filter.operator}-${index}`"
          variant="secondary"
          class="gap-1 font-mono text-xs"
        >
          {{ filter.column }} {{ operatorLabel(filter.operator) }}
          <template v-if="filter.value !== null && filter.value !== ''">
            "{{ filter.value }}"
          </template>
          <button
            type="button"
            class="ml-1 rounded-sm hover:text-destructive"
            :aria-label="`Remove filter on ${filter.column}`"
            @click="removeFilter(index)"
          >
            <XIcon class="size-3" aria-hidden="true" />
          </button>
        </Badge>
        <Button type="button" size="sm" variant="ghost" @click="clearFilters">
          Clear all
        </Button>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="space-y-1.5">
          <Label for="filter-column">Column</Label>
          <Select v-model="draftColumn">
            <SelectTrigger id="filter-column">
              <SelectValue placeholder="Column" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="column in availableColumns" :key="column" :value="column">
                {{ column }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-1.5">
          <Label for="filter-operator">Operator</Label>
          <Select v-model="draftOperator">
            <SelectTrigger id="filter-operator">
              <SelectValue placeholder="Operator" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="operator in DATABASE_ROW_FILTER_OPERATORS"
                :key="operator.value"
                :value="operator.value"
              >
                {{ operator.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div v-if="operatorRequiresValue" class="space-y-1.5 sm:col-span-2">
          <Label for="filter-value">Value</Label>
          <Input
            id="filter-value"
            v-model="draftValue"
            placeholder="Filter value"
            class="font-mono text-sm"
            @keydown.enter.prevent="addDraftFilter"
          />
        </div>
      </div>

      <Button type="button" size="sm" :disabled="isLoading" @click="addDraftFilter">
        Apply filter
      </Button>
    </div>

    <Transition name="status-crossfade" mode="out-in">
      <div
        v-if="isLoading"
        key="loading"
        class="panel space-y-3 p-4"
        role="status"
        aria-live="polite"
        aria-busy="true"
      >
        <div class="flex items-center gap-2 text-muted-foreground">
          <span
            class="inline-flex size-1.5 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
            aria-hidden="true"
          />
          <span :key="loadingMessage" class="log-loading-message text-sm">{{ loadingMessage }}</span>
        </div>
        <Skeleton class="h-4 w-48 motion-reduce:animate-none" />
        <Skeleton class="h-24 w-full motion-reduce:animate-none" />
      </div>

      <p v-else-if="errorMessage !== null" key="error" class="text-sm text-destructive" role="alert">
        {{ errorMessage }}
      </p>

      <div
        v-else-if="data?.status === 'ready'"
        key="ready"
        class="panel overflow-hidden motion-reduce:transition-none"
        :class="{ 'env-diff-ready motion-reduce:animate-none': showReadyFlash }"
      >
        <EmptyState
          v-if="kind === 'databases' && data.databases.length === 0"
          title="No databases found"
          description="The deploy user could not list any databases. Check provisioning and credentials."
          :icon="DatabaseIcon"
        />
        <EmptyState
          v-else-if="kind === 'tables' && data.tables.length === 0"
          title="No tables in this database"
          description="The public schema has no tables, or the deploy user lacks access."
          :icon="TableIcon"
        />
        <EmptyState
          v-else-if="kind === 'rows' && data.rows.length === 0"
          title="No rows returned"
          description="This table is empty or the query returned no results."
          :icon="TableIcon"
        />

        <Table v-else-if="kind === 'databases'">
          <TableHeader>
            <TableRow>
              <TableHead>Database</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="(name, index) in data.databases"
              :key="name"
              class="cursor-pointer animate-env-row-in motion-reduce:animate-none hover:bg-muted/40"
              :style="{ animationDelay: rowEntranceDelay(index) }"
              @click="emit('selectDatabase', name)"
            >
              <TableCell class="font-mono text-sm">
                {{ name }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>

        <Table v-else-if="kind === 'tables'">
          <TableHeader>
            <TableRow>
              <TableHead>Table</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="(name, index) in data.tables"
              :key="name"
              class="cursor-pointer animate-env-row-in motion-reduce:animate-none hover:bg-muted/40"
              :style="{ animationDelay: rowEntranceDelay(index) }"
              @click="emit('selectTable', name)"
            >
              <TableCell class="font-mono text-sm">
                {{ name }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>

        <div v-else-if="kind === 'rows'" class="overflow-x-auto">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-2">
            <p class="text-xs text-muted-foreground">
              Page {{ data.page }} · {{ data.rowCount }} row{{ data.rowCount === 1 ? '' : 's' }}
              (limit {{ data.limit ?? 50 }})
            </p>
            <div class="flex items-center gap-2">
              <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="!canGoPrevious || isLoading"
                @click="emit('changePage', rowPage - 1)"
              >
                <ChevronLeftIcon class="mr-1 size-4" aria-hidden="true" />
                Previous
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="!canGoNext || isLoading"
                @click="emit('changePage', rowPage + 1)"
              >
                Next
                <ChevronRightIcon class="ml-1 size-4" aria-hidden="true" />
              </Button>
            </div>
          </div>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead v-for="column in data.columns" :key="column" class="font-mono text-xs">
                  {{ column }}
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="(row, rowIndex) in data.rows"
                :key="rowIndex"
                class="animate-env-row-in motion-reduce:animate-none"
                :style="{ animationDelay: rowEntranceDelay(rowIndex) }"
              >
                <TableCell
                  v-for="(cell, cellIndex) in row"
                  :key="cellIndex"
                  class="max-w-xs truncate font-mono text-xs"
                  :title="cell"
                >
                  {{ cell === '' ? '—' : cell }}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </Transition>
  </div>
</template>
