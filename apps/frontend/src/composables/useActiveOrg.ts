import { computed, type ComputedRef } from 'vue'
import { useAuthStore } from '@/features/auth/stores/useAuthStore'
import type { Organization } from '@/types'

interface UseActiveOrgReturn {
  currentOrg: ComputedRef<Organization | null>
  orgId: ComputedRef<string | null>
}

export function useActiveOrg(): UseActiveOrgReturn {
  const authStore = useAuthStore()

  const currentOrg = computed(() => authStore.currentOrg)

  const orgId = computed(() => authStore.currentOrg?.id ?? null)

  return {
    currentOrg,
    orgId,
  }
}
