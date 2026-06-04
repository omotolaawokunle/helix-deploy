import { api } from '@/lib/axios'
import type { Site } from '@/types'

interface ResourceResponse<T> {
  data: T
}

export async function fetchSite(siteId: string): Promise<Site> {
  const response = await api.get<ResourceResponse<Site>>(`/api/v1/sites/${siteId}`)

  return response.data.data
}
