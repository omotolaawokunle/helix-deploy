import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchServer, fetchServers } from '@/features/servers/api'
import { useActiveOrg } from '@/composables/useActiveOrg'
import type { Server } from '@/types'

export const useServersStore = defineStore('servers', () => {
  const servers = ref<Server[]>([])
  const isLoading = ref(false)
  const hasFetched = ref(false)
  const fetchError = ref<string | null>(null)

  const { orgId } = useActiveOrg()

  const activeTagFilters = ref<string[]>([])

  async function fetch(tagFilters: string[] = activeTagFilters.value): Promise<void> {
    const activeOrgId = orgId.value

    if (activeOrgId === null) {
      servers.value = []

      return
    }

    activeTagFilters.value = tagFilters
    isLoading.value = true
    fetchError.value = null

    try {
      servers.value = await fetchServers(activeOrgId, {
        tags: tagFilters.length > 0 ? tagFilters : undefined,
      })
      hasFetched.value = true
    } catch {
      fetchError.value = 'Unable to load servers.'
      hasFetched.value = true
    } finally {
      isLoading.value = false
    }
  }

  async function getById(serverId: string): Promise<Server | undefined> {
    const cached = servers.value.find(server => server.id === serverId)

    if (cached !== undefined) {
      return cached
    }

    const server = await fetchServer(serverId)
    const existingIndex = servers.value.findIndex(entry => entry.id === serverId)

    if (existingIndex === -1) {
      servers.value.push(server)
    } else {
      servers.value[existingIndex] = server
    }

    return server
  }

  function invalidateCache(): void {
    hasFetched.value = false
    servers.value = []
    fetchError.value = null
  }

  async function handleServerConnected(): Promise<void> {
    invalidateCache()
    await fetch()
  }

  return {
    servers,
    isLoading,
    hasFetched,
    fetchError,
    activeTagFilters,
    fetch,
    getById,
    invalidateCache,
    handleServerConnected,
  }
})
