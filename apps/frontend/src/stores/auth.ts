import { defineStore } from 'pinia'
import { api } from '@/lib/axios'
import type { User } from '@/types'

interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    isAuthenticated: false,
    isLoading: false,
  }),
  actions: {
    setAuthUser(user: User | null): void {
      this.user = user
      this.isAuthenticated = user !== null
    },
    clearAuth(): void {
      this.setAuthUser(null)
    },
    async init(): Promise<void> {
      this.isLoading = true

      try {
        const response = await api.get<User>('/api/v1/user')
        this.setAuthUser(response.data)
      } catch {
        this.clearAuth()
      } finally {
        this.isLoading = false
      }
    },
    async login(payload: LoginPayload): Promise<void> {
      this.isLoading = true

      try {
        await api.get('/sanctum/csrf-cookie')
        const response = await api.post<User>('/api/v1/login', payload)
        this.setAuthUser(response.data)
      } finally {
        this.isLoading = false
      }
    },
    async logout(): Promise<void> {
      this.isLoading = true

      try {
        await api.post('/api/v1/logout')
      } finally {
        this.clearAuth()
        this.isLoading = false
      }
    },
    async register(payload: RegisterPayload): Promise<void> {
      this.isLoading = true

      try {
        await api.get('/sanctum/csrf-cookie')
        const response = await api.post<User>('/api/v1/register', payload)
        this.setAuthUser(response.data)
      } finally {
        this.isLoading = false
      }
    },
  },
})
