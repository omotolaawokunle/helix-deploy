<script setup lang="ts">
import { computed, nextTick, toRef, watch } from 'vue'
import { useVirtualList } from '@vueuse/core'

export interface DeploymentLogLine {
  timestamp: string
  content: string
}

interface Props {
  lines: DeploymentLogLine[]
  stepId: string
  followTail?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  followTail: false,
})

const VIRTUAL_THRESHOLD = 80
const LINE_HEIGHT_PX = 18

const useVirtual = computed(() => props.lines.length >= VIRTUAL_THRESHOLD)

const { list, containerProps, wrapperProps, scrollTo } = useVirtualList(
  toRef(props, 'lines'),
  {
    itemHeight: LINE_HEIGHT_PX,
    overscan: 12,
  },
)

watch(
  () => props.lines.length,
  async () => {
    if (!props.followTail || !useVirtual.value) {
      return
    }

    await nextTick()
    scrollTo(props.lines.length - 1)
  },
)

function lineColorClass(content: string): string {
  if (content.startsWith('  +') || content.startsWith('[success]')) {
    return 'text-green-400'
  }

  if (content.startsWith('  -') || content.startsWith('[error]') || content.startsWith('Error')) {
    return 'text-red-400'
  }

  if (content.toLowerCase().startsWith('warning')) {
    return 'text-yellow-400'
  }

  return 'text-zinc-300'
}

function formatTimestamp(iso: string): string {
  try {
    return new Date(iso).toLocaleTimeString()
  } catch {
    return iso
  }
}
</script>

<template>
  <div
    v-if="lines.length === 0"
    class="px-4 py-3 text-xs text-zinc-500"
  >
    Waiting for output…
  </div>

  <template v-else-if="!useVirtual">
    <pre
      v-for="(logLine, index) in lines"
      :key="`${stepId}-${index}`"
      class="log-line whitespace-pre-wrap break-words px-2 py-px text-xs leading-relaxed"
    >
      <span class="mr-3 select-none text-zinc-600">{{ formatTimestamp(logLine.timestamp) }}</span>
      <span :class="lineColorClass(logLine.content)">{{ logLine.content }}</span>
    </pre>
  </template>

  <div
    v-else
    class="max-h-[min(60vh,32rem)] overflow-y-auto overscroll-y-contain"
    data-testid="deployment-log-virtual-scroll"
    v-bind="containerProps"
  >
    <div v-bind="wrapperProps">
      <pre
        v-for="{ data: logLine, index } in list"
        :key="`${stepId}-${index}`"
        class="log-line whitespace-pre-wrap break-words px-2 py-px text-xs leading-relaxed"
        :style="{ minHeight: `${LINE_HEIGHT_PX}px` }"
      >
        <span class="mr-3 select-none text-zinc-600">{{ formatTimestamp(logLine.timestamp) }}</span>
        <span :class="lineColorClass(logLine.content)">{{ logLine.content }}</span>
      </pre>
    </div>
  </div>
</template>

<style scoped>
.log-line {
  content-visibility: auto;
  contain-intrinsic-size: auto 1.125rem;
}
</style>
