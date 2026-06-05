import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  createOrganization,
  fetchAuthUser,
  fetchOrganizations,
  loginRequest,
  logoutRequest,
  registerRequest,
  resendVerificationEmail,
  switchOrganization,
} from '@/features/auth/api'
import type { AuthUser, LoginPayload, RegisterPayload } from '@/features/auth/types'
import { fetchCurrentMemberRole } from '@/features/organizations/api'
import type { Organization } from '@/types'
import { TeamRole } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const organizations = ref<Organization[]>([])
  const currentRole = ref<TeamRole | null>(null)
  const isLoading = ref(false)
  const isInitialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  const isEmailVerified = computed(() => user.value?.emailVerifiedAt != null)

  const currentOrg = computed(() => user.value?.currentOrganization ?? null)

  const isOwner = computed(() => currentRole.value === TeamRole.Owner)

  const isAdmin = computed(
    () => currentRole.value === TeamRole.Admin || currentRole.value === TeamRole.Owner,
  )

  const isDeveloper = computed(() => {
    if (currentRole.value === null) {
      return false
    }

    return currentRole.value === TeamRole.Developer || isAdmin.value
  })

  const canViewAuditLog = computed(() => isAdmin.value)

  const canManageOrgSettings = computed(() => isOwner.value)

  function setAuthUser(nextUser: AuthUser | null): void {
    user.value = nextUser
  }

  function clearAuth(): void {
    user.value = null
    organizations.value = []
    currentRole.value = null
    isInitialized.value = false
  }

  function markAuthInitialized(): void {
    isInitialized.value = true
  }

  async function resolveCurrentRole(): Promise<void> {
    const org = currentOrg.value

    if (org === null || user.value === null) {
      currentRole.value = null

      return
    }

    currentRole.value = await fetchCurrentMemberRole(org.id, user.value.id)
  }

  async function loadOrganizations(): Promise<void> {
    organizations.value = await fetchOrganizations()
  }

  async function hydrateAuthenticatedUser(authUser: AuthUser): Promise<void> {
    setAuthUser(authUser)

    if (authUser.emailVerifiedAt == null) {
      return
    }

    await loadOrganizations()
    await resolveCurrentRole()
  }

  async function init(): Promise<void> {
    if (isInitialized.value) {
      return
    }

    isLoading.value = true

    try {
      const authUser = await fetchAuthUser()
      await hydrateAuthenticatedUser(authUser)
    } catch {
      clearAuth()
    } finally {
      isLoading.value = false
      isInitialized.value = true
    }
  }

  async function login(payload: LoginPayload): Promise<AuthUser> {
    isLoading.value = true

    try {
      const authUser = await loginRequest(payload)
      await hydrateAuthenticatedUser(authUser)

      return authUser
    } finally {
      isLoading.value = false
    }
  }

  async function logout(): Promise<void> {
    isLoading.value = true

    try {
      await logoutRequest()
    } finally {
      clearAuth()
      isLoading.value = false
    }
  }

  async function register(payload: RegisterPayload): Promise<AuthUser> {
    isLoading.value = true

    try {
      return await registerRequest(payload)
    } finally {
      isLoading.value = false
    }
  }

  async function resendVerification(): Promise<void> {
    await resendVerificationEmail()
  }

  async function switchOrg(orgId: string): Promise<void> {
    await switchOrganization(orgId)
    window.location.reload()
  }

  async function createOrg(name: string): Promise<Organization> {
    const organization = await createOrganization({ name })
    await loadOrganizations()

    return organization
  }

  return {
    user,
    organizations,
    currentRole,
    isLoading,
    isInitialized,
    isAuthenticated,
    isEmailVerified,
    currentOrg,
    isOwner,
    isAdmin,
    isDeveloper,
    canViewAuditLog,
    canManageOrgSettings,
    setAuthUser,
    clearAuth,
    markAuthInitialized,
    init,
    login,
    logout,
    register,
    resendVerification,
    switchOrg,
    createOrg,
    loadOrganizations,
    resolveCurrentRole,
  }
})
