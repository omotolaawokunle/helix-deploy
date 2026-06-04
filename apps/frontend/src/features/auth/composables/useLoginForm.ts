import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { z } from 'zod'
import type { LoginPayload } from '@/features/auth/types'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import { extractFieldErrors } from '@/lib/validation-errors'

const loginSchema = toTypedSchema(
  z.object({
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
  }),
)

export function useLoginForm() {
  const authStore = useAuthStore()
  const router = useRouter()
  const apiError = ref<string | null>(null)

  const form = useForm({
    validationSchema: loginSchema,
    initialValues: {
      email: '',
      password: '',
    },
  })

  async function submitLogin(values: LoginPayload): Promise<void> {
    apiError.value = null

    try {
      await authStore.login(values)
      await router.push('/dashboard')
    } catch (error: unknown) {
      const fieldErrors = extractFieldErrors(error)

      if (fieldErrors !== null) {
        form.setErrors(fieldErrors)
        return
      }

      apiError.value = 'Invalid email or password.'
    }
  }

  const onSubmit = form.handleSubmit(submitLogin)

  return {
    authStore,
    apiError,
    form,
    onSubmit,
    submitLogin,
  }
}
