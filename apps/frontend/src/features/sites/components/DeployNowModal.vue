<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import ProductionWarningBanner from '@/components/common/ProductionWarningBanner.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import type { Site } from '@/types'

interface Props {
  open: boolean
  site: Site | null
  isProduction: boolean
  isSubmitting?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isSubmitting: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  submit: [branch: string]
}>()

const branch = ref('')
const productionConfirmed = ref(false)

const canSubmit = computed(() => {
  if (props.isSubmitting || branch.value.trim() === '') {
    return false
  }

  if (props.isProduction) {
    return productionConfirmed.value
  }

  return true
})

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen && props.site !== null) {
      branch.value = props.site.deployBranch
      productionConfirmed.value = false
    }
  },
)

function handleOpenChange(value: boolean): void {
  emit('update:open', value)
}

function handleSubmit(): void {
  if (!canSubmit.value) {
    return
  }

  emit('submit', branch.value.trim())
}
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent
      side="right"
      class="flex w-full flex-col sm:max-w-md"
      data-testid="deploy-now-sheet"
    >
      <SheetHeader>
        <SheetTitle>Deploy now</SheetTitle>
        <SheetDescription>
          Trigger a deployment for {{ site?.domain ?? 'this site' }}.
        </SheetDescription>
      </SheetHeader>

      <div class="flex flex-1 flex-col gap-4 overflow-y-auto py-4">
        <ProductionWarningBanner
          :resource-name="site?.domain ?? 'site'"
          :is-production="isProduction"
          variant="inline"
        />

        <div class="space-y-2">
          <Label for="deploy-branch">Branch</Label>
          <Input
            id="deploy-branch"
            v-model="branch"
            data-testid="deploy-branch-input"
            placeholder="main"
          />
        </div>

        <div
          v-if="isProduction"
          class="flex items-start gap-2"
        >
          <input
            id="production-confirm"
            v-model="productionConfirmed"
            type="checkbox"
            class="mt-1 size-4 rounded border-input"
            data-testid="production-confirm-checkbox"
          />
          <Label for="production-confirm" class="text-sm leading-snug">
            I confirm this deployment will affect live traffic
          </Label>
        </div>
      </div>

      <SheetFooter class="flex-row justify-end gap-2 border-t pt-4">
        <Button variant="outline" @click="handleOpenChange(false)">
          Cancel
        </Button>
        <Button
          data-testid="deploy-submit"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          Deploy
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
