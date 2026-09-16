import { resetPasswordRequest } from '@/features/auth/api'
import type { ResetPasswordPayload } from '@/features/auth/types'
import { extractFieldErrors } from '@/lib/validation-errors'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { z } from 'zod'

const resetPasswordSchema = toTypedSchema(
  z
    .object({
      password: z.string().min(8, 'Password must be at least 8 characters'),
      passwordConfirmation: z.string().min(8, 'Confirm your password'),
    })
    .refine((values) => values.password === values.passwordConfirmation, {
      message: 'Passwords do not match',
      path: ['passwordConfirmation'],
    }),
)

export function useResetPasswordForm() {
  const route = useRoute()
  const router = useRouter()
  const isSubmitting = ref(false)
  const apiError = ref<string | null>(null)

  const email = computed((): string => {
    return typeof route.query.email === 'string' ? route.query.email : ''
  })

  const token = computed((): string => {
    return typeof route.query.token === 'string' ? route.query.token : ''
  })

  const hasValidLink = computed((): boolean => {
    return email.value !== '' && token.value !== ''
  })

  const form = useForm({
    validationSchema: resetPasswordSchema,
    initialValues: {
      password: '',
      passwordConfirmation: '',
    },
  })

  async function submitResetPassword(values: {
    password: string
    passwordConfirmation: string
  }): Promise<void> {
    apiError.value = null

    if (!hasValidLink.value) {
      apiError.value = 'This reset link is invalid or incomplete.'
      return
    }

    isSubmitting.value = true

    const payload: ResetPasswordPayload = {
      email: email.value,
      token: token.value,
      password: values.password,
      passwordConfirmation: values.passwordConfirmation,
    }

    try {
      await resetPasswordRequest(payload)
      await router.push({
        path: '/login',
        query: { reset: '1' },
      })
    } catch (error: unknown) {
      const fieldErrors = extractFieldErrors(error)

      if (fieldErrors !== null) {
        form.setErrors(fieldErrors)
        return
      }

      apiError.value = 'Unable to reset password. Request a new link and try again.'
    } finally {
      isSubmitting.value = false
    }
  }

  const onSubmit = form.handleSubmit(submitResetPassword)

  return {
    form,
    isSubmitting,
    apiError,
    hasValidLink,
    email,
    onSubmit,
    submitResetPassword,
  }
}
