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
const showPostgresqlSelector = computed(() => selectedServices.value.has('postgresql'))
const showMysqlSelector = computed(() => selectedServices.value.has('mysql'))
const showPythonSelector = computed(() => selectedServices.value.has('python'))
const showRedisPassword = computed(() => selectedServices.value.has('redis'))

const POSTGRESQL_VERSIONS = ['14', '15', '16', '17', '18'] as const
const MYSQL_VERSIONS = ['8.0', '8.4'] as const
const PYTHON_VERSIONS = ['3.10', '3.11', '3.12', '3.13'] as const

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

    <div v-if="showPostgresqlSelector" class="space-y-2">
      <Label for="template-postgresql-version">PostgreSQL version</Label>
      <Select
        :model-value="form.postgresqlVersion"
        :disabled="readOnly"
        @update:model-value="(value) => updateField('postgresqlVersion', String(value))"
      >
        <SelectTrigger id="template-postgresql-version">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="version in POSTGRESQL_VERSIONS"
            :key="version"
            :value="version"
          >
            PostgreSQL {{ version }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="showMysqlSelector" class="space-y-2">
      <Label for="template-mysql-version">MySQL version</Label>
      <Select
        :model-value="form.mysqlVersion"
        :disabled="readOnly"
        @update:model-value="(value) => updateField('mysqlVersion', String(value))"
      >
        <SelectTrigger id="template-mysql-version">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="version in MYSQL_VERSIONS"
            :key="version"
            :value="version"
          >
            MySQL {{ version }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="showPythonSelector" class="space-y-2">
      <Label for="template-python-version">Python version</Label>
      <Select
        :model-value="form.pythonVersion"
        :disabled="readOnly"
        @update:model-value="(value) => updateField('pythonVersion', String(value))"
      >
        <SelectTrigger id="template-python-version">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="version in PYTHON_VERSIONS"
            :key="version"
            :value="version"
          >
            Python {{ version }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="showRedisPassword" class="space-y-2">
      <Label for="template-redis-password">Redis password (optional)</Label>
      <Input
        id="template-redis-password"
        :model-value="form.redisPassword"
        :disabled="readOnly"
        type="password"
        autocomplete="new-password"
        placeholder="Auto-generated if left blank"
        @update:model-value="(value) => updateField('redisPassword', String(value))"
      />
    </div>
  </div>
</template>
