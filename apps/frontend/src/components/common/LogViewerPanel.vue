<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, toRef, watch } from 'vue'
import { useVirtualList } from '@vueuse/core'
import { AlertCircleIcon, TerminalIcon } from '@lucide/vue'
import { useRotatingStatusMessage } from '@/composables/useRotatingStatusMessage'

interface Props {
  lines: string[]
  isLoading: boolean
  errorMessage?: string | null
  emptyMessage?: string
  requestedLines?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  errorMessage: null,
  emptyMessage: 'No log lines in this snapshot.',
  requestedLines: null,
})

const emit = defineEmits<{
  retry: []
}>()

const VIRTUAL_THRESHOLD = 80
const LINE_HEIGHT_PX = 18

const LOADING_MESSAGES = [
  'Connecting over SSH to read the log file…',
  'Running tail on the selected log path…',
  'Waiting for the server to return lines…',
  'Pulling the latest snapshot from disk…',
] as const

const linesRef = toRef(props, 'lines')
const contentRef = ref<HTMLElement | null>(null)
const showReadyFlash = ref(false)
let readyFlashTimer: ReturnType<typeof setTimeout> | null = null

const useVirtual = computed(() => props.lines.length >= VIRTUAL_THRESHOLD && !props.isLoading)

const displayText = computed(() => props.lines.join('\n'))

const isEmptySnapshot = computed(
  () => !props.isLoading
    && (props.errorMessage === null || props.errorMessage === '')
    && props.lines.length === 0,
)

const loadingMessage = useRotatingStatusMessage(
  LOADING_MESSAGES,
  computed(() => props.isLoading),
)

const footerLabel = computed((): string | null => {
  if (props.isLoading || (props.errorMessage !== null && props.errorMessage !== '')) {
    return null
  }

  if (props.lines.length === 0) {
    return 'Snapshot empty'
  }

  const requested = props.requestedLines

  if (requested !== null && props.lines.length < requested) {
    return `Showing ${props.lines.length} of ${requested} requested lines`
  }

  return `Showing ${props.lines.length} line${props.lines.length === 1 ? '' : 's'}`
})

const { list, containerProps, wrapperProps, scrollTo } = useVirtualList(linesRef, {
  itemHeight: LINE_HEIGHT_PX,
  overscan: 10,
})

function triggerReadyFlash(): void {
  if (readyFlashTimer !== null) {
    clearTimeout(readyFlashTimer)
  }

  showReadyFlash.value = true
  readyFlashTimer = setTimeout(() => {
    showReadyFlash.value = false
    readyFlashTimer = null
  }, 650)
}

watch(
  () => props.isLoading,
  (loading, wasLoading) => {
    if (wasLoading === true && loading === false && props.lines.length > 0) {
      triggerReadyFlash()
    }
  },
)

watch(
  () => props.lines.length,
  async (length, previousLength) => {
    if (props.isLoading || length === 0 || length === previousLength) {
      return
    }

    await nextTick()

    if (useVirtual.value) {
      scrollTo(length - 1)

      return
    }

    const element = contentRef.value

    if (element !== null) {
      element.scrollTop = element.scrollHeight
    }
  },
)

onUnmounted(() => {
  if (readyFlashTimer !== null) {
    clearTimeout(readyFlashTimer)
  }
})
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-zinc-800">
    <Transition name="status-crossfade" mode="out-in">
      <div
        v-if="isLoading"
        key="loading"
        class="log-panel min-h-48 max-h-[70vh] space-y-3 overflow-auto p-4"
        role="status"
        aria-live="polite"
        aria-busy="true"
        data-testid="log-viewer-loading"
      >
        <div class="flex items-center gap-2 text-zinc-400">
          <span
            class="inline-flex size-1.5 animate-pulse rounded-full bg-primary motion-reduce:animate-none"
            aria-hidden="true"
          />
          <span
            :key="loadingMessage"
            class="log-loading-message text-sm"
          >
            {{ loadingMessage }}
          </span>
        </div>
        <div class="space-y-2" aria-hidden="true">
          <div
            class="h-3 w-full animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none"
            style="animation-delay: 0ms"
          />
          <div
            class="h-3 w-10/12 animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none"
            style="animation-delay: 80ms"
          />
          <div
            class="h-3 w-4/5 animate-pulse rounded bg-zinc-800/80 motion-reduce:animate-none"
            style="animation-delay: 160ms"
          />
        </div>
      </div>

      <div
        v-else-if="errorMessage !== null && errorMessage !== ''"
        key="error"
        class="log-panel flex min-h-48 max-h-[70vh] flex-col justify-center gap-3 p-6 animate-panel-in"
        role="alert"
        data-testid="log-viewer-error"
      >
        <div class="flex items-start gap-3 text-destructive">
          <AlertCircleIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <p class="text-sm font-medium">
            {{ errorMessage }}
          </p>
        </div>
        <p class="text-sm text-zinc-400">
          Check that the server is reachable and the log file exists, then try again.
        </p>
        <div>
          <button
            type="button"
            class="text-sm font-medium text-primary underline-offset-4 transition-opacity duration-150 hover:underline active:opacity-80"
            @click="emit('retry')"
          >
            Retry fetch
          </button>
        </div>
      </div>

      <div
        v-else-if="isEmptySnapshot"
        key="empty"
        class="log-panel flex min-h-48 max-h-[70vh] flex-col items-center justify-center gap-3 px-6 py-10 text-center animate-panel-in"
        data-testid="log-viewer-empty"
      >
        <div
          class="flex size-12 items-center justify-center rounded-full border border-zinc-800 bg-zinc-900/80"
          aria-hidden="true"
        >
          <TerminalIcon class="size-5 text-zinc-500" />
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium text-zinc-300">
            {{ emptyMessage }}
          </p>
          <p class="text-sm text-zinc-500">
            The file may not exist yet, or nothing has been written since rotation.
          </p>
        </div>
        <button
          type="button"
          class="text-sm font-medium text-primary underline-offset-4 transition-opacity duration-150 hover:underline active:opacity-80"
          @click="emit('retry')"
        >
          Refresh snapshot
        </button>
      </div>

      <div
        v-else
        key="content"
        class="animate-panel-in"
        :class="{ 'log-snapshot-ready': showReadyFlash }"
      >
        <div
          v-if="useVirtual"
          class="log-panel max-h-[70vh] min-h-48 overflow-y-auto overscroll-y-contain p-2"
          data-testid="log-viewer-virtual"
          v-bind="containerProps"
        >
          <div v-bind="wrapperProps">
            <pre
              v-for="{ data: line, index } in list"
              :key="index"
              class="log-line whitespace-pre-wrap break-words px-2 py-px text-xs leading-relaxed text-zinc-300"
              :style="{ minHeight: `${LINE_HEIGHT_PX}px` }"
            >{{ line }}</pre>
          </div>
        </div>
        <pre
          v-else
          ref="contentRef"
          class="log-panel max-h-[70vh] min-h-48 overflow-auto whitespace-pre-wrap p-4 text-zinc-300"
          data-testid="log-viewer-content"
        >{{ displayText }}</pre>
      </div>
    </Transition>

    <Transition name="fade-up">
      <div
        v-if="footerLabel !== null"
        key="footer"
        class="border-t border-zinc-800 bg-zinc-950 px-4 py-2 text-xs text-zinc-500"
        data-testid="log-viewer-footer"
      >
        {{ footerLabel }}
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.log-line {
  content-visibility: auto;
  contain-intrinsic-size: auto 1.125rem;
}
</style>
