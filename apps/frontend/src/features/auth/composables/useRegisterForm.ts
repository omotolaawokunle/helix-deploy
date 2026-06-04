import { ref } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { z } from 'zod'
import type { RegisterPayload } from '@/features/auth/types'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { extractFieldErrors } from '@/lib/validation-errors'

const registerSchema = toTypedSchema(
  z.object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string().min(8, 'Confirm your password'),
  }).refine(values => values.password === values.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  }),
)

export function useRegisterForm() {
  const authStore = useAuthStore()
  const showVerificationNotice = ref(false)
  const isResending = ref(false)
  const resendMessage = ref<string | null>(null)
  const registeredEmail = ref<string | null>(null)

  const form = useForm({
    validationSchema: registerSchema,
    initialValues: {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
    },
  })

  async function submitRegister(values: RegisterPayload): Promise<void> {
    resendMessage.value = null

    try {
      const user = await authStore.register(values)
      registeredEmail.value = user.email
      showVerificationNotice.value = true
    } catch (error: unknown) {
      const fieldErrors = extractFieldErrors(error)

      if (fieldErrors !== null) {
        form.setErrors(fieldErrors)
      }
    }
  }

  const onSubmit = form.handleSubmit(submitRegister)

  async function handleResend(): Promise<void> {
    isResending.value = true
    resendMessage.value = null

    try {
      await authStore.resendVerification()
      resendMessage.value = 'Verification email sent.'
    } catch {
      resendMessage.value = 'Unable to resend verification email. Try signing in first.'
    } finally {
      isResending.value = false
    }
  }

  return {
    authStore,
    showVerificationNotice,
    isResending,
    resendMessage,
    registeredEmail,
    onSubmit,
    submitRegister,
    handleResend,
  }
}
