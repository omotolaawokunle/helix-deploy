<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { claimSite } from '@/features/sites/api'
import type { GitProviderType, Site } from '@/types'

interface Props {
  site: Site
  open: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  claimed: [site: Site]
}>()

const repositoryUrl = ref('')
const repositoryProvider = ref<GitProviderType>('github')
const deployBranch = ref('main')
const autoDeployEnabled = ref(false)
const isSubmitting = ref(false)
const revealedWebhookSecret = ref<string | null>(null)
const claimedSite = ref<Site | null>(null)

const showSecretPanel = computed(() => revealedWebhookSecret.value !== null)

const providerOptions: Array<{ value: GitProviderType; label: string }> = [
  { value: 'github', label: 'GitHub' },
  { value: 'gitlab', label: 'GitLab' },
  { value: 'bitbucket', label: 'Bitbucket' },
]

function resetForm(): void {
  repositoryUrl.value = ''
  repositoryProvider.value = 'github'
  deployBranch.value = props.site.deployBranch || 'main'
  autoDeployEnabled.value = false
  isSubmitting.value = false
  revealedWebhookSecret.value = null
  claimedSite.value = null
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  },
)

async function handleSubmit(): Promise<void> {
  if (repositoryUrl.value.trim() === '') {
    toast.error('Repository URL is required.')
    return
  }

  if (deployBranch.value.trim() === '') {
    toast.error('Deploy branch is required.')
    return
  }

  isSubmitting.value = true

  try {
    const result = await claimSite(props.site.id, {
      repositoryUrl: repositoryUrl.value.trim(),
      repositoryProvider: repositoryProvider.value,
      deployBranch: deployBranch.value.trim(),
      autoDeployEnabled: autoDeployEnabled.value,
    })

    claimedSite.value = result.site

    if (result.webhookSecret !== undefined) {
      revealedWebhookSecret.value = result.webhookSecret
      return
    }

    finishClaim(result.site)
  } catch {
    toast.error('Unable to claim site. Check the form and try again.')
  } finally {
    isSubmitting.value = false
  }
}

async function copyWebhookSecret(): Promise<void> {
  if (revealedWebhookSecret.value === null) {
    return
  }

  try {
    await navigator.clipboard.writeText(revealedWebhookSecret.value)
    toast.success('Webhook secret copied.')
  } catch {
    toast.error('Unable to copy webhook secret.')
  }
}

function finishClaim(site: Site): void {
  emit('claimed', site)
  emit('update:open', false)
}

function handleDone(): void {
  if (claimedSite.value !== null) {
    finishClaim(claimedSite.value)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Claim site</DialogTitle>
        <DialogDescription>
          Configure this discovered site so Helix can deploy it. Existing server configuration is left unchanged.
        </DialogDescription>
      </DialogHeader>

      <div v-if="showSecretPanel" class="space-y-4">
        <p class="text-sm text-muted-foreground">
          Site claimed successfully. Copy this webhook secret now — it will not be shown again.
        </p>
        <div class="space-y-2">
          <Label for="claim-webhook-secret">Webhook secret</Label>
          <div class="flex gap-2">
            <Input
              id="claim-webhook-secret"
              :model-value="revealedWebhookSecret ?? ''"
              readonly
              data-testid="claim-webhook-secret-input"
            />
            <Button type="button" variant="outline" @click="copyWebhookSecret">
              Copy
            </Button>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" data-testid="claim-done-button" @click="handleDone">
            Done
          </Button>
        </DialogFooter>
      </div>

      <form v-else class="space-y-4" @submit.prevent="handleSubmit">
        <div class="space-y-2">
          <Label for="claim-repository-url">Repository URL</Label>
          <Input
            id="claim-repository-url"
            v-model="repositoryUrl"
            placeholder="https://github.com/org/repo.git"
            autocomplete="off"
            data-testid="claim-repository-url-input"
          />
        </div>

        <div class="space-y-2">
          <Label for="claim-repository-provider">Provider</Label>
          <Select v-model="repositoryProvider">
            <SelectTrigger id="claim-repository-provider" data-testid="claim-repository-provider-select">
              <SelectValue placeholder="Select provider" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in providerOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="claim-deploy-branch">Deploy branch</Label>
          <Input
            id="claim-deploy-branch"
            v-model="deployBranch"
            placeholder="main"
            autocomplete="off"
            data-testid="claim-deploy-branch-input"
          />
        </div>

        <div class="flex items-start gap-2">
          <input
            id="claim-auto-deploy-enabled"
            v-model="autoDeployEnabled"
            type="checkbox"
            class="mt-0.5 rounded border-input"
            data-testid="claim-auto-deploy-checkbox"
          >
          <Label for="claim-auto-deploy-enabled" class="font-normal leading-relaxed">
            Enable auto-deploy via webhook
          </Label>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="emit('update:open', false)">
            Cancel
          </Button>
          <Button type="submit" :disabled="isSubmitting" data-testid="claim-submit-button">
            {{ isSubmitting ? 'Claiming…' : 'Claim site' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
