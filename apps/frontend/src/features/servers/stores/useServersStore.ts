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
  const activeGroupFilter = ref<string | null>(null)

  interface FetchOptions {
    tags?: string[]
    serverGroupId?: string | null
  }

  async function fetch(options: FetchOptions = {}): Promise<void> {
    const activeOrgId = orgId.value

    if (activeOrgId === null) {
      servers.value = []

      return
    }

    if (options.tags !== undefined) {
      activeTagFilters.value = options.tags
    }

    if (options.serverGroupId !== undefined) {
      activeGroupFilter.value = options.serverGroupId
    }

    isLoading.value = true
    fetchError.value = null

    try {
      servers.value = await fetchServers(activeOrgId, {
        tags: activeTagFilters.value.length > 0 ? activeTagFilters.value : undefined,
        serverGroupId: activeGroupFilter.value ?? undefined,
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

  interface ServerUpdatePayload {
    serverId: string
    status?: string
    os?: string | null
    phpVersion?: string | null
    nodeVersion?: string | null
    installedServices?: Server['installedServices'] | Record<string, { installed?: boolean }>
  }

  function applyServerUpdate(payload: ServerUpdatePayload): void {
    const index = servers.value.findIndex(server => server.id === payload.serverId)

    if (index === -1) {
      void fetch()

      return
    }

    const current = servers.value[index]
    const installedServices = payload.installedServices !== undefined
      ? normalizeInstalledServices(payload.installedServices)
      : current.installedServices

    servers.value[index] = {
      ...current,
      status: (payload.status ?? current.status) as typeof current.status,
      os: payload.os ?? current.os,
      phpVersion: payload.phpVersion ?? current.phpVersion,
      nodeVersion: payload.nodeVersion ?? current.nodeVersion,
      installedServices,
    }
  }

  function normalizeInstalledServices(
    services: Server['installedServices'] | Record<string, { installed?: boolean }>,
  ): Server['installedServices'] {
    if (Array.isArray(services)) {
      return services
    }

    return Object.entries(services)
      .filter(([, value]) => value?.installed === true)
      .map(([name]) => name)
  }

  function removeServer(serverId: string): void {
    servers.value = servers.value.filter(server => server.id !== serverId)
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
    activeGroupFilter,
    fetch,
    getById,
    invalidateCache,
    applyServerUpdate,
    removeServer,
    handleServerConnected,
  }
})
