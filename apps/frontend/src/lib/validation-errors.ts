import { isAxiosError } from 'axios'

export type FieldErrors = Record<string, string[]>

export function extractFieldErrors(error: unknown): FieldErrors | null {
  if (!isAxiosError(error) || error.response?.status !== 422) {
    return null
  }

  const errors = error.response.data?.errors

  if (errors === null || typeof errors !== 'object') {
    return null
  }

  return errors as FieldErrors
}

export function firstFieldError(errors: FieldErrors, field: string): string | undefined {
  const messages = errors[field]

  return Array.isArray(messages) ? messages[0] : undefined
}
