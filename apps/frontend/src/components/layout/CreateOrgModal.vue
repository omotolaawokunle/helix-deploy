<script setup lang="ts">
import { ref } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { extractFieldErrors } from '@/lib/validation-errors'

interface Props {
  open: boolean
}

defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  created: []
}>()

const authStore = useAuthStore()

const schema = toTypedSchema(
  z.object({
    name: z.string().min(2, 'Organization name must be at least 2 characters'),
  }),
)

const { handleSubmit, setErrors, isSubmitting } = useForm({
  validationSchema: schema,
  initialValues: {
    name: '',
  },
})

const apiError = ref<string | null>(null)

const onSubmit = handleSubmit(async (values) => {
  apiError.value = null

  try {
    await authStore.createOrg(values.name)
    emit('created')
    emit('update:open', false)
  } catch (error: unknown) {
    const fieldErrors = extractFieldErrors(error)

    if (fieldErrors !== null) {
      setErrors(fieldErrors)
      return
    }

    apiError.value = 'Unable to create organization. Please try again.'
  }
})
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>New Organization</DialogTitle>
        <DialogDescription>
          Create a new organization to manage servers and deployments.
        </DialogDescription>
      </DialogHeader>

      <form class="space-y-4" @submit="onSubmit">
        <FormField v-slot="{ componentField }" name="name">
          <FormItem>
            <FormLabel>Organization name</FormLabel>
            <FormControl>
              <Input
                v-bind="componentField"
                placeholder="Acme Inc."
                autocomplete="organization"
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>

        <p v-if="apiError" class="text-sm text-destructive">
          {{ apiError }}
        </p>

        <DialogFooter>
          <Button type="button" variant="outline" @click="emit('update:open', false)">
            Cancel
          </Button>
          <Button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Creating…' : 'Create organization' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
