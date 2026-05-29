import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

type UnauthorizedHandler = () => void | Promise<void>

interface RetriableRequestConfig extends InternalAxiosRequestConfig {
  _retriedAfter419?: boolean
}

let unauthorizedHandler: UnauthorizedHandler | null = null

export function setApiUnauthorizedHandler(handler: UnauthorizedHandler): void {
  unauthorizedHandler = handler
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

api.interceptors.response.use(
  response => response,
  async (error: AxiosError) => {
    const status = error.response?.status
    const config = error.config as RetriableRequestConfig | undefined

    if (status === 422) {
      return Promise.reject(error)
    }

    if (status === 419 && config && !config._retriedAfter419) {
      config._retriedAfter419 = true
      await api.get('/sanctum/csrf-cookie')
      return api(config)
    }

    if (status === 401 && unauthorizedHandler) {
      await unauthorizedHandler()
    }

    return Promise.reject(error)
  },
)
