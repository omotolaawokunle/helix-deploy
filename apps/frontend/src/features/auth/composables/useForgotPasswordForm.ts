import { forgotPasswordRequest } from '@/features/auth/api'
import type { ForgotPasswordPayload } from '@/features/auth/types'
import { extractFieldErrors } from '@/lib/validation-errors'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { ref } from 'vue'
import { z } from 'zod'

const forgotPasswordSchema = toTypedSchema(
  z.object({
    email: z.string().email('Enter a valid email address'),
  }),
)

export function useForgotPasswordForm() {
  const isSubmitting = ref(false)
  const submitted = ref(false)
  const apiError = ref<string | null>(null)

  const form = useForm({
    validationSchema: forgotPasswordSchema,
    initialValues: {
      email: '',
    },
  })

  async function submitForgotPassword(values: ForgotPasswordPayload): Promise<void> {
    apiError.value = null
    isSubmitting.value = true

    try {
      await forgotPasswordRequest(values)
      submitted.value = true
    } catch (error: unknown) {
      const fieldErrors = extractFieldErrors(error)

      if (fieldErrors !== null) {
        form.setErrors(fieldErrors)
        return
      }

      apiError.value = 'Unable to send reset link. Try again.'
    } finally {
      isSubmitting.value = false
    }
  }

  const onSubmit = form.handleSubmit(submitForgotPassword)

  return {
    form,
    isSubmitting,
    submitted,
    apiError,
    onSubmit,
    submitForgotPassword,
  }
}
