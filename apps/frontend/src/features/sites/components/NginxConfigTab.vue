<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { fetchNginxConfig, saveNginxConfig } from '@/features/sites/api'
import { formatRelativeTime } from '@/lib/format'

interface Props {
  siteId: string
}

const props = defineProps<Props>()

const config = ref('')
const savedConfig = ref('')
const updatedAt = ref<string | null>(null)
const isEditable = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const saveMessage = ref<string | null>(null)
const saveError = ref<string | null>(null)

const POLL_INTERVAL_MS = 2000
const MAX_POLL_ATTEMPTS = 30

let pollTimer: ReturnType<typeof setTimeout> | null = null
let pollAttempts = 0

function stopPolling(): void {
  if (pollTimer !== null) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

function schedulePoll(): void {
  stopPolling()
  pollTimer = setTimeout(() => {
    void loadConfig(false)
  }, POLL_INTERVAL_MS)
}

async function loadConfig(refresh: boolean): Promise<void> {
  if (refresh) {
    isLoading.value = true
    pollAttempts = 0
    stopPolling()
    saveMessage.value = null
    saveError.value = null
  }

  try {
    const response = await fetchNginxConfig(props.siteId, { refresh })

    switch (response.status) {
      case 'loading':
        isLoading.value = true
        pollAttempts += 1

        if (pollAttempts >= MAX_POLL_ATTEMPTS) {
          stopPolling()
          isLoading.value = false
          toast.error('Unable to load nginx configuration.')

          return
        }

        schedulePoll()

        return
      case 'failed':
        stopPolling()
        isLoading.value = false
        toast.error(response.message ?? 'Unable to load nginx configuration.')

        return
      case 'ready':
        stopPolling()
        isLoading.value = false
        config.value = response.config
        savedConfig.value = response.config
        updatedAt.value = response.updatedAt

        return
      default: {
        const unexpected: never = response.status
        stopPolling()
        isLoading.value = false
        toast.error(`Unexpected nginx config status: ${String(unexpected)}`)
      }
    }
  } catch {
    stopPolling()
    isLoading.value = false
    toast.error('Unable to load nginx configuration.')
  }
}

function handleEdit(): void {
  isEditable.value = true
}

async function handleSave(): Promise<void> {
  isSaving.value = true
  saveMessage.value = null
  saveError.value = null

  try {
    const response = await saveNginxConfig(props.siteId, config.value)
    savedConfig.value = response.config
    config.value = response.config
    updatedAt.value = response.updatedAt
    isEditable.value = false
    saveMessage.value = 'Configuration saved and tested successfully.'
  } catch (error: unknown) {
    const message = error instanceof Error
      ? error.message
      : 'Nginx configuration test failed.'

    if (
      typeof error === 'object'
      && error !== null
      && 'response' in error
      && typeof (error as { response?: { data?: { error?: string } } }).response?.data?.error === 'string'
    ) {
      saveError.value = (error as { response: { data: { error: string } } }).response.data.error
    } else {
      saveError.value = message
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  void loadConfig(true)
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p v-if="updatedAt !== null" class="text-sm text-muted-foreground">
        Last updated: {{ formatRelativeTime(updatedAt) }}
      </p>
      <div class="flex gap-2">
        <Button
          v-if="!isEditable"
          type="button"
          variant="outline"
          data-testid="nginx-edit-button"
          @click="handleEdit"
        >
          Edit
        </Button>
        <Button
          v-if="isEditable"
          type="button"
          :disabled="isSaving"
          data-testid="nginx-save-button"
          @click="handleSave"
        >
          {{ isSaving ? 'Saving…' : 'Save & Test' }}
        </Button>
      </div>
    </div>

    <Textarea
      v-model="config"
      :readonly="!isEditable"
      rows="24"
      class="w-full font-mono text-sm"
      data-testid="nginx-config-textarea"
      :placeholder="isLoading ? 'Loading…' : ''"
    />

    <p v-if="saveMessage !== null" class="text-sm feedback-success" data-testid="nginx-save-success">
      {{ saveMessage }}
    </p>
    <p v-if="saveError !== null" class="text-sm text-destructive" data-testid="nginx-save-error">
      {{ saveError }}
    </p>
  </div>
</template>
