<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import ConfirmDestructiveDialog from '@/components/common/ConfirmDestructiveDialog.vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { shortCommitHash } from '@/lib/format'
import type { DeploymentDetail } from '@/features/deployments/types'

interface Props {
  open: boolean
  deployment: DeploymentDetail | null
  isProduction: boolean
  isSubmitting?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isSubmitting: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  submit: [reason: string | undefined]
}>()

const reason = ref('')

const reasonLength = computed(() => reason.value.trim().length)

const meetsReasonRequirement = computed(() => {
  if (!props.isProduction) {
    return true
  }

  return reasonLength.value >= 10
})

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      reason.value = ''
    }
  },
)

function handleConfirm(): void {
  const trimmed = reason.value.trim()

  emit('submit', trimmed === '' ? undefined : trimmed)
}

function handleOpenChange(value: boolean): void {
  emit('update:open', value)
}
</script>

<template>
  <ConfirmDestructiveDialog
    :open="open"
    title="Rollback deployment"
    description="This will deploy the previous release to the server. This action cannot be undone."
    confirm-text="rollback"
    confirm-button-label="Rollback"
    :can-confirm="meetsReasonRequirement && !isSubmitting"
    @update:open="handleOpenChange"
    @confirm="handleConfirm"
  >
    <div class="space-y-4" data-testid="rollback-dialog">
      <ProductionWarningBanner
        v-if="isProduction && deployment !== null"
        :resource-name="deployment.site?.domain ?? 'site'"
        :is-production="true"
        variant="inline"
        message="You are rolling back a PRODUCTION deployment"
      />

      <dl v-if="deployment !== null" class="space-y-2 text-sm">
        <div class="flex justify-between gap-4">
          <dt class="text-muted-foreground">
            Commit
          </dt>
          <dd class="font-mono">
            {{ shortCommitHash(deployment.commitHash) }}
          </dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-muted-foreground">
            Release path
          </dt>
          <dd class="truncate text-right font-mono">
            {{ deployment.releasePath ?? '—' }}
          </dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-muted-foreground">
            Deployed at
          </dt>
          <dd>
            {{ deployment.finishedAt ? new Date(deployment.finishedAt).toLocaleString() : '—' }}
          </dd>
        </div>
      </dl>

      <div class="space-y-2">
        <Label for="rollback-reason">
          Reason for rollback
          <span v-if="isProduction" class="text-destructive">*</span>
        </Label>
        <Textarea
          id="rollback-reason"
          v-model="reason"
          data-testid="rollback-reason"
          :placeholder="isProduction ? 'Describe why you are rolling back (min 10 characters)' : 'Optional reason'"
          rows="4"
        />
        <p
          v-if="isProduction"
          class="text-xs text-muted-foreground"
          data-testid="rollback-reason-counter"
        >
          {{ reasonLength }} / 10 minimum characters
        </p>
      </div>
    </div>
  </ConfirmDestructiveDialog>
</template>
