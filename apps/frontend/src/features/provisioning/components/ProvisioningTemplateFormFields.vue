<script setup lang="ts">
import { computed } from 'vue'
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
import { Textarea } from '@/components/ui/textarea'
import { PROVISIONING_SERVICE_LABELS } from '@/features/provisioning/constants'
import type { ProvisioningTemplateFormState } from '@/features/provisioning/types'
import {
  NODE_VERSIONS,
  PHP_VERSIONS,
  PROVISIONING_SCRIPTS,
  type ProvisioningScript,
} from '@/features/servers/types'

interface Props {
  modelValue: ProvisioningTemplateFormState
  readOnly?: boolean
  validationError?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  readOnly: false,
  validationError: null,
})

const emit = defineEmits<{
  'update:modelValue': [value: ProvisioningTemplateFormState]
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value: ProvisioningTemplateFormState) => emit('update:modelValue', value),
})

const selectedServices = computed(() => new Set(form.value.services))

const showPhpSelector = computed(() => selectedServices.value.has('php'))
const showNodeSelector = computed(() => selectedServices.value.has('nodejs'))

function toggleService(script: ProvisioningScript): void {
  if (props.readOnly) {
    return
  }

  const next = new Set(form.value.services)

  if (next.has(script)) {
    next.delete(script)
  } else {
    next.add(script)
  }

  form.value = {
    ...form.value,
    services: [...next],
  }
}

function updateField<K extends keyof ProvisioningTemplateFormState>(
  field: K,
  value: ProvisioningTemplateFormState[K],
): void {
  form.value = {
    ...form.value,
    [field]: value,
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-2">
      <Label for="template-name">Template name</Label>
      <Input
        id="template-name"
        :model-value="form.name"
        :disabled="readOnly"
        placeholder="laravel-production"
        @update:model-value="(value) => updateField('name', String(value))"
      />
    </div>

    <div class="space-y-2">
      <Label for="template-description">Description</Label>
      <Textarea
        id="template-description"
        :model-value="form.description"
        :disabled="readOnly"
        rows="3"
        placeholder="Optional notes for your team"
        @update:model-value="(value) => updateField('description', String(value))"
      />
    </div>

    <div class="space-y-2">
      <Label>Services</Label>
      <div class="flex flex-wrap gap-2">
        <Button
          v-for="script in PROVISIONING_SCRIPTS"
          :key="script"
          type="button"
          size="sm"
          :variant="selectedServices.has(script) ? 'default' : 'outline'"
          :disabled="readOnly"
          @click="toggleService(script)"
        >
          {{ PROVISIONING_SERVICE_LABELS[script] }}
        </Button>
      </div>
      <p v-if="validationError !== null" class="text-sm text-destructive">
        {{ validationError }}
      </p>
    </div>

    <div v-if="showPhpSelector" class="space-y-2">
      <Label for="template-php-version">PHP version</Label>
      <Select
        :model-value="form.phpVersion"
        :disabled="readOnly"
        @update:model-value="(value) => updateField('phpVersion', String(value))"
      >
        <SelectTrigger id="template-php-version">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="version in PHP_VERSIONS"
            :key="version"
            :value="version"
          >
            PHP {{ version }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="showNodeSelector" class="space-y-2">
      <Label for="template-node-version">Node.js version</Label>
      <Select
        :model-value="String(form.nodeVersion)"
        :disabled="readOnly"
        @update:model-value="(value) => updateField('nodeVersion', Number(value))"
      >
        <SelectTrigger id="template-node-version">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="version in NODE_VERSIONS"
            :key="version"
            :value="String(version)"
          >
            Node {{ version }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
  </div>
</template>
