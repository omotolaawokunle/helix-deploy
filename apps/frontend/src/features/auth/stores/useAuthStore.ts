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

  const isAuthenticated = computed(() => user.value !== null)

  const currentOrg = computed(() => user.value?.currentOrganization ?? null)

  const isOwner = computed(() => currentRole.value === TeamRole.Owner)

  const isAdmin = computed(
    () => currentRole.value === TeamRole.Admin || currentRole.value === TeamRole.Owner,
  )

  const isDeveloper = computed(() => {
    if (currentRole.value === null) {
      return false
    }

    return (
      currentRole.value === TeamRole.Maintainer
      || currentRole.value === TeamRole.Member
      || isAdmin.value
    )
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

  async function init(): Promise<void> {
    isLoading.value = true

    try {
      const authUser = await fetchAuthUser()
      setAuthUser(authUser)
      await loadOrganizations()
      await resolveCurrentRole()
    } catch {
      clearAuth()
    } finally {
      isLoading.value = false
    }
  }

  async function login(payload: LoginPayload): Promise<void> {
    isLoading.value = true

    try {
      const authUser = await loginRequest(payload)
      setAuthUser(authUser)
      await loadOrganizations()
      await resolveCurrentRole()
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
    isAuthenticated,
    currentOrg,
    isOwner,
    isAdmin,
    isDeveloper,
    canViewAuditLog,
    canManageOrgSettings,
    setAuthUser,
    clearAuth,
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
