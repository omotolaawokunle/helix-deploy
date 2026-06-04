import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchServer, fetchServers } from '@/features/servers/api'
import { useActiveOrg } from '@/composables/useActiveOrg'
import type { Server } from '@/types'

export const useServersStore = defineStore('servers', () => {
  const servers = ref<Server[]>([])
  const isLoading = ref(false)
  const hasFetched = ref(false)

  const { orgId } = useActiveOrg()

  async function fetch(): Promise<void> {
    const activeOrgId = orgId.value

    if (activeOrgId === null) {
      servers.value = []

      return
    }

    isLoading.value = true

    try {
      servers.value = await fetchServers(activeOrgId)
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
  }

  async function handleServerConnected(): Promise<void> {
    invalidateCache()
    await fetch()
  }

  return {
    servers,
    isLoading,
    hasFetched,
    fetch,
    getById,
    invalidateCache,
    handleServerConnected,
  }
})
