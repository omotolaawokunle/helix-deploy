<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import ProvisioningTemplateFormFields from '@/features/provisioning/components/ProvisioningTemplateFormFields.vue'
import type {
  ProvisioningTemplateFormState,
  ProvisioningTemplateRecord,
} from '@/features/provisioning/types'

export type ProvisioningTemplateSheetMode = 'create' | 'edit' | 'view'

interface Props {
  open: boolean
  mode: ProvisioningTemplateSheetMode
  template?: ProvisioningTemplateRecord | null
  isSubmitting?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  template: null,
  isSubmitting: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  submit: [state: ProvisioningTemplateFormState]
}>()

const form = ref<ProvisioningTemplateFormState>(createEmptyForm())
const servicesValidationError = ref<string | null>(null)

const isReadOnly = computed(() => props.mode === 'view')

const sheetTitle = computed((): string => {
  if (props.mode === 'create') {
    return 'Create template'
  }

  if (props.mode === 'edit') {
    return 'Edit template'
  }

  return 'Template details'
})

const sheetDescription = computed((): string => {
  if (props.mode === 'view') {
    return 'Built-in templates are read-only. Apply them when provisioning a server.'
  }

  if (props.mode === 'create') {
    return 'Define a reusable stack your team can apply when provisioning servers.'
  }

  return 'Update services and default runtime versions for this organization template.'
})

function createEmptyForm(): ProvisioningTemplateFormState {
  return {
    name: '',
    description: '',
    services: ['create-deploy-user', 'nginx'],
    phpVersion: '8.3',
    nodeVersion: 20,
    postgresqlVersion: '16',
    mysqlVersion: '8.4',
    pythonVersion: '3.12',
    redisPassword: '',
  }
}

function formFromTemplate(template: ProvisioningTemplateRecord): ProvisioningTemplateFormState {
  return {
    name: template.name,
    description: template.description ?? '',
    services: [...template.services],
    phpVersion: typeof template.options.phpVersion === 'string' ? template.options.phpVersion : '8.3',
    nodeVersion: typeof template.options.nodeVersion === 'number' ? template.options.nodeVersion : 20,
    postgresqlVersion: typeof template.options.postgresqlVersion === 'string' ? template.options.postgresqlVersion : '16',
    mysqlVersion: typeof template.options.mysqlVersion === 'string' ? template.options.mysqlVersion : '8.4',
    pythonVersion: typeof template.options.pythonVersion === 'string' ? template.options.pythonVersion : '3.12',
    redisPassword: typeof template.options.redisPassword === 'string' ? template.options.redisPassword : '',
  }
}

watch(
  () => [props.open, props.mode, props.template] as const,
  ([isOpen, mode, template]) => {
    if (!isOpen) {
      return
    }

    servicesValidationError.value = null

    if (mode === 'create' || template === null) {
      form.value = createEmptyForm()

      return
    }

    form.value = formFromTemplate(template)
  },
  { immediate: true },
)

function handleSubmit(): void {
  if (isReadOnly.value) {
    return
  }

  if (form.value.services.length === 0) {
    servicesValidationError.value = 'Select at least one service.'

    return
  }

  if (form.value.name.trim() === '') {
    return
  }

  servicesValidationError.value = null
  emit('submit', form.value)
}
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent side="right" class="flex w-full flex-col sm:max-w-lg">
      <SheetHeader>
        <SheetTitle>{{ sheetTitle }}</SheetTitle>
        <SheetDescription>{{ sheetDescription }}</SheetDescription>
      </SheetHeader>

      <SheetBody>
        <ProvisioningTemplateFormFields
          v-model="form"
          :read-only="isReadOnly"
          :validation-error="servicesValidationError"
        />
      </SheetBody>

      <SheetFooter>
        <Button type="button" variant="outline" @click="emit('update:open', false)">
          {{ isReadOnly ? 'Close' : 'Cancel' }}
        </Button>
        <Button
          v-if="!isReadOnly"
          type="button"
          :disabled="isSubmitting || form.name.trim() === '' || form.services.length === 0"
          data-testid="provisioning-template-submit"
          @click="handleSubmit"
        >
          {{ isSubmitting ? 'Saving…' : mode === 'create' ? 'Create template' : 'Save changes' }}
        </Button>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
