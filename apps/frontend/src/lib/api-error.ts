import { isAxiosError } from 'axios'

export function extractApiErrorMessage(error: unknown, fallback: string): string {
  if (!isAxiosError(error)) {
    return fallback
  }

  const message = error.response?.data?.message

  if (typeof message === 'string' && message.trim() !== '') {
    return message
  }

  return fallback
}
