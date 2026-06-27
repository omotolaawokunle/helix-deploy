<script setup lang="ts">
import { RefreshCwIcon } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export interface LogTypeOption {
  value: string
  label: string
}

interface Props {
  logType: string
  lineCount: number
  logTypeOptions: LogTypeOption[]
  lineCountOptions: readonly number[]
  isLoading: boolean
  description?: string
  refreshTestId?: string
  controlsId?: string
}

const props = withDefaults(defineProps<Props>(), {
  description: undefined,
  refreshTestId: undefined,
  controlsId: 'log-viewer',
})

const emit = defineEmits<{
  'update:logType': [value: string]
  'update:lineCount': [value: number]
  refresh: []
}>()

const logTypeSelectId = `${props.controlsId}-log-type`
const lineCountSelectId = `${props.controlsId}-lines`
</script>

<template>
  <div class="space-y-3 animate-page-in motion-reduce:animate-none">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
        <div
          v-if="logTypeOptions.length > 1"
          class="space-y-2"
        >
          <Label :for="logTypeSelectId">Log type</Label>
          <Select
            :model-value="logType"
            @update:model-value="emit('update:logType', $event)"
          >
            <SelectTrigger
              :id="logTypeSelectId"
              class="w-full transition-colors duration-150 sm:w-[200px]"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in logTypeOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-2">
          <Label :for="lineCountSelectId">Lines</Label>
          <Select
            :model-value="String(lineCount)"
            @update:model-value="emit('update:lineCount', Number($event))"
          >
            <SelectTrigger
              :id="lineCountSelectId"
              class="w-full transition-colors duration-150 sm:w-[120px]"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="count in lineCountOptions"
                :key="count"
                :value="String(count)"
              >
                {{ count }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      <Button
        type="button"
        variant="outline"
        class="shrink-0 transition-transform duration-100 active:scale-[0.98] motion-reduce:transition-none motion-reduce:active:scale-100"
        :disabled="isLoading"
        :aria-busy="isLoading"
        :data-testid="refreshTestId"
        @click="emit('refresh')"
      >
        <RefreshCwIcon
          class="mr-2 size-4 motion-reduce:animate-none"
          :class="{ 'animate-spin': isLoading }"
          aria-hidden="true"
        />
        {{ isLoading ? 'Fetching…' : 'Refresh' }}
      </Button>
    </div>
    <p
      v-if="description !== undefined && description !== ''"
      class="animate-page-in-delay-1 text-sm leading-relaxed text-muted-foreground motion-reduce:animate-none"
    >
      {{ description }}
    </p>
  </div>
</template>
